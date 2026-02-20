<?php

namespace App\Http\Controllers\Admin\Helper;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Tiraggio;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Controllers\Admin\Helper\Core\FilterHandler;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ExportCsvController extends Controller
{
	public function exportCrudToCsv($crud)
	{
		// Increase execution time and memory limit for large exports
		set_time_limit(0); // No timeout
		ini_set("memory_limit", "512M"); // Increase memory limit

		// Build the fully qualified model class name dynamically
		$modelClass = "App\\Models\\" . Str::studly($crud);

		// Check if the model class exists before proceeding
		if (!class_exists($modelClass)) {
			throw new NotFoundHttpException("Model not found");
		}

		// Create model instance
		$model = new $modelClass();

		// Initialize CRUD panel for the model
		CRUD::setModel($modelClass);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/" . $crud);
		CRUD::setEntityNameStrings($crud, $crud . "s");

		// Get query builder from model class
		$query = $modelClass::query();

		// Apply filters directly to the query using FilterHandler
		FilterHandler::applyFiltersToQuery($query, $model);

		// Generate CSV file path and name
		$csvFileName = strtolower(class_basename($modelClass)) . "_export_" . now()->format("Ymd_His") . ".csv";
		$csvPath = storage_path("app/" . $csvFileName);

		// Open file handle early
		$handle = fopen($csvPath, "w");

		// Disable model events for better performance during export
		$modelClass::unsetEventDispatcher();

		// Check if we have data
		if ($query->count() === 0) {
			fclose($handle);
			unlink($csvPath);
			throw new NotFoundHttpException("No data to export");
		}

		// Process data in chunks to avoid memory issues (larger chunks for better performance)
		$isFirstChunk = true;
		$headers = [];

		$query->chunk(5000, function ($entries) use ($handle, &$isFirstChunk, &$headers) {
			foreach ($entries as $entry) {
				$baseData = $entry->toArray();

				// Build headers only once from first entry
				if ($isFirstChunk) {
					$headers = array_keys($baseData);
					fputcsv($handle, $headers);
					$isFirstChunk = false;
				}

				// Build row data
				$rowData = [];

				foreach ($baseData as $value) {
					// Convert arrays/objects to JSON strings for CSV compatibility
					if (is_array($value) || is_object($value)) {
						$value = json_encode($value);
					}

					$rowData[] = $value;
				}

				fputcsv($handle, $rowData);
			}
		});

		fclose($handle);

		// Get download token from request for loading indicator
		$downloadToken = request()->get("downloadToken");

		// Set cookie to signal download has started (for loading indicator)
		if ($downloadToken) {
			setcookie("downloadToken", $downloadToken, time() + 60, "/"); // 1 minute expiry
		}

		// Return downloadable response and delete file after download
		return response()->download($csvPath)->deleteFileAfterSend(true);
	}

	/**
	 * Export CSV credito outstanding
	 *
	 * Crea un file CSV con i dati dei tiraggi filtrati per NDG e stato.
	 */
	public function exportCreditoOutstanding($request)
	{
		// Get data from JSON if available, otherwise from request
		$requestData = $request->json()->all() ?: $request->all();

		$values = $requestData;

		// Validate the data
		$validator = Validator::make($values, [
			"ndg" => "sometimes|string",
			"status" => "sometimes|string|in:sopra_forecast,sotto_forecast,sotto_baseline",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$values = $validator->validated();

		$statusFilter = $values["status"] ?? null;

		$user_id = !empty($values["ndg"]) ? optional(User::where("ndg", $values["ndg"])->first())->id : null;
		if (!empty($values["ndg"]) && empty($user_id)) {
			throw new NotFoundHttpException("NDG non trovato");
		}

		$tiraggiQuery = Tiraggio::with(["delibera", "user"])
			->where("status", "accepted")
			->whereNotNull("importo_deliberato");

		if (!empty($user_id)) {
			$tiraggiQuery->where("user_id", $user_id);
		}

		$tiraggi = $tiraggiQuery->get();
		$data = [];

		foreach ($tiraggi as $tiraggio) {
			$user = $tiraggio->user;
			$delibera = $tiraggio->delibera;
			$forecast = $tiraggio->getForecastArray();
			$baseline = $tiraggio->getBaselineArray();
			$mesi_da_erogato = (int) Carbon::parse($tiraggio->data_delibera)->diffInMonths(Carbon::now());
			$forecast_tot = array_sum(array_slice($forecast, 0, $mesi_da_erogato));
			$baseline_tot = array_sum(array_slice($baseline, 0, $mesi_da_erogato));
			$mesi_mancanti = count($forecast) - $mesi_da_erogato;
			$fine_rimborso_prevista = Carbon::parse(Carbon::now())->addMonths($mesi_mancanti)->format("Y-m-d");
			$esito =
				$tiraggio->trattenuta_totale >= $forecast_tot
					? "sopra_forecast"
					: ($tiraggio->trattenuta_totale >= $baseline_tot
						? "sotto_forecast"
						: "sotto_baseline");

			$item = [
				"ndg" => $user->ndg,
				"rating" => $user->rating,
				"plafond_prevalutato" => $delibera->max_atp ?? 0,
				"data_erogazione" => $tiraggio->data_delibera,
				"importo_erogato" => $tiraggio->importo_deliberato,
				"quota_capitale_residua" => $tiraggio->importo_deliberato - $tiraggio->trattenuta_totale,
				"fine_rimborso_prevista" => $fine_rimborso_prevista,
				"trattenute" => $tiraggio->trattenuta_totale,
				"forecast_tot" => $forecast_tot,
				"baseline_tot" => $baseline_tot,
				"esito" => $esito,
			];

			if (!$statusFilter || $esito === $statusFilter) {
				$data[] = $item;
			}
		}

		// Create export directory if it doesn't exist
		$exportDir = public_path("storage/export-csv");
		if (!file_exists($exportDir)) {
			mkdir($exportDir, 0755, true);
		}

		// Clean old files - keep only the most recent one
		$files = glob($exportDir . "/credito_outstanding_*.csv");
		if (count($files) > 0) {
			// Sort by modification time (newest first)
			usort($files, function ($a, $b) {
				return filemtime($b) - filemtime($a);
			});

			// Delete all files except the newest one
			$filesToDelete = array_slice($files, 1);
			foreach ($filesToDelete as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

		// Generate filename with timestamp
		$filename = "credito_outstanding_" . date("Y-m-d_H-i-s") . ".csv";
		$filepath = $exportDir . "/" . $filename;

		// Create CSV file
		$file = fopen($filepath, "w");

		// Add BOM for UTF-8 compatibility
		fwrite($file, "\xEF\xBB\xBF");

		// Add CSV headers
		$headers = [
			"NDG",
			"Rating",
			"Plafond Prevalutato",
			"Data Erogazione",
			"Importo Erogato",
			"Quota Capitale Residua",
			"Fine Rimborso Prevista",
			"Trattenute",
			"Forecast Tot",
			"Baseline Tot",
			"Esito",
		];
		fputcsv($file, $headers);

		// Add data rows
		foreach ($data as $row) {
			fputcsv($file, [
				$row["ndg"],
				$row["rating"],
				$row["plafond_prevalutato"],
				$row["data_erogazione"],
				$row["importo_erogato"],
				$row["quota_capitale_residua"],
				$row["fine_rimborso_prevista"],
				$row["trattenute"],
				$row["forecast_tot"],
				$row["baseline_tot"],
				$row["esito"],
			]);
		}

		fclose($file);

		// Return the file path
		return response()->json(
			[
				"success" => true,
				"file_path" => "/storage/export-csv/" . $filename,
				"filename" => $filename,
				"total_records" => count($data),
			],
			201
		);
	}

	/**
	 * Export CSV target potenziale
	 *
	 * Crea un file CSV con i dati degli utenti potenziali filtrati.
	 */
	public function exportTargetPotenziale($request)
	{
		// Get data from JSON if available, otherwise from request
		$requestData = $request->json()->all() ?: $request->all();

		$values = $requestData;

		// Validate the data
		$validator = Validator::make($values, [
			"ndg" => "sometimes|string",
			"ateco" => "sometimes|string",
			"filiale_id" => "sometimes|integer|exists:filiali,id",
			"provincia" => "sometimes|string",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$values = $validator->validated();

		$usersQuery = User::with(["delibere", "filiale"])
			->where("sospeso", false)
			->whereNot("role_id", \App\Models\Role::where("name", "Filiale")->first()->id)
			->where("backpack_role_id", null);

		if (!empty($values["ndg"])) {
			$usersQuery->where("ndg", $values["ndg"]);
		}

		if (!empty($values["ateco"])) {
			$usersQuery->where("ateco", $values["ateco"]);
		}

		if (!empty($values["filiale_id"])) {
			$usersQuery->where("filiale_id", $values["filiale_id"]);
		}

		if (!empty($values["provincia"])) {
			$usersQuery->where("provincia", $values["provincia"]);
		}

		$users = $usersQuery->get();
		$data = [];

		foreach ($users as $user) {
			$filiale = $user->filiale;
			$delibere = $user->delibere;
			$data[] = [
				"ndg" => $user->ndg,
				"ragione_sociale" => $user->ragione_sociale,
				"imk" => $user->segmento_appartenenza ? rtrim(rtrim($user->segmento_appartenenza, "0"), ".") : null,
				"codice_ateco" => preg_match('/^0([1-9])(\..*)?$/', $user->ateco, $matches)
					? $matches[1] . (isset($matches[2]) ? $matches[2] : "")
					: $user->ateco,
				"area_territoriale" => $user->provincia,
				"filiale" => $filiale?->descrizione ?? null,
				"plafond_prevalutato" => $delibere->max("max_atp") ?? 0,
			];
		}

		// Create export directory if it doesn't exist
		$exportDir = public_path("storage/export-csv");
		if (!file_exists($exportDir)) {
			mkdir($exportDir, 0755, true);
		}

		// Clean old files - keep only the most recent one
		$files = glob($exportDir . "/target_potenziale_*.csv");
		if (count($files) > 0) {
			// Sort by modification time (newest first)
			usort($files, function ($a, $b) {
				return filemtime($b) - filemtime($a);
			});

			// Delete all files except the newest one
			$filesToDelete = array_slice($files, 1);
			foreach ($filesToDelete as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

		// Generate filename with timestamp
		$filename = "target_potenziale_" . date("Y-m-d_H-i-s") . ".csv";
		$filepath = $exportDir . "/" . $filename;

		// Create CSV file
		$file = fopen($filepath, "w");

		// Add BOM for UTF-8 compatibility
		fwrite($file, "\xEF\xBB\xBF");

		// Add CSV headers
		$headers = ["NDG", "Ragione Sociale", "IMK", "Codice ATECO", "Area Territoriale", "Filiale", "Plafond Prevalutato"];
		fputcsv($file, $headers);

		// Add data rows
		foreach ($data as $row) {
			fputcsv($file, [
				$row["ndg"],
				$row["ragione_sociale"],
				$row["imk"],
				$row["codice_ateco"],
				$row["area_territoriale"],
				$row["filiale"],
				$row["plafond_prevalutato"],
			]);
		}

		fclose($file);

		// Return the file path
		return response()->json(
			[
				"success" => true,
				"file_path" => "/storage/export-csv/" . $filename,
				"filename" => $filename,
				"total_records" => count($data),
			],
			201
		);
	}

	/**
	 * Export CSV storico erogazioni
	 *
	 * Crea un file CSV con lo storico delle erogazioni filtrate.
	 */
	public function exportStoricoErogazioni($request)
	{
		// Get data from JSON if available, otherwise from request
		$requestData = $request->json()->all() ?: $request->all();

		$values = $requestData;

		// Validate the data
		$validator = Validator::make($values, [
			"ndg" => "sometimes|string",
			"status" => "sometimes|string|in:sopra_forecast,sotto_forecast,sotto_baseline",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$values = $validator->validated();

		$statusFilter = $values["status"] ?? null;

		$user_id = !empty($values["ndg"]) ? optional(User::where("ndg", $values["ndg"])->first())->id : null;
		if (!empty($values["ndg"]) && empty($user_id)) {
			throw new NotFoundHttpException("NDG non trovato");
		}

		$tiraggiQuery = Tiraggio::with(["delibera", "user"])
			->where("status", "paid")
			->whereNotNull("importo_deliberato");

		if (!empty($user_id)) {
			$tiraggiQuery->where("user_id", $user_id);
		}

		$tiraggi = $tiraggiQuery->get();
		$data = [];

		foreach ($tiraggi as $tiraggio) {
			$user = $tiraggio->user;
			$margine = $tiraggio->costo_totale - $tiraggio->importo_deliberato;
			$forecast = $tiraggio->getForecastArray();
			$baseline = $tiraggio->getBaselineArray();
			$forecast_tot = array_sum($forecast);
			$baseline_tot = array_sum($baseline);
			$esito =
				$tiraggio->trattenuta_totale >= $forecast_tot
					? "sopra_forecast"
					: ($tiraggio->trattenuta_totale >= $baseline_tot
						? "sotto_forecast"
						: "sotto_baseline");

			$item = [
				"ndg" => $user->ndg,
				"ragione_sociale" => $user->ragione_sociale,
				"rating" => $user->rating,
				"importo_erogato" => $tiraggio->importo_deliberato,
				"data_conclusione_rimborso" => $tiraggio->data_fine,
				"margine_effettivo_generato" => $margine,
				"esito" => $esito,
			];

			if (!$statusFilter || $esito === $statusFilter) {
				$data[] = $item;
			}
		}

		// Create export directory if it doesn't exist
		$exportDir = public_path("storage/export-csv");
		if (!file_exists($exportDir)) {
			mkdir($exportDir, 0755, true);
		}

		// Clean old files - keep only the most recent one
		$files = glob($exportDir . "/storico_erogazioni_*.csv");
		if (count($files) > 0) {
			// Sort by modification time (newest first)
			usort($files, function ($a, $b) {
				return filemtime($b) - filemtime($a);
			});

			// Delete all files except the newest one
			$filesToDelete = array_slice($files, 1);
			foreach ($filesToDelete as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

		// Generate filename with timestamp
		$filename = "storico_erogazioni_" . date("Y-m-d_H-i-s") . ".csv";
		$filepath = $exportDir . "/" . $filename;

		// Create CSV file
		$file = fopen($filepath, "w");

		// Add BOM for UTF-8 compatibility
		fwrite($file, "\xEF\xBB\xBF");

		// Add CSV headers
		$headers = [
			"NDG",
			"Ragione Sociale",
			"Rating",
			"Importo Erogato",
			"Data Conclusione Rimborso",
			"Margine Effettivo Generato",
			"Esito",
		];
		fputcsv($file, $headers);

		// Add data rows
		foreach ($data as $row) {
			fputcsv($file, [
				$row["ndg"],
				$row["ragione_sociale"],
				$row["rating"],
				$row["importo_erogato"],
				$row["data_conclusione_rimborso"],
				$row["margine_effettivo_generato"],
				$row["esito"],
			]);
		}

		fclose($file);

		// Return the file path
		return response()->json(
			[
				"success" => true,
				"file_path" => "/storage/export-csv/" . $filename,
				"filename" => $filename,
				"total_records" => count($data),
			],
			201
		);
	}

	/**
	 * Export CSV richieste ATP
	 *
	 * Crea un file CSV con le richieste ATP filtrate per NDG e stato.
	 */
	public function exportRichiesteAtp($request)
	{
		// Get data from JSON if available, otherwise from request
		$requestData = $request->json()->all() ?: $request->all();

		$values = $requestData;

		// Validate the data
		$validator = Validator::make($values, [
			"ndg" => "sometimes|string",
			"status" => "sometimes|string|in:in attesa,accettata,estinta,requested,accepted,paid",
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}

		$values = $validator->validated();

		// Map Italian status to internal status
		$statusMapping = [
			"in attesa" => "requested",
			"accettata" => "accepted",
			"estinta" => "paid",
		];

		$statusFilter = null;
		if (!empty($values["status"])) {
			$statusFilter = $statusMapping[$values["status"]] ?? $values["status"];
		}

		$user_id = !empty($values["ndg"]) ? optional(User::where("ndg", $values["ndg"])->first())->id : null;
		if (!empty($values["ndg"]) && empty($user_id)) {
			throw new NotFoundHttpException("NDG non trovato");
		}

		// Get results with relationships
		$tiraggiQuery = Tiraggio::with("user")->whereNotIn("status", ["open", "rejected"]);

		if (!empty($statusFilter)) {
			$tiraggiQuery->where("status", $statusFilter);
		}

		if (!empty($user_id)) {
			$tiraggiQuery->where("user_id", $user_id);
		}

		$tiraggi = $tiraggiQuery->get();

		$data = [];
		foreach ($tiraggi as $tiraggio) {
			$user = $tiraggio->user;
			$data[] = [
				"ndg" => $user->ndg,
				"ragione_sociale" => $user->ragione_sociale,
				"imk" => $user->segmento_appartenenza ? rtrim(rtrim($user->segmento_appartenenza, "0"), ".") : null,
				"ateco" => preg_match('/^0([1-9])(\..*)?$/', $user->ateco, $matches)
					? $matches[1] . (isset($matches[2]) ? $matches[2] : "")
					: $user->ateco,
				"data_richiesta" => $tiraggio->data_richiesta,
				"importo_richiesto" => $tiraggio->importo_richiesto,
				"quota_storno" => $tiraggio->percentuale,
				"stato_richiesta" => $tiraggio->status,
			];
		}

		// Create export directory if it doesn't exist
		$exportDir = public_path("storage/export-csv");
		if (!file_exists($exportDir)) {
			mkdir($exportDir, 0755, true);
		}

		// Clean old files - keep only the most recent one
		$files = glob($exportDir . "/richieste_atp_*.csv");
		if (count($files) > 0) {
			// Sort by modification time (newest first)
			usort($files, function ($a, $b) {
				return filemtime($b) - filemtime($a);
			});

			// Delete all files except the newest one
			$filesToDelete = array_slice($files, 1);
			foreach ($filesToDelete as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

		// Generate filename with timestamp
		$filename = "richieste_atp_" . date("Y-m-d_H-i-s") . ".csv";
		$filepath = $exportDir . "/" . $filename;

		// Create CSV file
		$file = fopen($filepath, "w");

		// Add BOM for UTF-8 compatibility
		fwrite($file, "\xEF\xBB\xBF");

		// Add CSV headers
		$headers = [
			"NDG",
			"Ragione Sociale",
			"IMK",
			"ATECO",
			"Data Richiesta",
			"Importo Richiesto",
			"Quota Storno",
			"Stato Richiesta",
		];
		fputcsv($file, $headers);

		// Add data rows
		foreach ($data as $row) {
			fputcsv($file, [
				$row["ndg"],
				$row["ragione_sociale"],
				$row["imk"],
				$row["ateco"],
				$row["data_richiesta"],
				$row["importo_richiesto"],
				$row["quota_storno"],
				$row["stato_richiesta"],
			]);
		}

		fclose($file);

		// Return the file path
		return response()->json(
			[
				"success" => true,
				"file_path" => "/storage/export-csv/" . $filename,
				"filename" => $filename,
				"total_records" => count($data),
			],
			201
		);
	}
}

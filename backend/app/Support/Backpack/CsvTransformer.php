<?php

namespace App\Support\Backpack;

/**
 * CSV Transformer for Job Imports.
 *
 * Model-specific transformations applied to every row of the folder-based
 * imports (ImportJobService, ProcessImportChunk), after the column mapping
 * has produced $rowData and before the row is written. Kept separate from
 * the seeder transformations to allow different filtering logic.
 *
 * The template ships with no model-specific rule: rows are imported as
 * mapped. To add one, add a case to transform() and a private method that
 * returns the adjusted $rowData — or [] to skip the row. For example:
 *
 *     case 'User':
 *         return self::transformUser($rowData, $row, $headers);
 *
 *     private static function transformUser(array $rowData, array $row, array $headers): array
 *     {
 *         $rowData['email'] = strtolower(CsvHelper::getValueByHeader($row, $headers, 'EMAIL'));
 *
 *         return $rowData;
 *     }
 */
class CsvTransformer
{
<<<<<<< HEAD
	private const USER_CSV_HEADERS = ["ndg", "abi", "filiale", "rating", "provincia", "codice_ateco"];

	private const STORICO_CSV_HEADERS = ["ndg", "year", "month", "amount", "transazioni"];

	private const PREVISIONALE_CSV_HEADERS = ["ndg", "year", "month", "amount", "transazioni"];

	private const DELIBERE_CSV_HEADERS = ["ndg", "abi", "max_atp_consigliato", "max_atp_effettivo", "tan", "taeg", "status"];

	private const TRATTENUTA_CSV_HEADERS = ["NDG", "DATA CONTABILE", "IMPORTO"];

	/**
	 * Extract values from CSV row based on header list
	 *
	 * @param  array  $row  Raw CSV row
	 * @param  array  $headers  CSV headers from file
	 * @param  array  $expectedHeaders  List of expected header names
	 * @return array Associative array of header => value
	 */
	private static function extractValues(array $row, array $headers, array $expectedHeaders): array
	{
		$values = [];
		foreach ($expectedHeaders as $header) {
			$values[$header] = CsvHelper::getValueByHeader($row, $headers, $header);
		}

		return $values;
	}

	// =========================================================================
	// MAIN TRANSFORM DISPATCHER
	// =========================================================================

	/**
	 * Apply model-specific transformations for job import
	 *
	 * @param  array  $rowData  Current row data
	 * @param  array  $row  Raw CSV row
	 * @param  array  $columnMapping  Column mapping configuration
	 * @param  array  $headers  CSV headers
	 * @param  string  $modelClass  Model class name
	 * @param  array  $preloadedData  Preloaded data for batch lookups (optional)
	 * @return array Transformed row data
	 */
	public static function transform(
		array $rowData,
		array $row,
		array $columnMapping,
		array $headers,
		string $modelClass,
		array $preloadedData = []
	): array {
		$className = class_basename($modelClass);

		switch ($className) {
			case "User":
				return self::transformUser($rowData, $row, $columnMapping ?? [], $headers);
			case "Tiraggio":
				return self::transformTiraggio($rowData, $row, $columnMapping ?? [], $headers);
			case "Trattenuta":
				return self::transformTrattenuta($rowData, $row, $columnMapping ?? [], $headers, $preloadedData);
			default:
				return $rowData;
		}
	}

	// =========================================================================
	// USER TRANSFORMATION
	// =========================================================================

	/**
	 * Transform User data for job import
	 * Expected CSV headers: ndg, abi, filiale, rating, provincia, codice_ateco
	 * Password handling is delegated to User::boot() (auto-hash, auto-generate)
	 */
	private static function transformUser(array $rowData, array $row, array $columnMapping, array $headers): array
	{
		// Extract all values using header list
		$csv = self::extractValues($row, $headers, self::USER_CSV_HEADERS);

		// Validate required fields
		if (empty($csv["ndg"]) || empty($csv["abi"])) {
			Log::channel("import")->warning("User import: missing ndg or abi");

			return [];
		}

		// Build user_id from abi-ndg
		$rowData["user_id"] = $csv["abi"] . "-" . $csv["ndg"];
		$rowData["banca_abi"] = $csv["abi"];

		// Handle filiale_id lookup (filiale_id is the PK)
		if (!empty($csv["filiale"])) {
			$filiale = Filiale::where("filiale_id", $csv["filiale"])->first();
			if ($filiale) {
				$rowData["filiale_id"] = $filiale->filiale_id;
			} else {
				Log::channel("import")->warning("User import: filiale_id {$csv["filiale"]} not found");
			}
		}

		// Map optional fields
		if (!empty($csv["rating"])) {
			$rowData["rating"] = $csv["rating"];
		}
		if (!empty($csv["provincia"])) {
			$rowData["provincia"] = $csv["provincia"];
		}
		if (!empty($csv["codice_ateco"])) {
			$rowData["ateco"] = $csv["codice_ateco"];
		}

		return $rowData;
	}

	// =========================================================================
	// TIRAGGIO TRANSFORMATION (Complex - handles multiple CSV types)
	// =========================================================================

	/**
	 * Transform Tiraggio data for job import
	 * Handles two CSV types: positive outcomes and negative outcomes
	 */
	private static function transformTiraggio(array $rowData, array $row, array $columnMapping, array $headers): array
	{
		// Check for USER_ID field to determine CSV type
		$statoRapportoFido = CsvHelper::getValueByHeader($row, $headers, "STATO_RAPP_FIDO");
		$fidoEstinto = !empty($statoRapportoFido) && $statoRapportoFido === "estinto";
		$fidoInEssere = !empty($statoRapportoFido) && $statoRapportoFido === "in essere";

		$statoRapportoAtp = CsvHelper::getValueByHeader($row, $headers, "STATO_RAPP_ATP");
		$atpEstinto = !empty($statoRapportoAtp) && $statoRapportoAtp === "estinto";
		$atpInEssere = !empty($statoRapportoAtp) && $statoRapportoAtp === "in essere";

		$ragioneSociale = CsvHelper::getValueByHeader($row, $headers, "INTESTAZIONE");

		$importoFidoOperativo = CsvHelper::getValueByHeader($row, $headers, "IMP_FIDO_OPER_SETT_FG");
		// Loose on purpose: the CSV hands back the string '0' (or ''), never the
		// int, so the strict comparison that used to be here was always false
		// and the "fido estinto with zero balance" branch could never fire.
		$fidoZero =
			$importoFidoOperativo !== null &&
			$importoFidoOperativo !== "" &&
			(float) CsvHelper::simpleDecimal($importoFidoOperativo) === 0.0;

		// The V3 file carries NDG, not USER_ID: keying off USER_ID made every
		// row bail out here, so nothing in the file was ever processed.
		$ndgRaw =
			CsvHelper::getValueByHeader($row, $headers, "NDG") ?: CsvHelper::getValueByHeader($row, $headers, "USER_ID");

		$user_id = CsvHelper::resolveUserIdFromNdg($ndgRaw);

		if ($user_id === null) {
			CsvHelper::incrementCustom("missing_user_ids");
			Log::channel("tiraggio")->warning(
				"NDG not found: {$ndgRaw} (tried also " . config("import.abi_prefix") . "-{$ndgRaw})"
			);

			return [];
		}

		$user = User::where("user_id", $user_id)->first();

		if (!empty($ragioneSociale) && empty($user->ragione_sociale)) {
			User::where("user_id", $user_id)->update(["ragione_sociale" => $ragioneSociale]);
			Log::channel("tiraggio")->info(
				"Ragione Sociale updated for USER_ID: {$user_id} - Ragione Sociale: " . $ragioneSociale
			);
		}

		$rapportoAtp = CsvHelper::getValueByHeader($row, $headers, "RAPP_UTI_ATP");
		if (!empty($rapportoAtp)) {
			if (str_starts_with($rapportoAtp, "0")) {
				$rapportoAtp = substr($rapportoAtp, 1);
			}
			if (str_contains($rapportoAtp, "/")) {
				$rapportoAtp = str_replace("/", "", $rapportoAtp);
			}
		}

		if (($fidoEstinto && $atpEstinto) || ($fidoEstinto && $fidoZero)) {
			// Check if user has tiraggio in "requested" status
			$tiraggio = Tiraggio::where("numero_pratica", $rapportoAtp)->where("user_id", $user_id)->first();
			if (empty($tiraggio)) {
				return [];
			}
			$delibera = Delibera::where("delibera_id", $tiraggio->delibera_id)->first();
			if (empty($delibera)) {
				return [];
			}
			$dataEstinzione = CsvHelper::getValueByHeader($row, $headers, "DT_EST_RAPP_ATP");
			if (!empty($dataEstinzione)) {
				$dataEstinzione = Carbon::createFromFormat("d/m/Y", $dataEstinzione)->format("Y-m-d");
			}

			return self::handleEstintoOutcome(
				$rowData,
				$row,
				$headers,
				$user_id,
				$tiraggio,
				$delibera,
				$dataEstinzione,
				$rapportoAtp
			);
		}

		if ($fidoInEssere && $atpInEssere) {
			Log::channel("tiraggio")->warning("POSITIVE OUTCOME: check if {$rapportoAtp} is already in the database...");
			$tiraggio = Tiraggio::where("numero_pratica", $rapportoAtp)->first();
			if (!empty($tiraggio)) {
				Log::channel("tiraggio")->warning("POSITIVE OUTCOME: skipped for {$rapportoAtp}: tiraggio already exists");

				return [];
			}
			// Check if user has tiraggio in "requested" status
			$tiraggio = Tiraggio::where("user_id", $user_id)->where("status", "requested")->first();
			$delibera = Delibera::where("user_id", $user_id)->where("status", "open")->first();
			if (empty($tiraggio)) {
				return [];
			}
			if (empty($delibera)) {
				return [];
			}
			// Determine CSV type based on available fields
			$dataOperativita = CsvHelper::getValueByHeader($row, $headers, "DT_OPERATIVITA");
			if (!empty($dataOperativita)) {
				$dataOperativita = Carbon::createFromFormat("d/m/Y", $dataOperativita)->format("Y-m-d");
			}
			$dataChiusura = CsvHelper::getValueByHeader($row, $headers, "DT_SCAD_LINEA");
			if (!empty($dataChiusura)) {
				$dataChiusura = Carbon::createFromFormat("d/m/Y", $dataChiusura)->format("Y-m-d");
			}
			$importoDeliberatoRaw = CsvHelper::getValueByHeader($row, $headers, "IMP_FIDO_INI");
			$importoDeliberato = $importoDeliberatoRaw !== null ? (int) $importoDeliberatoRaw : null;

			if (empty($importoDeliberato) || empty($dataOperativita) || empty($dataChiusura) || empty($rapportoAtp)) {
				return [];
			}

			return self::handlePositiveOutcome(
				$rowData,
				$row,
				$headers,
				$user_id,
				$tiraggio,
				$delibera,
				$dataOperativita,
				$dataChiusura,
				$rapportoAtp,
				$importoDeliberato
			);
		}

		$esitoDelibera = CsvHelper::getValueByHeader($row, $headers, "Esito Delibera");
		if (!empty($esitoDelibera) && strtoupper($esitoDelibera) === "NEG") {
			Log::channel("tiraggio")->warning("NEGATIVE OUTCOME");
			$tiraggio = Tiraggio::where("user_id", $user_id)->where("status", "requested")->first();
			if (empty($tiraggio)) {
				return [];
			}

			return self::handleNegativeOutcome($rowData, $row, $headers, $user_id, $tiraggio);
		}

		return [];
	}

	/**
	 * Handle estinto outcome CSV (esiti estinti)
	 */
	private static function handleEstintoOutcome(
		array $rowData,
		array $row,
		array $headers,
		string $user_id,
		$tiraggio,
		$delibera,
		string $dataEstinzione,
		string $rapportoAtp
	): array {
		Tiraggio::where("tiraggio_id", $tiraggio->tiraggio_id)->update([
			"status" => "paid",
			"data_fine" => $dataEstinzione,
		]);

		Log::channel("tiraggio")->info(
			"ESTINTO OUTCOME: Tiraggio {$rapportoAtp} (ID: {$tiraggio->tiraggio_id}) updated - status: {$tiraggio->status} → paid, data_fine: {$dataEstinzione}"
		);

		// Same rule as TiraggioObserver: the slot is skipped only when one exists,
		// whatever else the user holds (a multi-tiraggio user keeps accepted ones).
		if (Tiraggio::where("user_id", $user_id)->where("status", "open")->exists()) {
			Log::channel("tiraggio")->warning(
				"ESTINTO OUTCOME: Tiraggio open for USER_ID: {$user_id} already exists. Skipping processing."
			);
		} else {
			Tiraggio::create([
				"user_id" => $user_id,
				"status" => "open",
				"delibera_id" => $delibera->delibera_id,
				"tan" => $delibera->tan,
				"taeg" => $delibera->taeg,
			]);
			Log::channel("tiraggio")->info("ESTINTO OUTCOME: Tiraggio open for USER_ID: {$user_id} created.");
		}

		return $rowData;
	}

	/**
	 * Handle positive outcome CSV (esiti positivi)
	 */
	private static function handlePositiveOutcome(
		array $rowData,
		array $row,
		array $headers,
		$user_id,
		$tiraggio,
		$delibera,
		string $dataOperativita,
		string $dataChiusura,
		string $rapportoAtp,
		int $importoDeliberato
	): array {
		if (empty($tiraggio) || empty($delibera)) {
			return [];
		}

		$taeg = $tiraggio->taeg ?? $delibera->taeg;

		$dataOperativitaCarbon = Carbon::parse($dataOperativita);
		$giorniPrimoMese = $dataOperativitaCarbon->daysInMonth;
		$remainingDays = PianoRientroCalculator::giorniResiduiPrimoMese($dataOperativitaCarbon);
		$currentYear = $dataOperativitaCarbon->year;
		$currentMonth = $dataOperativitaCarbon->month;
		$previsionali_12 = Previsionale::where("user_id", $tiraggio->user_id)
			->orderByRaw('STR_TO_DATE(CONCAT(anno, "-", LPAD(mese, 2, "0"), "-01"), "%Y-%m-%d")')
			->where(function ($query) use ($currentYear, $currentMonth) {
				$query
					->where("mese", ">=", $currentMonth)
					->where("anno", "=", $currentYear)
					->orWhere(function ($q) use ($currentYear) {
						$q->where("anno", ">", $currentYear);
					});
			})
			->limit(12)
			->get();

		$previsionali_13 = Previsionale::where("user_id", $tiraggio->user_id)
			->orderByRaw('STR_TO_DATE(CONCAT(anno, "-", LPAD(mese, 2, "0"), "-01"), "%Y-%m-%d")')
			->where(function ($query) use ($currentYear, $currentMonth) {
				$query
					->where("mese", ">=", $currentMonth)
					->where("anno", "=", $currentYear)
					->orWhere(function ($q) use ($currentYear) {
						$q->where("anno", ">", $currentYear);
					});
			})
			->limit(13)
			->get();

		$importo_previsto_totale = $previsionali_12->sum("importo_transato_previsto");
		$percentuale_prevista = floatval(($importoDeliberato * 100) / $importo_previsto_totale);
		$trattenuta_baseline = 0;
		$trattenuta_forecast = 0;
		$trattenuta_baseline_totale = 0;
		$trattenuta_forecast_totale = 0;
		$mesi = 0;
		$quota_residua = [];
		$quota_residua[0] = $importoDeliberato;
		$quota_interessi = [];

		foreach ($previsionali_13 as $previsionale) {
			$trattenuta_baseline = ($previsionale->importo_transato_previsto * $percentuale_prevista) / 100;
			if ($mesi == 0) {
				$trattenuta_baseline = ($trattenuta_baseline / $giorniPrimoMese) * $remainingDays;
			}
			if ($importoDeliberato - $trattenuta_baseline_totale < $trattenuta_baseline) {
				$trattenuta_baseline = max(0, $importoDeliberato - $trattenuta_baseline_totale);
			}
			$trattenuta_baseline_totale += $trattenuta_baseline;

			$importoResiduoForecast = $importoDeliberato - $trattenuta_forecast_totale;
			if ($importoResiduoForecast > 0) {
				$trattenuta_forecast = ($previsionale->importo_transato_previsto * $tiraggio->percentuale) / 100;
				if ($mesi == 0) {
					$trattenuta_forecast = ($trattenuta_forecast / $giorniPrimoMese) * $remainingDays;
				}
				if ($trattenuta_forecast > $importoResiduoForecast) {
					$trattenuta_forecast = $importoResiduoForecast;
				}
				$trattenuta_forecast_totale += $trattenuta_forecast;
				$mesi++;
			} else {
				$trattenuta_forecast = 0;
			}

			Proiezione::create([
				"user_id" => $user_id,
				"tiraggio_numero_pratica" => $tiraggio->numero_pratica,
				"anno" => $previsionale->anno,
				"mese" => $previsionale->mese,
				"baseline" => $trattenuta_baseline,
				"forecast" => $trattenuta_forecast,
			]);
			Log::channel("tiraggio")->info(
				"POSITIVE OUTCOME: Proiezione created for USER_ID: {$user_id} - {$previsionale->anno}/{$previsionale->mese} - baseline: {$trattenuta_baseline} - forecast: {$trattenuta_forecast}"
			);
		}

		foreach ($quota_residua as $mese => $quota) {
			$quota_interessi[$mese] = $quota * ($tiraggio->tan / 1200);
		}
		$quota_interessi_totale = array_sum($quota_interessi);

		Tiraggio::where("tiraggio_id", $tiraggio->tiraggio_id)->update([
			"status" => "accepted",
			"data_delibera" => $dataOperativita,
			"trattenuta_totale" => 0,
			"data_fine" => $dataChiusura,
			"numero_pratica" => $rapportoAtp,
			"importo_deliberato" => $importoDeliberato,
			"mesi" => $mesi,
			"quota_interessi" => $quota_interessi_totale,
			"costo_totale" => $importoDeliberato + $quota_interessi_totale,
			"delibera_id" => $delibera->delibera_id,
		]);
		Log::channel("tiraggio")->info(
			"POSITIVE OUTCOME: Tiraggio (ID: {$tiraggio->tiraggio_id}) updated - status: {$tiraggio->status} → accepted, data_delibera: {$dataOperativita}, pratica: {$rapportoAtp}, importo_deliberato: {$importoDeliberato}"
		);

		Delibera::where("delibera_id", $delibera->delibera_id)->update([
			"data_delibera" => $dataOperativita,
			"data_fine" => $dataChiusura,
			"importo_deliberato" => $importoDeliberato,
		]);
		Log::channel("tiraggio")->info(
			"POSITIVE OUTCOME: Delibera (ID: {$delibera->delibera_id}) updated - data_delibera: {$dataOperativita}, data_fine: {$dataChiusura}, importo_deliberato: {$importoDeliberato}"
		);

		return $rowData;
	}

	/**
	 * Handle negative outcome CSV (esiti negativi)
	 */
	private static function handleNegativeOutcome(array $rowData, array $row, array $headers, $user_id, $tiraggio): array
	{
		if (!$tiraggio) {
			return [];
		}

		$tiraggio->status = "rejected";
		$tiraggio->save();

		$rowData = [
			"tiraggio_id" => $tiraggio->tiraggio_id,
			"status" => "rejected",
		];

		Log::channel("tiraggio")->info(
			"TIRAGGIO JOB: Tiraggio {$tiraggio->tiraggio_id} updated - status: {$tiraggio->status} → rejected"
		);

		return $rowData;
	}

	// =========================================================================
	// TRATTENUTA TRANSFORMATION
	// =========================================================================

	/**
	 * Transform Trattenuta data for job import
	 * Uses preloaded data when available to avoid N+1 queries
	 */
	private static function transformTrattenuta(
		array $rowData,
		array $row,
		array $columnMapping,
		array $headers,
		array $preloaded = []
	): array {
		$csv = self::extractValues($row, $headers, self::TRATTENUTA_CSV_HEADERS);

		if (empty($csv["NDG"])) {
			return [];
		}

		$ndgRaw = $csv["NDG"];
		$userId = CsvHelper::resolveUserIdFromNdg($ndgRaw, $preloaded["users"] ?? []);

		if ($userId === null) {
			CsvHelper::incrementCustom("missing_user_ids");
			CsvHelper::incrementSkipped();
			Log::channel("trattenuta")->warning(
				"NDG not found: {$ndgRaw} (tried also " . config("import.abi_prefix") . "-{$ndgRaw})"
			);

			return [];
		}

		$dataContabile = null;
		if (!empty($csv["DATA CONTABILE"])) {
			$dataContabile = Carbon::createFromFormat("d/m/Y", $csv["DATA CONTABILE"])->format("Y-m-d");
		}

		if (!$dataContabile) {
			return [];
		}

		// Find tiraggio using preloaded data or DB query
		$actualTiraggio = null;

		if (!empty($preloaded["tiraggi_by_user"][$userId])) {
			// Search in preloaded tiraggi for matching date range
			foreach ($preloaded["tiraggi_by_user"][$userId] as $tiraggio) {
				if ($tiraggio["data_delibera"] <= $dataContabile && $tiraggio["data_fine"] >= $dataContabile) {
					$actualTiraggio = (object) $tiraggio;
					break;
				}
			}
		} else {
			// Fallback to DB query
			$actualTiraggio = Tiraggio::where("user_id", $userId)
				->where("data_delibera", "<=", $dataContabile)
				->where("data_fine", ">=", $dataContabile)
				->first();
		}

		if (!$actualTiraggio) {
			CsvHelper::incrementCustom("missing_tiraggi");
			CsvHelper::incrementSkipped();

			return [];
		}

		if (empty($actualTiraggio->importo_deliberato) || (float) $actualTiraggio->importo_deliberato <= 0) {
			CsvHelper::incrementCustom("zero_importo_deliberato");
			CsvHelper::incrementSkipped();
			$numeroPratica = $actualTiraggio->numero_pratica ?? "N/A";
			Log::channel("trattenuta")->warning(
				"IMPORTO DELIBERATO ZERO | Tiraggio {$actualTiraggio->tiraggio_id}|{$numeroPratica} | USER_ID {$userId} - manual handling required"
			);

			return [];
		}

		$transatoString = CsvHelper::simpleDecimal($csv["IMPORTO"]);
		$userTransato = $transatoString !== null ? (float) $transatoString : 0.0;

		// Check for duplicate: DB has UNIQUE(user_id, tiraggio_numero_pratica, data_contabile, -1/-2 sentinel)
		// Same user + tiraggio + date = already imported (regardless of amount)
		$duplicateKey = $userId . "|" . $actualTiraggio->numero_pratica . "|" . $dataContabile;

		if (!empty($preloaded["existing_trattenute"][$duplicateKey])) {
			CsvHelper::incrementCustom("duplicates");
			CsvHelper::incrementSkipped();

			return [];
		}

		// Fallback to DB query if preloaded data not available
		if (empty($preloaded["existing_trattenute"])) {
			$existingTrattenuta = Trattenuta::where("user_id", $userId)
				->where("tiraggio_numero_pratica", $actualTiraggio->numero_pratica)
				->where("data_contabile", $dataContabile)
				->whereRaw("coalesce(importo_transato, 0) not in (-1, -2)")
				->exists();

			if ($existingTrattenuta) {
				CsvHelper::incrementCustom("duplicates");
				CsvHelper::incrementSkipped();

				return [];
			}
		}

		$percentuale = (float) ($actualTiraggio->percentuale ?? 0);
		$trattenuta = ($userTransato * $percentuale) / 100;
		$trattenuta = (float) ceil($trattenuta);

		$rowData["user_id"] = $userId;
		$rowData["tiraggio_numero_pratica"] = $actualTiraggio->numero_pratica;
		$rowData["importo_transato"] = $userTransato;
		$rowData["importo_trattenuto"] = $trattenuta;
		$rowData["data_contabile"] = $dataContabile;

		return $rowData;
	}

	// =========================================================================
	// UTILITY METHODS
	// =========================================================================

	/**
	 * Update open delibera cache for a user (used by ImportJobService)
	 */
	public static function updateOpenDeliberaCache(string $userId): void
	{
		// This method can be used to invalidate/update any caching mechanism
		// Currently a no-op but kept for interface compatibility
	}
=======
    /**
     * Apply model-specific transformations for job import.
     *
     * @param  array  $rowData  Current row data (already mapped to model columns)
     * @param  array  $row  Raw CSV row
     * @param  array  $columnMapping  Column mapping configuration
     * @param  array  $headers  CSV headers
     * @param  string  $modelClass  Model class name
     * @param  array  $preloadedData  Preloaded data for batch lookups (optional)
     * @return array Transformed row data; an empty array skips the row
     */
    public static function transform(
        array $rowData,
        array $row,
        array $columnMapping,
        array $headers,
        string $modelClass,
        array $preloadedData = []
    ): array {
        return match (class_basename($modelClass)) {
            default => $rowData,
        };
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}

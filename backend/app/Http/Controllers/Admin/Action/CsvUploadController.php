<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Backpack page that uploads a CSV into a database/import/{folder} directory,
 * for the folder-based import pipeline (CheckImportFiles → ProcessImportFile).
 */
class CsvUploadController extends Controller
{
<<<<<<< HEAD
	/**
	 * Mapping delle tipologie alle directory di import
	 */
	private const TYPE_MAPPING = [
		"trattenuta" => [
			"name" => "Transato giornaliero",
			"directory" => "trattenuta",
			"description" =>
				'Ricevuto l\'importo transato giornaliero con la data contabile, viene calcolata la trattenuta relativa al tiraggio in essere nel periodo corrispondente',
			"patterns" => ["CashBerry_TransatoWLDaCC_"],
		],
		"tiraggio" => [
			"name" => "Rapporti ATP e Fidi estinti",
			"directory" => "tiraggio",
			"description" =>
				'Con un solo file vengono elaborate le richieste di tiraggio in attesa, con esito positivo, il calcolo delle trattenute totali per i tiraggi in essere e l\'estinzione dei tiraggi conclusi',
			"patterns" => ["CashBerry_RapportiFidiEstinti_E_ATPAperti_"],
		],
		"delibera" => [
			"name" => "Eleggibili",
			"directory" => "delibera",
			"description" =>
				"Vengono abilitati gli NDG (se sospesi) e vengono aggiornate le loro delibere, in particolare per quanto riguarda il plafond",
			"patterns" => [],
		],
		"delibere-negative" => [
			"name" => "Delibere Negative",
			"directory" => "tiraggio",
			"description" => "Riguarda le richieste di tiraggio da rifiutare",
			"patterns" => ["CashBerry_DelibereNegative_"],
		],
	];

	/**
	 * Rileva automaticamente la tipologia basata sul nome del file
	 */
	private function detectTypeFromFileName(string $fileName): ?string
	{
		foreach (self::TYPE_MAPPING as $key => $type) {
			foreach ($type["patterns"] as $pattern) {
				if (strpos($fileName, $pattern) === 0) {
					return $key;
				}
			}
		}
=======
    /**
     * Upload types (config/import.php 'upload_types'): each one names the
     * database/import/{directory} folder its files land in, where
     * CheckImportFiles picks them up.
     *
     * @return array<string, array{name: string, directory: string, description: string, patterns: list<string>}>
     */
    private function types(): array
    {
        return config('import.upload_types', []);
    }

    /**
     * Rileva automaticamente la tipologia basata sul nome del file
     */
    private function detectTypeFromFileName(string $fileName): ?string
    {
        foreach ($this->types() as $key => $type) {
            foreach ($type['patterns'] as $pattern) {
                if (strpos($fileName, $pattern) === 0) {
                    return $key;
                }
            }
        }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		return null;
	}

<<<<<<< HEAD
	/**
	 * Mostra la pagina di upload CSV
	 */
	public function index()
	{
		return view("admin.csv-upload.index", [
			"types" => self::TYPE_MAPPING,
		]);
	}

	/**
	 * Gestisce l'upload del file CSV
	 */
	public function upload(Request $request)
	{
		$request->validate([
			"file" => "required|file|mimes:csv,txt|max:50000", // max 50MB
			"type" => "nullable|string|in:" . implode(",", array_keys(self::TYPE_MAPPING)),
		]);
=======
    /**
     * Mostra la pagina di upload CSV
     */
    public function index()
    {
        return view('admin.csv-upload.index', [
            'types' => $this->types(),
        ]);
    }

    /**
     * Gestisce l'upload del file CSV
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:50000', // max 50MB
            'type' => 'nullable|string|in:'.implode(',', array_keys($this->types())),
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$file = $request->file("file");
		$originalFileName = $file->getClientOriginalName();

		// Rileva automaticamente la tipologia dal nome del file
		$detectedType = $this->detectTypeFromFileName($originalFileName);
		$type = $request->input("type", $detectedType);

		// Se non è stata rilevata automaticamente e non è stata selezionata manualmente
		if (!$type) {
			return back()->with(
				"error",
				"Impossibile rilevare automaticamente la tipologia. Seleziona manualmente la tipologia dal menu a tendina."
			);
		}

<<<<<<< HEAD
		if (!isset(self::TYPE_MAPPING[$type])) {
			return back()->with("error", "Tipologia non valida");
		}

		$directory = self::TYPE_MAPPING[$type]["directory"];
		$importPath = database_path("import/{$directory}");
=======
        $types = $this->types();

        if (! isset($types[$type])) {
            return back()->with('error', 'Tipologia non valida');
        }

        $directory = $types[$type]['directory'];
        $importPath = database_path("import/{$directory}");
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		// Crea la directory se non esiste (senza pending)
		if (!File::exists($importPath)) {
			File::makeDirectory($importPath, 0755, true);
		}

		// Salva il file direttamente nella directory del modello
		$fileName = $originalFileName;
		$filePath = "{$importPath}/{$fileName}";

		// Se il file esiste già, aggiungi un timestamp
		if (File::exists($filePath)) {
			$pathInfo = pathinfo($originalFileName);
			$fileName = $pathInfo["filename"] . "_" . time() . "." . ($pathInfo["extension"] ?? "csv");
			$filePath = "{$importPath}/{$fileName}";
		}

		try {
			$file->move($importPath, $fileName);

<<<<<<< HEAD
			Log::channel("import")->info(
				"File CSV caricato: {$directory}/{$fileName} (Tipologia: " . self::TYPE_MAPPING[$type]["name"] . ")"
			);
=======
            Log::channel('import')->info(
                "File CSV caricato: {$directory}/{$fileName} (Tipologia: ".$types[$type]['name'].')'
            );
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

			return back()->with("success", "File caricato con successo: {$fileName}");
		} catch (\Exception $e) {
			Log::channel("import")->error("Errore durante il caricamento del file: " . $e->getMessage());

			return back()->with("error", "Errore durante il caricamento del file: " . $e->getMessage());
		}
	}
}

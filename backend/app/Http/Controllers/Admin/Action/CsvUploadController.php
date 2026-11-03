<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CsvUploadController extends Controller
{
    /**
     * Mapping delle tipologie alle directory di import
     */
    private const TYPE_MAPPING = [
        'trattenuta' => [
            'name' => 'Transato giornaliero',
            'directory' => 'trattenuta',
            'description' => 'Ricevuto l\'importo transato giornaliero con la data contabile, viene calcolata la trattenuta relativa al tiraggio in essere nel periodo corrispondente',
            'patterns' => ['CashBerry_TransatoWLDaCC_'],
        ],
        'tiraggio' => [
            'name' => 'Rapporti ATP e Fidi estinti',
            'directory' => 'tiraggio',
            'description' => 'Con un solo file vengono elaborate le richieste di tiraggio in attesa, con esito positivo, il calcolo delle trattenute totali per i tiraggi in essere e l\'estinzione dei tiraggi conclusi',
            'patterns' => ['CashBerry_RapportiFidiEstinti_E_ATPAperti_'],
        ],
        'delibera' => [
            'name' => 'Eleggibili',
            'directory' => 'delibera',
            'description' => 'Vengono abilitati gli NDG (se sospesi) e vengono aggiornate le loro delibere, in particolare per quanto riguarda il plafond',
            'patterns' => [],
        ],
        'delibere-negative' => [
            'name' => 'Delibere Negative',
            'directory' => 'tiraggio',
            'description' => 'Riguarda le richieste di tiraggio da rifiutare',
            'patterns' => ['CashBerry_DelibereNegative_'],
        ],
    ];

    /**
     * Rileva automaticamente la tipologia basata sul nome del file
     */
    private function detectTypeFromFileName(string $fileName): ?string
    {
        foreach (self::TYPE_MAPPING as $key => $type) {
            foreach ($type['patterns'] as $pattern) {
                if (strpos($fileName, $pattern) === 0) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Mostra la pagina di upload CSV
     */
    public function index()
    {
        return view('admin.csv-upload.index', [
            'types' => self::TYPE_MAPPING,
        ]);
    }

    /**
     * Gestisce l'upload del file CSV
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:50000', // max 50MB
            'type' => 'nullable|string|in:'.implode(',', array_keys(self::TYPE_MAPPING)),
        ]);

        $file = $request->file('file');
        $originalFileName = $file->getClientOriginalName();

        // Rileva automaticamente la tipologia dal nome del file
        $detectedType = $this->detectTypeFromFileName($originalFileName);
        $type = $request->input('type', $detectedType);

        // Se non è stata rilevata automaticamente e non è stata selezionata manualmente
        if (! $type) {
            return back()->with(
                'error',
                'Impossibile rilevare automaticamente la tipologia. Seleziona manualmente la tipologia dal menu a tendina.'
            );
        }

        if (! isset(self::TYPE_MAPPING[$type])) {
            return back()->with('error', 'Tipologia non valida');
        }

        $directory = self::TYPE_MAPPING[$type]['directory'];
        $importPath = database_path("import/{$directory}");

        // Crea la directory se non esiste (senza pending)
        if (! File::exists($importPath)) {
            File::makeDirectory($importPath, 0755, true);
        }

        // Salva il file direttamente nella directory del modello
        $fileName = $originalFileName;
        $filePath = "{$importPath}/{$fileName}";

        // Se il file esiste già, aggiungi un timestamp
        if (File::exists($filePath)) {
            $pathInfo = pathinfo($originalFileName);
            $fileName = $pathInfo['filename'].'_'.time().'.'.($pathInfo['extension'] ?? 'csv');
            $filePath = "{$importPath}/{$fileName}";
        }

        try {
            $file->move($importPath, $fileName);

            Log::channel('import')->info(
                "File CSV caricato: {$directory}/{$fileName} (Tipologia: ".self::TYPE_MAPPING[$type]['name'].')'
            );

            return back()->with('success', "File caricato con successo: {$fileName}");
        } catch (\Exception $e) {
            Log::channel('import')->error('Errore durante il caricamento del file: '.$e->getMessage());

            return back()->with('error', 'Errore durante il caricamento del file: '.$e->getMessage());
        }
    }
}

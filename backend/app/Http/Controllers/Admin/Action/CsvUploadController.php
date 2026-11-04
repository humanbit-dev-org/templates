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

        return null;
    }

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

        $types = $this->types();

        if (! isset($types[$type])) {
            return back()->with('error', 'Tipologia non valida');
        }

        $directory = $types[$type]['directory'];
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
                "File CSV caricato: {$directory}/{$fileName} (Tipologia: ".$types[$type]['name'].')'
            );

            return back()->with('success', "File caricato con successo: {$fileName}");
        } catch (\Exception $e) {
            Log::channel('import')->error('Errore durante il caricamento del file: '.$e->getMessage());

            return back()->with('error', 'Errore durante il caricamento del file: '.$e->getMessage());
        }
    }
}

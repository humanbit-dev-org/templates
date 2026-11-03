<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Controllers\Controller;
use App\Services\DataCleanup\DataCleanupService;
use Illuminate\Http\Request;

class DataCleanupController extends Controller
{
    public function __construct(
        private DataCleanupService $service
    ) {}

    /**
     * Display list of available cleanup commands.
     */
    public function index()
    {
        $commands = $this->service->getCommands();

        return view('admin.data-cleanup.index', [
            'commands' => $commands,
        ]);
    }

    /**
     * Preview what a command would do (read-only).
     */
    public function preview(Request $request, string $commandId)
    {
        $command = $this->service->getCommand($commandId);

        if (! $command) {
            return redirect()->route('data-cleanup.index')->with('error', 'Comando non trovato.');
        }

        $parameters = $request->only(array_keys($command['parameters'] ?? []));

        $result = $this->service->preview($commandId, $parameters);

        return view('admin.data-cleanup.preview', [
            'commandId' => $commandId,
            'command' => $command,
            'result' => $result,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Execute a cleanup command (creates backup first).
     */
    public function execute(Request $request, string $commandId)
    {
        $command = $this->service->getCommand($commandId);

        if (! $command) {
            return redirect()->route('data-cleanup.index')->with('error', 'Comando non trovato.');
        }

        $parameters = $request->only(array_keys($command['parameters'] ?? []));

        try {
            $result = $this->service->execute($commandId, $parameters);

            if ($result['success']) {
                $backupInfo = '';
                if (! empty($result['backup_files'])) {
                    $tables = implode(', ', array_keys($result['backup_files']));
                    $backupInfo = " Backup creati per: {$tables}.";
                }

                return redirect()
                    ->route('data-cleanup.index')
                    ->with('success', $result['message'].$backupInfo.' Puoi ripristinare dalla sezione CSV Rollback.');
            } else {
                return redirect()
                    ->route('data-cleanup.index')
                    ->with('error', $result['message'] ?? 'Errore durante l\'esecuzione.');
            }
        } catch (\Exception $e) {
            return redirect()
                ->route('data-cleanup.index')
                ->with('error', 'Errore: '.$e->getMessage());
        }
    }
}

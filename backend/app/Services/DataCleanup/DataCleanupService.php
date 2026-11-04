<?php

namespace App\Services\DataCleanup;

use App\Support\Backpack\CsvBackupManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Registry and runner of the one-off data cleanup commands shown on the
 * Backpack "Data Cleanup" page (DataCleanupController).
 *
 * Every command has a read-only preview and an execution step; before the
 * execution each affected table is backed up to CSV (see CSV Rollback).
 *
 * Adding a command:
 *   1. add its metadata to getCommands(), keyed by a kebab-case id;
 *   2. add two private methods named after the id in StudlyCase:
 *      preview{Id}(array $parameters): array and execute{Id}(array $parameters): array
 *      (e.g. 'pages-hidden-orphans' → previewPagesHiddenOrphans / executePagesHiddenOrphans).
 *
 * preview*() returns ['can_execute' => bool, 'summary' => [label => count],
 * 'details' => list<string>, 'warnings' => list<string>]; execute*() returns
 * ['success' => bool, 'message' => string, 'stats' => [label => count]].
 */
class DataCleanupService
{
    /**
     * Get all registered cleanup commands metadata.
     *
     * Shape of an entry:
     *
     *     'pages-hidden-orphans' => [
     *         'title' => 'Pages - Eliminazione pagine nascoste senza traduzioni',
     *         'description' => 'Elimina le pagine con visible = 0 e nessuna traduzione collegata.',
     *         'icon' => 'la la-broom',
     *         'affected_tables' => ['pages'],
     *         'has_parameters' => false,
     *         'parameters' => null, // or ['field' => ['label' => ..., 'type' => 'date|text', 'required' => bool]]
     *     ],
     *
     * @return array<string, array{title: string, description: string, icon: string, affected_tables: list<string>, has_parameters: bool, parameters: array|null}>
     */
    public function getCommands(): array
    {
        return [];
    }

    /**
     * Get a single command metadata.
     */
    public function getCommand(string $id): ?array
    {
        return $this->getCommands()[$id] ?? null;
    }

    /**
     * Get affected tables for a command.
     *
     * @return list<string>
     */
    public function getAffectedTables(string $commandId): array
    {
        $command = $this->getCommand($commandId);

        return $command['affected_tables'] ?? [];
    }

    /**
     * Run preview (read-only) for a command.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{can_execute: bool, summary: array<string, int>, details: list<string>, warnings: list<string>}
     */
    public function preview(string $commandId, array $parameters = []): array
    {
        $method = $this->handlerMethod('preview', $commandId);

        if ($method === null) {
            return ['can_execute' => false, 'summary' => [], 'details' => ['Comando non trovato'], 'warnings' => []];
        }

        return $this->{$method}($parameters);
    }

    /**
     * Execute a command (creates backup first).
     *
     * @param  array<string, mixed>  $parameters
     * @return array{success: bool, message: string, backup_files: array<string, string>, stats: array<string, int>}
     */
    public function execute(string $commandId, array $parameters = []): array
    {
        $method = $this->handlerMethod('execute', $commandId);

        if ($method === null) {
            return ['success' => false, 'message' => 'Comando non trovato', 'backup_files' => [], 'stats' => []];
        }

        // Backup every affected table before touching it, so the run can be
        // undone from the CSV Rollback page.
        $backupFiles = [];

        foreach ($this->getAffectedTables($commandId) as $table) {
            if (Schema::hasTable($table)) {
                $backupFile = CsvBackupManager::createBackup($table, 'backend', true);
                if ($backupFile) {
                    $backupFiles[$table] = $backupFile;
                }
            }
        }

        $result = $this->{$method}($parameters);
        $result['backup_files'] = $backupFiles;

        return $result;
    }

    /**
     * Name of the preview/execute method of a registered command, or null
     * when the command is unknown or its method is missing.
     *
     * @param  'preview'|'execute'  $step
     */
    private function handlerMethod(string $step, string $commandId): ?string
    {
        if ($this->getCommand($commandId) === null) {
            return null;
        }

        $method = $step.Str::studly($commandId);

        return method_exists($this, $method) ? $method : null;
    }
}

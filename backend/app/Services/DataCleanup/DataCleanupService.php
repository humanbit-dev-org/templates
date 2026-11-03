<?php

namespace App\Services\DataCleanup;

use App\Support\Backpack\CsvBackupManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataCleanupService
{
    /**
     * Get all registered cleanup commands metadata.
     *
     * @return array<string, array{title: string, description: string, icon: string, affected_tables: list<string>, has_parameters: bool, parameters: array|null}>
     */
    public function getCommands(): array
    {
        return [
            'storici-previsionali-zero-padding' => [
                'title' => 'Storici & Previsionali - Rimozione doppioni con zero-padding',
                'description' => 'Elimina i record con mese con zero iniziale (es. 05) mantenendo il corrispondente senza zero (es. 5), su storici e previsionali.',
                'icon' => 'la la-clone',
                'affected_tables' => ['storici', 'previsionali'],
                'has_parameters' => false,
                'parameters' => null,
            ],
            'delibere-doppioni-open' => [
                'title' => 'Delibere - Rimozione doppioni status open per utente',
                'description' => 'Mantiene la delibera open più recente per utente. Riassegna i tiraggi collegati prima di eliminare.',
                'icon' => 'la la-copy',
                'affected_tables' => ['delibere', 'tiraggi'],
                'has_parameters' => false,
                'parameters' => null,
            ],
            'tiraggi-doppioni-open-strict' => [
                'title' => 'Tiraggi - Rimozione doppioni open (campi identici)',
                'description' => 'Mantiene il tiraggio open più recente per utente. Elimina solo se tutti i campi significativi sono identici (trattenuta_totale NULL e 0 sono uguali; a parità di delibera tiene quello con la trattenuta valorizzata). Con delibera diversa tiene il tiraggio della delibera open ed elimina quello della close; negli altri casi skippa.',
                'icon' => 'la la-filter',
                'affected_tables' => ['tiraggi'],
                'has_parameters' => false,
                'parameters' => null,
            ],
            'tiraggi-requested-old' => [
                'title' => 'Tiraggi - Eliminazione requested fino a data',
                'description' => 'Elimina tutti i tiraggi in stato requested con data_richiesta fino alla data specificata.',
                'icon' => 'la la-calendar-times',
                'affected_tables' => ['tiraggi'],
                'has_parameters' => true,
                'parameters' => [
                    'data_fine' => ['label' => 'Data fine (YYYY-MM-DD)', 'type' => 'date', 'required' => true],
                ],
            ],
            'tiraggi-doppioni-open-loose' => [
                'title' => 'Tiraggi - Eliminazione open più vecchio (senza confronto campi)',
                'description' => 'Mantiene il tiraggio open più recente per utente, eliminando tutti gli altri senza vincoli di uguaglianza.',
                'icon' => 'la la-broom',
                'affected_tables' => ['tiraggi'],
                'has_parameters' => false,
                'parameters' => null,
            ],
            'storici-previsionali-delete-by-month' => [
                'title' => 'Storici & Previsionali - Eliminazione record per anno e mese',
                'description' => 'Elimina tutti i record di storici e previsionali per un anno e mese specifici.',
                'icon' => 'la la-calendar-minus',
                'affected_tables' => ['storici', 'previsionali'],
                'has_parameters' => true,
                'parameters' => [
                    'anno' => ['label' => 'Anno', 'type' => 'text', 'required' => true],
                    'mese' => ['label' => 'Mese', 'type' => 'text', 'required' => true],
                ],
            ],
            'delibere-close-orphaned' => [
                'title' => 'Delibere - Eliminazione close senza tiraggi collegati',
                'description' => 'Trova le delibere in stato close senza nessun tiraggio collegato e le elimina.',
                'icon' => 'la la-unlink',
                'affected_tables' => ['delibere'],
                'has_parameters' => false,
                'parameters' => null,
            ],
            'tiraggi-doppioni-open-no-delibera' => [
                'title' => 'Tiraggi - Eliminazione doppioni open senza delibera_id',
                'description' => 'Per utenti con 2+ tiraggi open, elimina quelli senza delibera_id mantenendo quello collegato.',
                'icon' => 'la la-link-slash',
                'affected_tables' => ['tiraggi'],
                'has_parameters' => false,
                'parameters' => null,
            ],
        ];
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
        return match ($commandId) {
            'storici-previsionali-zero-padding' => $this->previewStoriciPrevisionaliZeroPadding(),
            'delibere-doppioni-open' => $this->previewDelibereDoppioniOpen(),
            'tiraggi-doppioni-open-strict' => $this->previewTiraggiDoppioniOpenStrict(),
            'tiraggi-requested-old' => $this->previewTiraggiRequestedOld($parameters),
            'tiraggi-doppioni-open-loose' => $this->previewTiraggiDoppioniOpenLoose(),
            'storici-previsionali-delete-by-month' => $this->previewStoriciPrevisionaliDeleteByMonth($parameters),
            'delibere-close-orphaned' => $this->previewDelibereCloseOrphaned(),
            'tiraggi-doppioni-open-no-delibera' => $this->previewTiraggiDoppioniOpenNoDelibera(),
            default => ['can_execute' => false, 'summary' => [], 'details' => ['Comando non trovato'], 'warnings' => []],
        };
    }

    /**
     * Execute a command (creates backup first).
     *
     * @param  array<string, mixed>  $parameters
     * @return array{success: bool, message: string, backup_files: array<string, string>, stats: array<string, int>}
     */
    public function execute(string $commandId, array $parameters = []): array
    {
        $affectedTables = $this->getAffectedTables($commandId);
        $backupFiles = [];

        foreach ($affectedTables as $table) {
            if (Schema::hasTable($table)) {
                $backupFile = CsvBackupManager::createBackup($table, 'backend', true);
                if ($backupFile) {
                    $backupFiles[$table] = $backupFile;
                }
            }
        }

        $result = match ($commandId) {
            'storici-previsionali-zero-padding' => $this->executeStoriciPrevisionaliZeroPadding(),
            'delibere-doppioni-open' => $this->executeDelibereDoppioniOpen(),
            'tiraggi-doppioni-open-strict' => $this->executeTiraggiDoppioniOpenStrict(),
            'tiraggi-requested-old' => $this->executeTiraggiRequestedOld($parameters),
            'tiraggi-doppioni-open-loose' => $this->executeTiraggiDoppioniOpenLoose(),
            'storici-previsionali-delete-by-month' => $this->executeStoriciPrevisionaliDeleteByMonth($parameters),
            'delibere-close-orphaned' => $this->executeDelibereCloseOrphaned(),
            'tiraggi-doppioni-open-no-delibera' => $this->executeTiraggiDoppioniOpenNoDelibera(),
            default => ['success' => false, 'message' => 'Comando non trovato', 'stats' => []],
        };

        $result['backup_files'] = $backupFiles;

        return $result;
    }

    // --- Storici & Previsionali - Zero padding ---

    /**
     * Tables to check for zero-padded mese duplicates.
     * Each entry: [table => primaryKeyColumn]
     */
    private const ZERO_PADDING_TABLES = [
        'storici' => 'storico_id',
        'previsionali' => 'previsionale_id',
    ];

    private function previewStoriciPrevisionaliZeroPadding(): array
    {
        $details = [];
        $totalToDelete = 0;
        $totalWithZero = 0;
        $totalNoTwin = 0;

        foreach (self::ZERO_PADDING_TABLES as $table => $pk) {
            if (! Schema::hasTable($table)) {
                $details[] = "SKIP: Tabella \"{$table}\" non trovata.";

                continue;
            }

            // Find records with zero-padded mese (e.g. '05', '08')
            $withZero = DB::table($table)
                ->where('mese', 'REGEXP', '^0[1-9]$')
                ->get();

            $details[] = "=== {$table}: {$withZero->count()} record con zero-padding ===";
            $totalWithZero += $withZero->count();

            foreach ($withZero as $record) {
                $meseWithoutZero = ltrim($record->mese, '0');

                // Look for a twin: same user_id, anno, and mese without zero
                $twin = DB::table($table)
                    ->where('user_id', $record->user_id)
                    ->where('anno', $record->anno)
                    ->where('mese', $meseWithoutZero)
                    ->first();

                if ($twin) {
                    $details[] = "  DELETE {$table} {$pk}={$record->{$pk}} user={$record->user_id} anno={$record->anno} mese={$record->mese} | KEEP {$pk}={$twin->{$pk}} mese={$twin->mese}";
                    $totalToDelete++;
                } else {
                    $totalNoTwin++;
                    $details[] = "  NO TWIN {$table} {$pk}={$record->{$pk}} user={$record->user_id} anno={$record->anno} mese={$record->mese} | atteso mese={$meseWithoutZero}";
                }
            }
        }

        return [
            'can_execute' => $totalToDelete > 0,
            'summary' => [
                'record_con_zero' => $totalWithZero,
                'da_eliminare' => $totalToDelete,
                'senza_doppione' => $totalNoTwin,
            ],
            'details' => $details,
            'warnings' => [],
        ];
    }

    private function executeStoriciPrevisionaliZeroPadding(): array
    {
        $deleted = 0;

        foreach (self::ZERO_PADDING_TABLES as $table => $pk) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $withZero = DB::table($table)
                ->where('mese', 'REGEXP', '^0[1-9]$')
                ->get();

            foreach ($withZero as $record) {
                $meseWithoutZero = ltrim($record->mese, '0');

                $twin = DB::table($table)
                    ->where('user_id', $record->user_id)
                    ->where('anno', $record->anno)
                    ->where('mese', $meseWithoutZero)
                    ->first();

                if ($twin) {
                    DB::table($table)->where($pk, $record->{$pk})->delete();
                    $deleted++;
                }
            }
        }

        return ['success' => true, 'message' => "Eliminati {$deleted} record doppioni con zero-padding da storici e previsionali.", 'stats' => ['deleted' => $deleted]];
    }

    // --- Delibere - Doppioni open con gestione tiraggi ---

    private function previewDelibereDoppioniOpen(): array
    {
        $usersWithMultipleOpen = DB::table('delibere')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $details = [];
        $idsToDelete = [];
        $tiraggiToReassign = 0;
        $tiraggiToDetach = 0;
        $skipped = 0;

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('delibere')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($records->count() < 2) {
                continue;
            }

            $toKeep = $records->first();
            $details[] = "=== user_id={$userGroup->user_id} | {$records->count()} delibere open ===";
            $details[] = "  KEEP   delibera_id={$toKeep->delibera_id} | created={$toKeep->created_at}";

            $tiraggiKeep = DB::table('tiraggi')->where('delibera_id', $toKeep->delibera_id)->pluck('tiraggio_id')->toArray();

            foreach ($records->skip(1) as $record) {
                $tiraggiDelete = DB::table('tiraggi')->where('delibera_id', $record->delibera_id)->pluck('tiraggio_id')->toArray();

                if (! empty($tiraggiKeep) && ! empty($tiraggiDelete) && array_diff($tiraggiDelete, $tiraggiKeep)) {
                    $skipped++;
                    $details[] = "  SKIP   delibera_id={$record->delibera_id} | tiraggi diversi tra KEEP e DELETE";

                    continue;
                }

                if (empty($tiraggiKeep) && ! empty($tiraggiDelete)) {
                    $tiraggiToReassign += count($tiraggiDelete);
                    $details[] = "  DELETE delibera_id={$record->delibera_id} | REASSIGN tiraggi=[".implode(',', $tiraggiDelete).']';
                } elseif (empty($tiraggiKeep) && empty($tiraggiDelete)) {
                    $details[] = "  DELETE delibera_id={$record->delibera_id} | nessun tiraggio collegato";
                } else {
                    $tiraggiToDetach += count($tiraggiDelete);
                    $details[] = "  DELETE delibera_id={$record->delibera_id} | DETACH tiraggi=[".implode(',', $tiraggiDelete).']';
                }

                $idsToDelete[] = $record->delibera_id;
            }
        }

        return [
            'can_execute' => count($idsToDelete) > 0,
            'summary' => [
                'utenti_con_doppioni' => $usersWithMultipleOpen->count(),
                'da_eliminare' => count($idsToDelete),
                'tiraggi_da_riassegnare' => $tiraggiToReassign,
                'tiraggi_da_sganciare' => $tiraggiToDetach,
                'skippati' => $skipped,
            ],
            'details' => $details,
            'warnings' => $skipped > 0 ? ["{$skipped} delibere skippate per tiraggi in conflitto"] : [],
        ];
    }

    private function executeDelibereDoppioniOpen(): array
    {
        $usersWithMultipleOpen = DB::table('delibere')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $idsToDelete = [];
        $deleted = 0;
        $reassigned = 0;
        $detached = 0;

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('delibere')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($records->count() < 2) {
                continue;
            }

            $toKeep = $records->first();
            $tiraggiKeep = DB::table('tiraggi')->where('delibera_id', $toKeep->delibera_id)->pluck('tiraggio_id')->toArray();

            foreach ($records->skip(1) as $record) {
                $tiraggiDelete = DB::table('tiraggi')->where('delibera_id', $record->delibera_id)->pluck('tiraggio_id')->toArray();

                if (! empty($tiraggiKeep) && ! empty($tiraggiDelete) && array_diff($tiraggiDelete, $tiraggiKeep)) {
                    continue;
                }

                if (empty($tiraggiKeep) && ! empty($tiraggiDelete)) {
                    DB::table('tiraggi')->whereIn('tiraggio_id', $tiraggiDelete)->update(['delibera_id' => $toKeep->delibera_id]);
                    $reassigned += count($tiraggiDelete);
                } elseif (! empty($tiraggiKeep) && ! empty($tiraggiDelete)) {
                    DB::table('tiraggi')->whereIn('tiraggio_id', $tiraggiDelete)->update(['delibera_id' => null]);
                    $detached += count($tiraggiDelete);
                }

                $idsToDelete[] = $record->delibera_id;
            }
        }

        if (! empty($idsToDelete)) {
            DB::table('delibere')->whereIn('delibera_id', $idsToDelete)->delete();
            $deleted = count($idsToDelete);
        }

        return ['success' => true, 'message' => "Eliminate {$deleted} delibere. Tiraggi riassegnati: {$reassigned}, sganciati: {$detached}.", 'stats' => ['deleted' => $deleted, 'reassigned' => $reassigned, 'detached' => $detached]];
    }

    // --- Tiraggi - Doppioni open strict (campi identici + regole delibera/trattenuta) ---

    /**
     * Fields left out of the strict comparison. delibera_id is not compared as a
     * plain field: a different delibera is handled by the delibera rule below.
     */
    private const STRICT_EXCLUDED_FIELDS = ['tiraggio_id', 'numero_pratica', 'created_at', 'updated_at', 'tan', 'taeg', 'delibera_id'];

    private function previewTiraggiDoppioniOpenStrict(): array
    {
        $plan = $this->planTiraggiDoppioniOpenStrict();
        $stats = $plan['stats'];

        $warnings = [];
        if ($stats['skipped'] > 0) {
            $warnings[] = "{$stats['skipped']} tiraggi skippati (campi diversi o delibere senza regola applicabile)";
        }
        if ($stats['discrepancies'] > 0) {
            $warnings[] = "{$stats['discrepancies']} discrepanze tra ordine di created_at e updated_at";
        }

        return [
            'can_execute' => count($plan['ids']) > 0,
            'summary' => [
                'utenti_con_doppioni' => $plan['users'],
                'da_eliminare' => count($plan['ids']),
                'eliminati_regola_delibera' => $stats['delibera_rule'],
                'swap_trattenuta' => $stats['swaps'],
                'skippati' => $stats['skipped'],
                'discrepanze' => $stats['discrepancies'],
            ],
            'details' => $plan['details'],
            'warnings' => $warnings,
        ];
    }

    private function executeTiraggiDoppioniOpenStrict(): array
    {
        // Same plan as the preview, so exactly the tiraggi shown there are deleted.
        $plan = $this->planTiraggiDoppioniOpenStrict();

        $deleted = 0;
        if (! empty($plan['ids'])) {
            DB::table('tiraggi')->whereIn('tiraggio_id', $plan['ids'])->delete();
            $deleted = count($plan['ids']);
        }

        return [
            'success' => true,
            'message' => "Eliminati {$deleted} tiraggi doppioni (regola delibera: {$plan['stats']['delibera_rule']}, swap trattenuta: {$plan['stats']['swaps']}, skippati: {$plan['stats']['skipped']}).",
            'stats' => ['deleted' => $deleted] + $plan['stats'],
        ];
    }

    /**
     * Decide which open tiraggi to delete for users with 2+ open tiraggi.
     *
     * Read-only: shared by preview and execute so the two cannot diverge.
     * For each user the most recent tiraggio (created_at) starts as the one to
     * keep, and every other one is compared with it:
     *  - all fields except STRICT_EXCLUDED_FIELDS must match, otherwise the
     *    record is skipped; trattenuta_totale NULL and 0.00 count as equal;
     *  - different delibera_id: the tiraggio linked to the open delibera is
     *    kept and the one linked to the close delibera is deleted, whichever
     *    side it is on; any other combination is skipped;
     *  - same delibera_id, and the one to keep has trattenuta_totale NULL while
     *    the other has a value: the one with the value is kept (swap).
     * After a swap the newly kept record is the reference for the next ones.
     *
     * @return array{users: int, ids: list<int|string>, details: list<string>, stats: array{skipped: int, delibera_rule: int, swaps: int, discrepancies: int}}
     */
    private function planTiraggiDoppioniOpenStrict(): array
    {
        $usersWithMultipleOpen = DB::table('tiraggi')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $details = [];
        $idsToDelete = [];
        $stats = ['skipped' => 0, 'delibera_rule' => 0, 'swaps' => 0, 'discrepancies' => 0];

        // Status of the delibera linked to a tiraggio (null when there is none), read once per delibera.
        $deliberaStatuses = [];
        $deliberaStatus = function ($deliberaId) use (&$deliberaStatuses): ?string {
            if ($deliberaId === null) {
                return null;
            }

            return $deliberaStatuses[$deliberaId] ??= DB::table('delibere')->where('delibera_id', $deliberaId)->value('status');
        };

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('tiraggi')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($records->count() < 2) {
                continue;
            }

            $toKeep = $records->first();
            $details[] = "=== user_id={$userGroup->user_id} | {$records->count()} tiraggi open ===";
            $details[] = "  KEEP   tiraggio_id={$toKeep->tiraggio_id} | numero_pratica={$toKeep->numero_pratica} | delibera_id={$toKeep->delibera_id} [".$deliberaStatus($toKeep->delibera_id).'] | trattenuta_totale='.var_export($toKeep->trattenuta_totale, true)." | created={$toKeep->created_at} | updated={$toKeep->updated_at}";

            foreach ($records->skip(1) as $record) {
                // Recomputed on every iteration: $toKeep changes after a swap.
                $differences = $this->strictTiraggioDifferences($toKeep, $record);

                if (! empty($differences)) {
                    $stats['skipped']++;
                    $details[] = "  SKIP   tiraggio_id={$record->tiraggio_id} | numero_pratica={$record->numero_pratica} | campi diversi: ".implode(' | ', $differences).' -> eliminazione non possibile';

                    continue;
                }

                $keepStatus = $deliberaStatus($toKeep->delibera_id);
                $recordStatus = $deliberaStatus($record->delibera_id);
                $swap = false;
                $label = '';

                if ((string) $toKeep->delibera_id !== (string) $record->delibera_id) {
                    if ($keepStatus === 'open' && $recordStatus === 'close') {
                        $stats['delibera_rule']++;
                        $label = ' [REGOLA DELIBERA: eliminato tiraggio collegato a delibera close]';
                    } elseif ($keepStatus === 'close' && $recordStatus === 'open') {
                        $stats['delibera_rule']++;
                        $swap = true;
                        $label = ' [REGOLA DELIBERA: tenuto tiraggio collegato a delibera open]';
                    } else {
                        $stats['skipped']++;
                        $details[] = "  SKIP   tiraggio_id={$record->tiraggio_id} | numero_pratica={$record->numero_pratica} | delibera_id diverso (KEEP={$toKeep->delibera_id} [{$keepStatus}] DELETE={$record->delibera_id} [{$recordStatus}]) -> nessuna regola applicabile";

                        continue;
                    }
                } elseif ($toKeep->trattenuta_totale === null && $record->trattenuta_totale !== null) {
                    $stats['swaps']++;
                    $swap = true;
                    $label = ' [SWAP: tenuto il record con trattenuta_totale valorizzata]';
                }

                $keepRecord = $swap ? $record : $toKeep;
                $deleteRecord = $swap ? $toKeep : $record;
                $deleteStatus = $swap ? $keepStatus : $recordStatus;

                [$discrepancy, $createdDiff, $updatedDiff] = $this->timestampDiscrepancy($keepRecord, $deleteRecord);
                if ($discrepancy) {
                    $stats['discrepancies']++;
                }

                $details[] = "  DELETE tiraggio_id={$deleteRecord->tiraggio_id} | numero_pratica={$deleteRecord->numero_pratica} | delibera_id={$deleteRecord->delibera_id} [{$deleteStatus}] | trattenuta_totale=".var_export($deleteRecord->trattenuta_totale, true)." | created={$deleteRecord->created_at} | updated={$deleteRecord->updated_at} (delta created: {$createdDiff} | delta updated: {$updatedDiff})".($discrepancy ? ' *** DISCREPANZA ***' : '').$label;

                if ($swap) {
                    $details[] = "  KEEP   (nuovo) tiraggio_id={$keepRecord->tiraggio_id} | numero_pratica={$keepRecord->numero_pratica} | delibera_id={$keepRecord->delibera_id} [{$recordStatus}] | trattenuta_totale=".var_export($keepRecord->trattenuta_totale, true);
                }

                $toKeep = $keepRecord;
                $idsToDelete[] = $deleteRecord->tiraggio_id;
            }
        }

        return [
            'users' => $usersWithMultipleOpen->count(),
            'ids' => $idsToDelete,
            'details' => $details,
            'stats' => $stats,
        ];
    }

    /**
     * Fields that differ between two tiraggi, ignoring STRICT_EXCLUDED_FIELDS.
     * trattenuta_totale is compared as a number, so NULL and 0.00 are equal.
     *
     * @return list<string>
     */
    private function strictTiraggioDifferences(object $keep, object $record): array
    {
        $keepData = collect((array) $keep)->except(self::STRICT_EXCLUDED_FIELDS)->toArray();
        $recordData = collect((array) $record)->except(self::STRICT_EXCLUDED_FIELDS)->toArray();

        $differences = [];
        foreach ($keepData as $field => $value) {
            $other = $recordData[$field] ?? null;

            $equal = $field === 'trattenuta_totale'
                ? (float) $value === (float) $other
                : (string) $value === (string) $other;

            if (! $equal) {
                $differences[] = "{$field}: KEEP={$value} DELETE={$other}";
            }
        }

        return $differences;
    }

    /**
     * Compare the timestamps of the kept and the deleted record.
     *
     * A discrepancy is flagged when created_at and updated_at disagree on
     * which of the two records is the newer one (both differing): the record
     * kept as "most recent" by creation was not the most recently updated.
     *
     * @return array{bool, string, string} [discrepancy, delta created, delta updated]
     */
    private function timestampDiscrepancy(object $keep, object $delete): array
    {
        $keepCreated = Carbon::parse($keep->created_at);
        $deleteCreated = Carbon::parse($delete->created_at);
        $keepUpdated = Carbon::parse($keep->updated_at);
        $deleteUpdated = Carbon::parse($delete->updated_at);

        $discrepancy = ! $keepCreated->eq($deleteCreated)
            && $keepUpdated->ne($deleteUpdated)
            && $keepCreated->gt($deleteCreated) !== $keepUpdated->gt($deleteUpdated);

        return [
            $discrepancy,
            $deleteCreated->diffForHumans($keepCreated),
            $deleteUpdated->diffForHumans($keepUpdated),
        ];
    }

    // --- Tiraggi - Doppioni open loose (senza confronto) ---

    private function previewTiraggiDoppioniOpenLoose(): array
    {
        $usersWithMultipleOpen = DB::table('tiraggi')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $details = [];
        $idsToDelete = [];

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('tiraggi')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($records->count() < 2) {
                continue;
            }

            $toKeep = $records->first();
            $details[] = "=== user_id={$userGroup->user_id} | {$records->count()} tiraggi open ===";
            $details[] = "  KEEP   tiraggio_id={$toKeep->tiraggio_id} | numero_pratica={$toKeep->numero_pratica} | created={$toKeep->created_at}";

            foreach ($records->skip(1) as $record) {
                $details[] = "  DELETE tiraggio_id={$record->tiraggio_id} | numero_pratica={$record->numero_pratica} | created={$record->created_at}";
                $idsToDelete[] = $record->tiraggio_id;
            }
        }

        return [
            'can_execute' => count($idsToDelete) > 0,
            'summary' => [
                'utenti_con_doppioni' => $usersWithMultipleOpen->count(),
                'da_eliminare' => count($idsToDelete),
                'skippati' => 0,
            ],
            'details' => $details,
            'warnings' => [],
        ];
    }

    private function executeTiraggiDoppioniOpenLoose(): array
    {
        $usersWithMultipleOpen = DB::table('tiraggi')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $idsToDelete = [];

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('tiraggi')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($records->count() < 2) {
                continue;
            }

            foreach ($records->skip(1) as $record) {
                $idsToDelete[] = $record->tiraggio_id;
            }
        }

        $deleted = 0;
        if (! empty($idsToDelete)) {
            DB::table('tiraggi')->whereIn('tiraggio_id', $idsToDelete)->delete();
            $deleted = count($idsToDelete);
        }

        return ['success' => true, 'message' => "Eliminati {$deleted} tiraggi doppioni.", 'stats' => ['deleted' => $deleted]];
    }

    // --- Tiraggi - Requested fino a data custom ---

    private function previewTiraggiRequestedOld(array $parameters): array
    {
        $dataFine = $parameters['data_fine'] ?? '';

        if (empty($dataFine)) {
            return ['can_execute' => false, 'summary' => [], 'details' => ['Parametro data_fine obbligatorio.'], 'warnings' => ['Parametro mancante']];
        }

        $tiraggi = DB::table('tiraggi')
            ->where('status', 'requested')
            ->where('data_richiesta', '<=', $dataFine)
            ->get();

        $conOpen = 0;
        $senzaOpen = 0;
        $details = [];

        foreach ($tiraggi as $tiraggio) {
            $hasOpen = DB::table('tiraggi')
                ->where('user_id', $tiraggio->user_id)
                ->where('status', 'open')
                ->exists();

            if ($hasOpen) {
                $conOpen++;
            } else {
                $senzaOpen++;
                $details[] = "NO OPEN user_id={$tiraggio->user_id} | tiraggio_id={$tiraggio->tiraggio_id} | numero_pratica={$tiraggio->numero_pratica} | data_richiesta={$tiraggio->data_richiesta}";
            }
        }

        return [
            'can_execute' => $tiraggi->count() > 0,
            'summary' => [
                'totali' => $tiraggi->count(),
                'con_open' => $conOpen,
                'senza_open' => $senzaOpen,
            ],
            'details' => $details,
            'warnings' => $senzaOpen > 0 ? ["{$senzaOpen} utenti senza tiraggio open attivo"] : [],
        ];
    }

    private function executeTiraggiRequestedOld(array $parameters): array
    {
        $dataFine = $parameters['data_fine'] ?? '';

        $count = DB::table('tiraggi')
            ->where('status', 'requested')
            ->where('data_richiesta', '<=', $dataFine)
            ->count();

        DB::table('tiraggi')
            ->where('status', 'requested')
            ->where('data_richiesta', '<=', $dataFine)
            ->delete();

        return ['success' => true, 'message' => "Eliminati {$count} tiraggi requested fino al {$dataFine}.", 'stats' => ['deleted' => $count]];
    }

    // --- Storici & Previsionali - Delete by month ---

    private const DELETE_BY_MONTH_TABLES = [
        'storici' => 'storico_id',
        'previsionali' => 'previsionale_id',
    ];

    private function previewStoriciPrevisionaliDeleteByMonth(array $parameters): array
    {
        $anno = $parameters['anno'] ?? '';
        $mese = $parameters['mese'] ?? '';

        if (empty($anno) || empty($mese)) {
            return ['can_execute' => false, 'summary' => [], 'details' => ['Parametri anno e mese obbligatori.'], 'warnings' => ['Parametri mancanti']];
        }

        $details = [];
        $totalCount = 0;

        foreach (self::DELETE_BY_MONTH_TABLES as $table => $pk) {
            if (! Schema::hasTable($table)) {
                $details[] = "SKIP: Tabella \"{$table}\" non trovata.";

                continue;
            }

            $count = DB::table($table)->where('anno', $anno)->where('mese', $mese)->count();
            $totalCount += $count;
            $details[] = "{$table}: {$count} record per {$anno}/{$mese}";
        }

        return [
            'can_execute' => $totalCount > 0,
            'summary' => ['record_trovati' => $totalCount],
            'details' => $details,
            'warnings' => [],
        ];
    }

    private function executeStoriciPrevisionaliDeleteByMonth(array $parameters): array
    {
        $anno = $parameters['anno'] ?? '';
        $mese = $parameters['mese'] ?? '';

        $totalDeleted = 0;

        foreach (self::DELETE_BY_MONTH_TABLES as $table => $pk) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->where('anno', $anno)->where('mese', $mese)->count();
            DB::table($table)->where('anno', $anno)->where('mese', $mese)->delete();
            $totalDeleted += $count;
        }

        return ['success' => true, 'message' => "Eliminati {$totalDeleted} record per {$anno}/{$mese} da storici e previsionali.", 'stats' => ['deleted' => $totalDeleted]];
    }

    // --- Delibere - Close orphaned ---

    private function previewDelibereCloseOrphaned(): array
    {
        $delibere = DB::table('delibere')
            ->where('status', 'close')
            ->whereNotIn('delibera_id', DB::table('tiraggi')->whereNotNull('delibera_id')->pluck('delibera_id'))
            ->get();

        $details = [];
        foreach ($delibere as $record) {
            $details[] = "delibera_id={$record->delibera_id} | user_id={$record->user_id} | created={$record->created_at}";
        }

        return [
            'can_execute' => $delibere->count() > 0,
            'summary' => ['da_eliminare' => $delibere->count()],
            'details' => $details,
            'warnings' => [],
        ];
    }

    private function executeDelibereCloseOrphaned(): array
    {
        $count = DB::table('delibere')
            ->where('status', 'close')
            ->whereNotIn('delibera_id', DB::table('tiraggi')->whereNotNull('delibera_id')->pluck('delibera_id'))
            ->count();

        DB::table('delibere')
            ->where('status', 'close')
            ->whereNotIn('delibera_id', DB::table('tiraggi')->whereNotNull('delibera_id')->pluck('delibera_id'))
            ->delete();

        return ['success' => true, 'message' => "Eliminate {$count} delibere close orfane.", 'stats' => ['deleted' => $count]];
    }

    // --- Tiraggi - Doppioni open senza delibera_id ---

    private function previewTiraggiDoppioniOpenNoDelibera(): array
    {
        $usersWithMultipleOpen = DB::table('tiraggi')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $details = [];
        $idsToDelete = [];
        $skipped = 0;

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('tiraggi')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->get();

            $conDelibera = $records->whereNotNull('delibera_id');
            $senzaDelibera = $records->whereNull('delibera_id');

            if ($conDelibera->isEmpty() || $senzaDelibera->isEmpty()) {
                $skipped++;
                $details[] = "SKIP user_id={$userGroup->user_id}: nessuna coppia con/senza delibera_id (con={$conDelibera->count()} senza={$senzaDelibera->count()})";

                continue;
            }

            $toKeep = $conDelibera->first();
            $details[] = "=== user_id={$userGroup->user_id} ===";
            $details[] = "  KEEP   tiraggio_id={$toKeep->tiraggio_id} | delibera_id={$toKeep->delibera_id}";

            foreach ($senzaDelibera as $record) {
                $details[] = "  DELETE tiraggio_id={$record->tiraggio_id} | delibera_id=NULL";
                $idsToDelete[] = $record->tiraggio_id;
            }
        }

        return [
            'can_execute' => count($idsToDelete) > 0,
            'summary' => [
                'utenti_con_doppioni' => $usersWithMultipleOpen->count(),
                'da_eliminare' => count($idsToDelete),
                'skippati' => $skipped,
            ],
            'details' => $details,
            'warnings' => $skipped > 0 ? ["{$skipped} utenti skippati senza coppia chiara"] : [],
        ];
    }

    private function executeTiraggiDoppioniOpenNoDelibera(): array
    {
        $usersWithMultipleOpen = DB::table('tiraggi')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('total', '>', 1)
            ->get();

        $idsToDelete = [];

        foreach ($usersWithMultipleOpen as $userGroup) {
            $records = DB::table('tiraggi')
                ->where('user_id', $userGroup->user_id)
                ->where('status', 'open')
                ->get();

            $conDelibera = $records->whereNotNull('delibera_id');
            $senzaDelibera = $records->whereNull('delibera_id');

            if ($conDelibera->isEmpty() || $senzaDelibera->isEmpty()) {
                continue;
            }

            foreach ($senzaDelibera as $record) {
                $idsToDelete[] = $record->tiraggio_id;
            }
        }

        $deleted = 0;
        if (! empty($idsToDelete)) {
            DB::table('tiraggi')->whereIn('tiraggio_id', $idsToDelete)->delete();
            $deleted = count($idsToDelete);
        }

        return ['success' => true, 'message' => "Eliminati {$deleted} tiraggi open senza delibera_id.", 'stats' => ['deleted' => $deleted]];
    }
}

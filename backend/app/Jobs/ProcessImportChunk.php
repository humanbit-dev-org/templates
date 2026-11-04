<?php

namespace App\Jobs;

use App\Support\Backpack\CsvHelper;
use App\Support\Backpack\CsvTransformer;
use App\Support\Backpack\ImportJobService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Processes a chunk of CSV rows as part of a batch import.
 * Used by ProcessImportFile when Bus::batch() parallelism is enabled.
 */
class ProcessImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    /**
     * @param  string  $subfolder  Import subfolder name.
     * @param  string  $modelName  Model class name (without namespace).
     * @param  array  $rows  Chunk of CSV rows to process.
     * @param  array  $headers  CSV headers from file.
     * @param  int  $chunkIndex  Index of this chunk (for logging).
     * @param  array  $preloadedData  Preloaded data for batch lookups.
     */
    public function __construct(
        private string $subfolder,
        private string $modelName,
        private array $rows,
        private array $headers,
        private int $chunkIndex,
        private array $preloadedData = []
    ) {
        // Explicit hash tag ensures all import queue keys land on the same
        // Redis Cluster slot — required for Lua atomic operations (push/pop/reserve).
        $this->onQueue('{import}');
    }

    public function tags(): array
    {
        return [
            'import-chunk',
            'subfolder:'.$this->subfolder,
            'model:'.$this->modelName,
            'chunk:'.$this->chunkIndex,
            'rows:'.count($this->rows),
        ];
    }

    public function handle(): void
    {
        // Bail out early if the batch was cancelled while this chunk was queued.
        if ($this->batch()?->cancelled()) {
            Log::channel('import')->info("Chunk #{$this->chunkIndex} skipped — batch cancelled", [
                'subfolder' => $this->subfolder,
                'chunk' => $this->chunkIndex,
            ]);

            return;
        }

        $modelClass = "App\\Models\\{$this->modelName}";

        Log::channel('import')->info(
            "Processing chunk #{$this->chunkIndex} ({$this->subfolder}): ".count($this->rows).' rows'
        );

        $stats = $this->processRows($modelClass);

        Log::channel('import')->info(
            "Chunk #{$this->chunkIndex} completed: ".
                "created={$stats['created']}, updated={$stats['updated']}, ".
                "skipped={$stats['skipped']}, errors={$stats['errors']}"
        );
    }

    /**
     * Process the chunk of rows inside a single DB transaction.
     *
     * Uses DB::transaction() instead of manual beginTransaction/commit/rollback
     * so that any Throwable — including Error subclasses like TypeError or
     * OutOfMemoryError — automatically triggers a rollback.
     * Manual catch (\Exception) misses Error hierarchy entirely.
     */
    private function processRows(string $modelClass): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $model = new $modelClass;
        $tableColumns = Schema::getColumnListing($model->getTable());
        $fillableColumns = $model->getFillable();
        $columnMapping = $this->createAutoMapping($this->headers, $tableColumns, $fillableColumns);

        $batch = [];
        $batchSize = 200;

        DB::transaction(function () use ($modelClass, $columnMapping, &$batch, $batchSize, &$stats) {
            foreach ($this->rows as $row) {
                $rowData = $this->mapRowData($row, $columnMapping, $modelClass);

                if (empty($rowData) || ! empty($rowData['__skip_record'])) {
                    $stats['skipped']++;
                    unset($rowData);

                    continue;
                }

                $rowData = CsvTransformer::transform(
                    $rowData,
                    $row,
                    $columnMapping,
                    $this->headers,
                    $modelClass,
                    $this->preloadedData
                );

                if (empty($rowData)) {
                    $stats['skipped']++;

                    continue;
                }

                if (isset($rowData['__multiple_records__'])) {
                    foreach ($rowData['__multiple_records__'] as $record) {
                        $batch[] = $record;
                    }
                    unset($rowData);
                } else {
                    $batch[] = $rowData;
                }

                if (count($batch) >= $batchSize) {
                    $batchStats = ImportJobService::processBatchPublic($batch, $modelClass);
                    $stats = $this->mergeStats($stats, $batchStats);
                    $batch = [];
                }
            }

            // Flush remaining rows after the loop.
            if (! empty($batch)) {
                $batchStats = ImportJobService::processBatchPublic($batch, $modelClass);
                $stats = $this->mergeStats($stats, $batchStats);
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $batchStats
     * @return array<string, int>
     */
    private function mergeStats(array $stats, array $batchStats): array
    {
        foreach (['created', 'updated', 'skipped', 'errors'] as $key) {
            $stats[$key] += $batchStats[$key] ?? 0;
        }

        return $stats;
    }

    private function createAutoMapping(array $headers, array $tableColumns, array $fillableColumns): array
    {
        $mapping = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = Str::snake(strtolower(trim($header)));

            if (
                in_array($normalizedHeader, $tableColumns) &&
                (empty($fillableColumns) || in_array($normalizedHeader, $fillableColumns))
            ) {
                $mapping[$index] = $normalizedHeader;
            }
        }

        return $mapping;
    }

    private function mapRowData(array $row, array $columnMapping, string $modelClass): array
    {
        $rowData = [];

        foreach ($columnMapping as $csvIndex => $dbColumn) {
            if (! isset($row[$csvIndex])) {
                continue;
            }

            $value = trim($row[$csvIndex]);

            if (! CsvHelper::isDateLikeColumn($dbColumn)) {
                if (CsvHelper::looksNumeric($value)) {
                    $value = CsvHelper::simpleDecimal($value);
                }
            }

            if (
                str_contains($dbColumn, '_at') &&
                ! in_array($dbColumn, ImportJobService::NON_DATE_AT_COLUMNS, true) &&
                $value !== ''
            ) {
                $parsedDate = CsvHelper::parseDate($value);
                if ($parsedDate) {
                    $value = $parsedDate;
                }
            }

            // Same empty-cell rule as ImportJobService::mapRowData().
            if ($value === '' && in_array($dbColumn, ImportJobService::ZERO_WHEN_EMPTY_COLUMNS, true)) {
                $rowData[$dbColumn] = 0;
            } elseif ($value !== '') {
                $rowData[$dbColumn] = $value;
            }
        }

        return $rowData;
    }
}

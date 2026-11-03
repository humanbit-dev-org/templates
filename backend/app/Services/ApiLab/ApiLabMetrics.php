<?php

namespace App\Services\ApiLab;

use Illuminate\Support\Facades\DB;

class ApiLabMetrics
{
    protected int $queryCount = 0;

    protected float $queryTimeMs = 0;

    protected ?float $memoryBefore = null;

    protected bool $listening = false;

    public function start(): void
    {
        $this->queryCount = 0;
        $this->queryTimeMs = 0;
        $this->memoryBefore = memory_get_usage(true);
        $this->listening = true;

        DB::listen(function ($query): void {
            if (! $this->listening) {
                return;
            }
            $this->queryCount++;
            $this->queryTimeMs += (float) ($query->time ?? 0);
        });
    }

    public function stop(): void
    {
        $this->listening = false;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    public function collect(int $durationMs, ?string $responseBody, array $steps): array
    {
        $outboundMs = 0;
        foreach ($steps as $step) {
            if (($step['type'] ?? '') === 'outbound_http') {
                $outboundMs += (int) ($step['duration_ms'] ?? 0);
            }
        }

        $handlerMs = max(0, $durationMs - $outboundMs);
        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $deltaMb = $this->memoryBefore !== null
            ? round((memory_get_usage(true) - $this->memoryBefore) / 1024 / 1024, 2)
            : null;

        return [
            'duration_ms' => $durationMs,
            'handler_ms' => $handlerMs,
            'outbound_ms' => $outboundMs,
            'outbound_calls' => collect($steps)->where('type', 'outbound_http')->count(),
            'steps_count' => count($steps),
            'sql_queries' => $this->queryCount,
            'sql_time_ms' => (int) round($this->queryTimeMs),
            'memory_peak_mb' => $peakMb,
            'memory_delta_mb' => $deltaMb,
            'response_bytes' => $responseBody !== null ? strlen($responseBody) : 0,
        ];
    }
}

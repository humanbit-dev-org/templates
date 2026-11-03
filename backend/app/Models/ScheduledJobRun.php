<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Str;

/**
 * One execution of a scheduled job: written by the scheduler listener for the
 * runs schedule:run starts, and by RunScheduledCommand for the manual ones.
 * Kept 30 days (model:prune in routes/console.php).
 */
class ScheduledJobRun extends Model
{
    use Prunable;

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MANUAL = 'manual';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /** Characters of output kept: the tail, where the outcome is. */
    public const OUTPUT_LIMIT = 20000;

    protected $fillable = [
        'name',
        'trigger',
        'dry_run',
        'status',
        'parameters',
        'exit_code',
        'output',
        'triggered_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'parameters' => 'array',
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(30));
    }

    public static function tail(?string $output): ?string
    {
        if ($output === null || trim($output) === '') {
            return null;
        }

        return mb_strlen($output) > self::OUTPUT_LIMIT
            ? '…'.mb_substr($output, -self::OUTPUT_LIMIT)
            : $output;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function durationForHumans(): ?string
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        $seconds = $this->started_at->diffInMilliseconds($this->finished_at) / 1000;

        return $seconds < 60
            ? number_format($seconds, 1, ',', '').' s'
            : Str::of((string) intdiv((int) $seconds, 60))->append(' min ', (string) ((int) $seconds % 60), ' s')->toString();
    }
}

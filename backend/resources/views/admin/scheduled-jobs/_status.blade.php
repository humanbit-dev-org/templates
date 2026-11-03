@php
    [$class, $label] = match ($run->status) {
        \App\Models\ScheduledJobRun::STATUS_QUEUED => ['bg-secondary', 'In coda'],
        \App\Models\ScheduledJobRun::STATUS_RUNNING => ['bg-info', 'In corso'],
        \App\Models\ScheduledJobRun::STATUS_SUCCESS => ['bg-success', 'OK'],
        \App\Models\ScheduledJobRun::STATUS_FAILED => ['bg-danger', 'Fallito'],
        \App\Models\ScheduledJobRun::STATUS_SKIPPED => ['bg-warning text-dark', 'Saltato'],
        default => ['bg-light text-dark', $run->status],
    };
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
@if ($run->dry_run)
    <span class="badge bg-light text-dark border">dry run</span>
@endif

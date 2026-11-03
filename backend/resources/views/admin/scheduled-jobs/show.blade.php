@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-clock"></i> Esecuzione #{{ $run->id }}
    </h1>
    <p class="ms-2 mb-0">
        <a href="{{ route('scheduled-jobs.index') }}" class="d-print-none font-sm">
            <i class="la la-angle-double-left"></i> Torna a Scheduled Jobs
        </a>
    </p>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="la la-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Job</dt>
                    <dd class="col-sm-9">
                        {{ $definition['title'] ?? $run->name }}
                        <div><code class="small">{{ $run->name }}</code></div>
                    </dd>

                    @if ($commandLine)
                        <dt class="col-sm-3">Comando</dt>
                        <dd class="col-sm-9"><code>{{ $commandLine }}</code></dd>
                    @endif

                    <dt class="col-sm-3">Esito</dt>
                    <dd class="col-sm-9">
                        @include('admin.scheduled-jobs._status', ['run' => $run])
                        @if ($run->exit_code !== null)
                            <span class="text-muted small ms-1">exit code {{ $run->exit_code }}</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Avvio</dt>
                    <dd class="col-sm-9">
                        {{ $run->trigger === \App\Models\ScheduledJobRun::TRIGGER_MANUAL ? 'Manuale' : 'Scheduler' }}
                        @if ($run->triggered_by)
                            — {{ $run->triggered_by }}
                        @endif
                    </dd>

                    <dt class="col-sm-3">Accodato</dt>
                    <dd class="col-sm-9">{{ $run->created_at->format('d/m/Y H:i:s') }}</dd>

                    <dt class="col-sm-3">Inizio / fine</dt>
                    <dd class="col-sm-9">
                        {{ $run->started_at?->format('d/m/Y H:i:s') ?? '—' }}
                        /
                        {{ $run->finished_at?->format('d/m/Y H:i:s') ?? '—' }}
                        @if ($run->durationForHumans())
                            <span class="text-muted small ms-1">({{ $run->durationForHumans() }})</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Output</strong></div>
            <div class="card-body">
                @if ($run->output)
                    <pre class="mb-0 small" style="white-space: pre-wrap; max-height: 70vh; overflow: auto;">{{ $run->output }}</pre>
                @elseif ($run->isPending())
                    <span class="text-muted"><i class="la la-spinner la-spin"></i> In attesa del risultato…</span>
                @else
                    <span class="text-muted">Nessun output.</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@if ($run->isPending())
    @section('after_scripts')
        <script>setTimeout(() => window.location.reload(), 3000);</script>
    @endsection
@endif

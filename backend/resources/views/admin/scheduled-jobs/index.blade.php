@extends(backpack_view('blank'))

@php($describe = fn (string $expression) => \App\Services\ScheduledJobs\ScheduledJobRegistry::describe($expression))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-clock"></i> Scheduled Jobs
    </h1>
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

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="la la-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="alert alert-warning bg-light rounded p-4 mb-4">
            <div class="mb-0">
                <div class="mb-2">
                    <i class="la la-info-circle text-info me-2"></i>
                    L'elenco è letto dallo scheduler (<code>routes/console.php</code>), orari nel fuso {{ config('app.timezone') }}
                </div>
                <div class="mb-2">
                    <i class="la la-flask text-success me-2"></i>
                    Il <strong>dry run</strong> usa l'opzione <code>--dry-run</code> del comando: mostra cosa farebbe senza modificare nulla
                </div>
                <div>
                    <i class="la la-exclamation-triangle text-warning me-2"></i>
                    L'esecuzione reale va in coda su Horizon e rispetta il blocco anti-sovrapposizione dello scheduler
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($runnable as $row)
                @php($definition = $row['definition'])
                <div class="col-md-6 col-xxl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start mb-3">
                                <i class="{{ $definition['icon'] }} la-2x text-primary me-3"></i>
                                <div>
                                    <h6 class="card-title mb-1">{{ $definition['title'] }}</h6>
                                    <p class="card-text text-muted small mb-2">{{ $definition['description'] }}</p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-light text-dark border font-monospace">{{ $row['name'] }}</span>
                                        @if ($row['without_overlapping'])
                                            <span class="badge bg-light text-muted border" title="withoutOverlapping">no overlap</span>
                                        @endif
                                        @if ($row['on_one_server'])
                                            <span class="badge bg-light text-muted border" title="onOneServer">one server</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="row g-0 small border-top border-bottom py-2 mb-3">
                                <div class="col-4 pe-2">
                                    <div class="text-muted">Frequenza</div>
                                    <div title="{{ $row['expression'] }}">{{ $describe($row['expression']) }}</div>
                                </div>
                                <div class="col-4 pe-2">
                                    <div class="text-muted">Prossima</div>
                                    <div>{{ $row['next_run']->format('d/m H:i') }}</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Ultima</div>
                                    @if ($row['last_run'])
                                        <a href="{{ route('scheduled-jobs.show', $row['last_run']) }}" class="text-decoration-none">
                                            @include('admin.scheduled-jobs._status', ['run' => $row['last_run']])
                                        </a>
                                        <div class="text-muted">{{ ($row['last_run']->started_at ?? $row['last_run']->created_at)->format('d/m H:i') }}</div>
                                    @else
                                        <div>—</div>
                                    @endif
                                </div>
                            </div>

                            <form action="{{ route('scheduled-jobs.run') }}" method="POST" class="mt-auto">
                                @csrf
                                <input type="hidden" name="name" value="{{ $row['name'] }}">
                                @foreach ($definition['parameters'] ?? [] as $key => $parameter)
                                    <div class="mb-2">
                                        <label class="form-label small text-muted mb-1" for="param-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $parameter['label'] }}</label>
                                        <input type="{{ $parameter['type'] }}"
                                               id="param-{{ $loop->parent->index }}-{{ $loop->index }}"
                                               name="params[{{ $key }}]"
                                               value="{{ $parameter['default'] ?? '' }}"
                                               class="form-control form-control-sm">
                                    </div>
                                @endforeach
                                <div class="d-flex gap-2">
                                    @if ($definition['dry_run'])
                                        <button type="submit" name="dry_run" value="1" class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="la la-flask"></i> Dry run
                                        </button>
                                    @endif
                                    <button type="submit" name="dry_run" value="0" class="btn btn-sm btn-outline-danger flex-fill"
                                            onclick="return confirm(@js('Eseguire davvero: '.$definition['title'].'?'))">
                                        <i class="la la-play"></i> Esegui
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($system->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Job di sistema</strong>
                    <span class="text-muted small ms-2">solo schedulati, non lanciabili da qui</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th>Comando</th>
                                <th>Frequenza</th>
                                <th>Prossima</th>
                                <th>Ultima</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($system as $row)
                                <tr>
                                    <td><span class="font-monospace small">{{ $row['name'] }}</span></td>
                                    <td title="{{ $row['expression'] }}">{{ $describe($row['expression']) }}</td>
                                    <td class="text-nowrap">{{ $row['next_run']->format('d/m H:i') }}</td>
                                    <td class="text-nowrap">
                                        @if ($row['last_run'])
                                            @include('admin.scheduled-jobs._status', ['run' => $row['last_run']])
                                            <span class="text-muted small ms-1">{{ ($row['last_run']->started_at ?? $row['last_run']->created_at)->format('d/m H:i') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <strong>Ultime esecuzioni</strong>
                <span class="text-muted small ms-2">dei job lanciabili, ultimi 30 giorni</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th>Inizio</th>
                            <th>Job</th>
                            <th>Avvio</th>
                            <th>Esito</th>
                            <th>Durata</th>
                            <th>Lanciato da</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr>
                                <td class="text-nowrap">{{ ($run->started_at ?? $run->created_at)->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $registry->definition($run->name)['title'] ?? $run->name }}</td>
                                <td>{{ $run->trigger === \App\Models\ScheduledJobRun::TRIGGER_MANUAL ? 'Manuale' : 'Scheduler' }}</td>
                                <td class="text-nowrap">@include('admin.scheduled-jobs._status', ['run' => $run])</td>
                                <td class="text-nowrap">{{ $run->durationForHumans() ?? '—' }}</td>
                                <td class="small">{{ $run->triggered_by ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('scheduled-jobs.show', $run) }}" class="btn btn-sm btn-ghost-secondary">
                                        <i class="la la-eye"></i> Dettagli
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted text-center py-4">Nessuna esecuzione registrata.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@if ($pending)
    @section('after_scripts')
        <script>setTimeout(() => window.location.reload(), 5000);</script>
    @endsection
@endif

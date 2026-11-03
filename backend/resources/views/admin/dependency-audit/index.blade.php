@extends(backpack_view('blank'))

@php
    $severityBadge = [
        'critical' => ['bg-danger', 'Critica'],
        'high' => ['bg-danger', 'Alta'],
        'medium' => ['bg-warning text-dark', 'Media'],
        'low' => ['bg-info', 'Bassa'],
        'info' => ['bg-light text-dark border', 'Info'],
        'unknown' => ['bg-light text-dark border', 'Non indicata'],
    ];
    $statusBadge = [
        'ok' => ['bg-success', 'Supportato'],
        'security' => ['bg-info', 'Solo sicurezza'],
        'ending' => ['bg-warning text-dark', 'In scadenza'],
        'eol' => ['bg-danger', 'Fuori supporto'],
        'unknown' => ['bg-light text-dark border', 'Non disponibile'],
    ];
    // endoflife.date: a date, true (reached / ongoing) or false (none / ended).
    $eolDate = fn ($value, string $whenTrue, string $whenFalse) => match (true) {
        is_string($value) => \Carbon\Carbon::parse($value)->format('d/m/Y'),
        $value === true => $whenTrue,
        $value === false => $whenFalse,
        default => '—',
    };
@endphp

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-shield-alt"></i> Dependency Audit
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="alert alert-warning bg-light rounded p-4 mb-4">
            <div class="mb-0">
                <div class="mb-2">
                    <i class="la la-info-circle text-info me-2"></i>
                    Il report è generato dal CI a ogni build dell'immagine: è aggiornato al codice in esecuzione, non a oggi
                </div>
                <div>
                    <i class="la la-server text-success me-2"></i>
                    PHP, Laravel, MySQL (o Aurora) e Valkey sono letti da questo ambiente; Node, Next.js e React dall'immagine del frontend
                </div>
            </div>
        </div>

        @if (! $report)
            <div class="alert alert-info">
                <i class="la la-info-circle"></i>
                Nessun report in questa immagine: viene generato dal CI alla build.
                In locale: <code>bash .github/scripts/audit-report.sh</code> dalla radice del repository.
            </div>
        @else
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4 small">
                            <div class="text-muted">Generato il</div>
                            <div>{{ isset($report['generated_at']) ? \Carbon\Carbon::parse($report['generated_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</div>
                            <div class="text-muted mt-2">Versione</div>
                            <div>
                                {{ $report['ref'] ?? '—' }}
                                {{-- A local report's commit may exist only on that machine: no link. --}}
                                @if (! empty($report['commit']) && ! ($report['local'] ?? false))
                                    · <a href="https://github.com/humanbit-dev-org/rays/commit/{{ $report['commit'] }}" target="_blank" rel="noopener" class="font-monospace">{{ substr($report['commit'], 0, 8) }}</a>
                                @elseif (! empty($report['commit']))
                                    · <span class="font-monospace">{{ substr($report['commit'], 0, 8) }}</span>
                                    <span class="badge bg-light text-muted border ms-1">generato in locale</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="text-muted small mb-1">Vulnerabilità</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($counts as $severity => $count)
                                    @continue($count === 0 && in_array($severity, ['info', 'unknown'], true))
                                    <span class="badge {{ $count > 0 ? $severityBadge[$severity][0] : 'bg-light text-muted border' }} fs-6">
                                        {{ $severityBadge[$severity][1] }}: {{ $count }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($reportErrors !== [])
                <div class="alert alert-warning">
                    <div class="fw-bold mb-1"><i class="la la-exclamation-triangle"></i> Parti non verificate in questo report</div>
                    <ul class="mb-0 small">
                        @foreach ($reportErrors as $reportError)
                            <li>{{ $reportError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">
                    <strong>Vulnerabilità</strong>
                    <span class="text-muted small ms-2">composer audit e pnpm audit</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th>Gravità</th>
                                <th>Pacchetto</th>
                                <th>Installata</th>
                                <th>Avviso</th>
                                <th>Corretta in</th>
                                <th>Comando</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vulnerabilities as $item)
                                @php([$class, $label] = $severityBadge[$item['severity'] ?? 'unknown'] ?? $severityBadge['unknown'])
                                <tr>
                                    <td><span class="badge {{ $class }}">{{ $label }}</span></td>
                                    <td>
                                        <span class="font-monospace small">{{ $item['package'] }}</span>
                                        <div class="d-flex gap-1 mt-1">
                                            <span class="badge bg-light text-dark border">{{ $item['ecosystem'] }}</span>
                                            @if ($item['dev'] ?? false)
                                                <span class="badge bg-light text-muted border" title="Solo sviluppo: non va in produzione">dev</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-nowrap small">{{ $item['installed'] ?? '—' }}</td>
                                    <td class="small">
                                        @if (! empty($item['url']))
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['title'] }}</a>
                                        @else
                                            {{ $item['title'] }}
                                        @endif
                                        @if (! empty($item['cve']))
                                            <div class="text-muted">{{ $item['cve'] }}</div>
                                        @endif
                                    </td>
                                    {{-- fixed_status: available | none (no fix published) | unknown (not worked out) --}}
                                    @php($fixedStatus = $item['fixed_status'] ?? (! empty($item['fixed']) ? 'available' : 'unknown'))
                                    <td class="small text-nowrap" title="Versioni colpite: {{ $item['affected'] ?? '—' }}">
                                        @if ($fixedStatus === 'available')
                                            <span class="fw-bold">{{ $item['fixed'] }}</span>
                                        @elseif ($fixedStatus === 'none')
                                            <span class="text-danger">Nessuna correzione pubblicata</span>
                                        @else
                                            <span class="text-muted">Non determinabile</span>
                                        @endif
                                    </td>
                                    @include('admin.dependency-audit._command', ['command' => $item['command'] ?? null])
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted text-center py-4">Nessuna vulnerabilità nota.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <strong>Aggiornamenti major</strong>
                    <span class="text-muted small ms-2">dipendenze dirette con una nuova versione major</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th>Pacchetto</th>
                                <th>Attuale</th>
                                <th>Ultima</th>
                                <th></th>
                                <th>Comando</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($outdated as $item)
                                <tr>
                                    <td>
                                        <span class="font-monospace small">{{ $item['package'] }}</span>
                                        <span class="badge bg-light text-dark border ms-1">{{ $item['ecosystem'] }}</span>
                                    </td>
                                    <td class="text-nowrap small">{{ $item['current'] }}</td>
                                    <td class="text-nowrap small">{{ $item['latest'] ?? '—' }}</td>
                                    <td>
                                        @if ($item['dev'] ?? false)
                                            <span class="badge bg-light text-muted border">dev</span>
                                        @endif
                                        @if (($item['abandoned'] ?? null) !== null)
                                            <span class="badge bg-warning text-dark" title="{{ $item['abandoned'] !== '' ? 'Sostituto: '.$item['abandoned'] : 'Nessun sostituto indicato' }}">abbandonato</span>
                                        @endif
                                    </td>
                                    @include('admin.dependency-audit._command', ['command' => $item['command'] ?? null])
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center py-4">Nessun aggiornamento major disponibile.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <strong>Runtime</strong>
                <span class="text-muted small ms-2">versioni e fine supporto (endoflife.date)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th>Componente</th>
                            <th>Versione</th>
                            <th>Ultima del ciclo</th>
                            <th>Supporto attivo fino a</th>
                            <th>Fine supporto</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runtimes as $row)
                            @php([$class, $label] = $statusBadge[$row['status']])
                            <tr>
                                <td>
                                    {{ $row['name'] }}
                                    <div class="text-muted small">{{ $row['source'] === 'live' ? 'questo ambiente' : 'immagine frontend' }}</div>
                                </td>
                                <td class="text-nowrap">
                                    {{ $row['version'] ?? 'non disponibile' }}
                                    {{-- Aurora: the MySQL version it is compatible with. --}}
                                    @if ($row['note'] ?? null)
                                        <div class="text-muted small">{{ $row['note'] }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap small">{{ $row['cycle']['latest'] ?? '—' }}</td>
                                <td class="text-nowrap small">{{ $row['cycle'] ? $eolDate($row['cycle']['support'] ?? null, 'in corso', 'terminato') : '—' }}</td>
                                <td class="text-nowrap small">{{ $row['cycle'] ? $eolDate($row['cycle']['eol'] ?? null, 'raggiunta', 'non annunciata') : '—' }}</td>
                                <td><span class="badge {{ $class }}">{{ $label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('after_scripts')
    <script>
        // Copy buttons of the Comando column: the command goes to the clipboard,
        // the icon confirms for a moment.
        document.querySelectorAll('[data-copy-command]').forEach((button) => {
            button.addEventListener('click', async () => {
                const icon = button.querySelector('i');

                try {
                    await navigator.clipboard.writeText(button.dataset.copyCommand);
                    icon.className = 'la la-check text-success';
                } catch (error) {
                    icon.className = 'la la-times text-danger';
                }

                setTimeout(() => { icon.className = 'la la-copy'; }, 1500);
            });
        });
    </script>
@endsection

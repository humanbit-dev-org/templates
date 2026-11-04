@extends(backpack_view('blank'))

@php
$defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    $crud->entity_name_plural => false,
];
$breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
$archiveData = $crud->getOperationSetting('archiveData') ?? [];
$translations = $archiveData['translations'] ?? null;
$filters = $archiveData['filters'] ?? ['code' => '', 'page_id' => ''];
$pageOptions = $archiveData['pageOptions'] ?? [];
$placeholderSamples = $archiveData['placeholderSamples'] ?? [];
$totals = $archiveData['totals'] ?? ['records' => 0];

$renderResolvedValue = static function (?string $value) use ($placeholderSamples): string {
    if ($value === null || trim($value) === '') {
        return '';
    }

    $resolved = preg_replace_callback(
        '/\{\$([a-zA-Z_][a-zA-Z0-9_]*)(?:\.([a-zA-Z_][a-zA-Z0-9_]*))?\}/',
        static function (array $matches) use ($placeholderSamples): string {
            $key = isset($matches[2]) && $matches[2] !== '' ? $matches[1].'.'.$matches[2] : $matches[1];
            $sample = $placeholderSamples[$key] ?? '{$'.$key.'}';

            return e((string) $sample);
        },
        $value
    );

    return $resolved ?? $value;
};
@endphp

@push('after_styles')
<link rel="stylesheet" href="/static/css/translate_archive.css">
@endpush

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="mb-0" bp-section="page-heading">
        <i class="la la-archive"></i> Translate Archive
    </h1>
    <p class="ms-2 mb-0 text-muted">Archivio read-only sincronizzato dai JSON frontend</p>
</section>
@endsection

@section('content')
<div class="container-fluid translate-archive">
    <div class="card archive-toolbar-card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ url($crud->route) }}" class="row g-2 align-items-end">
                <div class="col-xl-4 col-md-6">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" value="{{ $filters['code'] }}" class="form-control" placeholder="Es. main_initial">
                </div>
                <div class="col-xl-4 col-md-6">
                    <label class="form-label">Page</label>
                    <select name="page_id" class="form-select">
                        <option value="">Tutte</option>
                        @foreach($pageOptions as $id => $name)
                            <option value="{{ $id }}" {{ (string) $filters['page_id'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-4 col-md-6 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="la la-search"></i> Filtra
                    </button>
                    <a class="btn btn-outline-secondary w-100" href="{{ url($crud->route) }}">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="archive-summary mb-3">
        <span class="summary-pill"><strong>{{ number_format((int) $totals['records']) }}</strong> records</span>
    </div>

    <div class="view-mode-switch mb-3">
        <button type="button" class="btn btn-sm btn-primary js-mode-btn is-active" data-mode="raw">Raw JSON</button>
        <button type="button" class="btn btn-sm btn-outline-primary js-mode-btn" data-mode="resolved">Valore risolto</button>
    </div>

    @if($translations && $translations->count() > 0)
        <div class="archive-grid js-archive-grid mode-raw">
            @foreach($translations as $row)
                @php
                    $pageName = $row->page?->name ?? 'all';
                    $itNode = ['it' => $row->it ?? '', 'text_it' => $row->text_it ?? ''];
                    $enNode = ['en' => $row->en ?? '', 'text_en' => $row->text_en ?? ''];
                    $hasItNode = collect($itNode)->contains(static fn ($value): bool => trim((string) $value) !== '');
                    $hasEnNode = collect($enNode)->contains(static fn ($value): bool => trim((string) $value) !== '');
                    $itJson = json_encode(['it' => $row->it ?? '', 'text_it' => $row->text_it ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $enJson = json_encode(['en' => $row->en ?? '', 'text_en' => $row->text_en ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @endphp
                <article class="archive-card">
                    <header class="archive-card-head">
                        <div class="archive-main-meta">
                            <h3 class="archive-code">{{ $row->code }}</h3>
                            <div class="archive-badges">
                                <span class="badge-archive badge-page">{{ $pageName }}</span>
                            </div>
                        </div>
                    </header>

                    <div class="json-columns">
                        @if($hasItNode)
                            <section class="json-pane">
                                <div class="json-pane-title">IT node</div>
                                <pre class="json-block js-json-block">{{ $itJson ?: '{}' }}</pre>
                                <div class="rendered-block">
                                    @if(trim((string) ($row->it ?? '')) !== '')
                                        <div class="resolved-row">{!! $renderResolvedValue($row->it) !!}</div>
                                    @endif
                                    @if(trim((string) ($row->text_it ?? '')) !== '')
                                        <div class="resolved-row">{!! $renderResolvedValue($row->text_it) !!}</div>
                                    @endif
                                </div>
                            </section>
                        @endif
                        @if($hasEnNode)
                            <section class="json-pane">
                                <div class="json-pane-title">EN node</div>
                                <pre class="json-block js-json-block">{{ $enJson ?: '{}' }}</pre>
                                <div class="rendered-block">
                                    @if(trim((string) ($row->en ?? '')) !== '')
                                        <div class="resolved-row">{!! $renderResolvedValue($row->en) !!}</div>
                                    @endif
                                    @if(trim((string) ($row->text_en ?? '')) !== '')
                                        <div class="resolved-row">{!! $renderResolvedValue($row->text_en) !!}</div>
                                    @endif
                                </div>
                            </section>
                        @endif
                        @if(!$hasItNode && !$hasEnNode)
                            <section class="json-pane json-pane-empty">
                                <div class="json-pane-title">Node</div>
                                <div class="json-empty-note">Nessun contenuto valorizzato</div>
                            </section>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="archive-pagination mt-3">
            {{ $translations->links() }}
        </div>
    @else
        <div class="card">
            <div class="card-body text-muted">
                Nessun translate trovato con i filtri correnti.
            </div>
        </div>
    @endif
</div>
@endsection

@push('after_scripts')
<script>
    (function () {
        function highlightJson(text) {
            const escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            const tokenRegex = /("(?:\\u[0-9a-fA-F]{4}|\\[^u]|[^\\"])*"\s*:?)|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+\-]?\d+)?/g;

            return escaped.replace(tokenRegex, function (match) {
                if (match.endsWith(':')) {
                    return '<span class="json-key">' + match + '</span>';
                }
                if (match.startsWith('"')) {
                    return '<span class="json-string">' + match + '</span>';
                }
                if (match === 'true' || match === 'false') {
                    return '<span class="json-boolean">' + match + '</span>';
                }
                if (match === 'null') {
                    return '<span class="json-null">' + match + '</span>';
                }

                return '<span class="json-number">' + match + '</span>';
            });
        }

        document.querySelectorAll('.js-json-block').forEach(function (el) {
            el.innerHTML = highlightJson(el.textContent || '');
        });

        const grid = document.querySelector('.js-archive-grid');
        const buttons = document.querySelectorAll('.js-mode-btn');
        if (!grid || buttons.length === 0) {
            return;
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const mode = btn.getAttribute('data-mode');
                grid.classList.remove('mode-raw', 'mode-resolved');
                grid.classList.add(mode === 'resolved' ? 'mode-resolved' : 'mode-raw');

                buttons.forEach(function (other) {
                    other.classList.remove('is-active', 'btn-primary');
                    other.classList.add('btn-outline-primary');
                });
                btn.classList.add('is-active', 'btn-primary');
                btn.classList.remove('btn-outline-primary');
            });
        });
    })();
</script>
@endpush

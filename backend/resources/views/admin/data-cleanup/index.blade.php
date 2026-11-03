@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-broom"></i> Pulizia Dati
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="la la-check-circle"></i> {!! session('success') !!}
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
                    Ogni comando mostra un'anteprima read-only prima dell'esecuzione
                </div>
                <div class="mb-2">
                    <i class="la la-shield-alt text-success me-2"></i>
                    Prima di ogni esecuzione viene creato un backup automatico delle tabelle coinvolte
                </div>
                <div>
                    <i class="la la-history text-primary me-2"></i>
                    I backup sono disponibili nella sezione <a href="{{ route('csv-rollback.index') }}">CSV Rollback</a> per il ripristino
                </div>
            </div>
        </div>

        <div class="row g-3">
            @foreach ($commands as $commandId => $command)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start mb-2">
                                <i class="{{ $command['icon'] }} la-2x text-primary me-3"></i>
                                <div>
                                    <h6 class="card-title mb-1">{{ $command['title'] }}</h6>
                                    <p class="card-text text-muted small mb-0">{{ $command['description'] }}</p>
                                </div>
                            </div>

                            <div class="mb-2">
                                @foreach ($command['affected_tables'] as $table)
                                    <span class="badge bg-light text-dark border me-1">{{ $table }}</span>
                                @endforeach
                            </div>

                            @if ($command['has_parameters'])
                                <form action="{{ route('data-cleanup.preview', $commandId) }}" method="GET" class="mt-auto">
                                    @csrf
                                    <div class="row g-2 mb-2">
                                        @foreach ($command['parameters'] as $paramName => $paramConfig)
                                            <div class="col">
                                                <label class="form-label small mb-1">{{ $paramConfig['label'] }}</label>
                                                <input type="{{ $paramConfig['type'] }}"
                                                       name="{{ $paramName }}"
                                                       class="form-control form-control-sm"
                                                       @if ($paramConfig['required']) required @endif
                                                       placeholder="{{ $paramConfig['label'] }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="la la-eye"></i> Anteprima
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('data-cleanup.preview', $commandId) }}" class="btn btn-sm btn-outline-primary mt-auto">
                                    <i class="la la-eye"></i> Anteprima
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

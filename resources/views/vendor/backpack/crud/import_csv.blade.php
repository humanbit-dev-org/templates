@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            <span class="text-capitalize">Importa CSV per {{ $crud }}</span>
        </h2>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Carica il file CSV</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ url($crud_route.'/import-csv/analyze') }}" enctype="multipart/form-data">
                        @csrf
                        
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        <div class="form-group">
                            <label for="csv_file">Seleziona un file CSV</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.txt" required>
                            <small class="form-text text-muted">
                                Il file deve essere in formato CSV con le intestazioni nella prima riga.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Analizza File</button>
                            <a href="{{ url($crud_route) }}" class="btn btn-default">Annulla</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection 
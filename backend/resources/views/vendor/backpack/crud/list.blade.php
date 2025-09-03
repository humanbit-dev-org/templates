@extends(backpack_view('blank'))

@php
$defaultBreadcrumbs = [
trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
$crud->entity_name_plural => url($crud->route),
trans('backpack::crud.list') => false,
];

// if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
$breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
  <h1 class="text-uppercase mb-0" bp-section="page-heading">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</h1>
  <p class="ms-2 ml-2 mb-0" id="datatable_info_stack" bp-section="page-subheading">{!! $crud->getSubheading() ?? '' !!}</p>
</section>
@endsection

@section('content')
{{-- Default box --}}
<div class="row" bp-section="crud-operation-list">

  {{-- THE ACTUAL CONTENT --}}
  <div class="{{ $crud->getListContentClass() }}">

    <div class="row mb-2 align-items-center">

      @if($crud->getOperationSetting('searchableTable'))
      <div class="col-sm-3">
        <div id="datatable_search_stack" class="mt-sm-0 mt-2 d-print-none">
          <div class="input-icon">
            <span class="input-icon-addon">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                <path d="M21 21l-6 -6"></path>
              </svg>
            </span>
            <input type="search" class="form-control" placeholder="{{ trans('backpack::crud.search') }}..." />
          </div>
        </div>
      </div>
      @endif
      <div class="col-sm-3">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false" aria-controls="filterPanel">
          <i class="la la-sliders"></i> {{ trans('backpack::crud.filters') }}
        </button>
      </div>
      <div class="col-sm-9 text-end">
        @if ( $crud->buttons()->where('stack', 'top')->count() || $crud->exportButtons())
        <div class="d-print-none {{ $crud->hasAccess('create')?'with-border':'' }}">
          @include('crud::inc.button_stack', ['stack' => 'top'])

        </div>
        @endif
      </div>
    </div>

    {{-- Backpack List Filters --}}
    @if ($crud->filtersEnabled())
    @include('crud::inc.filters_navbar')
    @endif



    @include('crud::inc.filters-panel')

    <div class="{{ backpack_theme_config('classes.tableWrapper') }}">
      <table
        id="crudTable"
        class="{{ backpack_theme_config('classes.table') ?? 'table table-striped table-hover nowrap rounded card-table table-vcenter card d-table shadow-xs border-xs' }}"
        data-responsive-table="{{ (int) $crud->getOperationSetting('responsiveTable') }}"
        data-has-details-row="{{ (int) $crud->getOperationSetting('detailsRow') }}"
        data-has-bulk-actions="{{ (int) $crud->getOperationSetting('bulkActions') }}"
        data-has-line-buttons-as-dropdown="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdown') }}"
        data-line-buttons-as-dropdown-minimum="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdownMinimum') }}"
        data-line-buttons-as-dropdown-show-before-dropdown="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdownShowBefore') }}"
        cellspacing="0">
        <thead>
          <tr>
            {{-- Table columns --}}
            @foreach ($crud->columns() as $column)
            @php
            $exportOnlyColumn = $column['exportOnlyColumn'] ?? false;
            $visibleInTable = $column['visibleInTable'] ?? ($exportOnlyColumn ? false : true);
            $visibleInModal = $column['visibleInModal'] ?? ($exportOnlyColumn ? false : true);
            $visibleInExport = $column['visibleInExport'] ?? true;
            $forceExport = $column['forceExport'] ?? (isset($column['exportOnlyColumn']) ? true : false);
            @endphp
            <th
              data-orderable="{{ var_export($column['orderable'], true) }}"
              data-priority="{{ $column['priority'] }}"
              data-column-name="{{ $column['name'] }}"
              {{--
                    data-visible-in-table => if developer forced column to be in the table with 'visibleInTable => true'
                    data-visible => regular visibility of the column
                    data-can-be-visible-in-table => prevents the column to be visible into the table (export-only)
                    data-visible-in-modal => if column appears on responsive modal
                    data-visible-in-export => if this column is exportable
                    data-force-export => force export even if columns are hidden
                    --}}

              data-visible="{{ $exportOnlyColumn ? 'false' : var_export($visibleInTable) }}"
              data-visible-in-table="{{ var_export($visibleInTable) }}"
              data-can-be-visible-in-table="{{ $exportOnlyColumn ? 'false' : 'true' }}"
              data-visible-in-modal="{{ var_export($visibleInModal) }}"
              data-visible-in-export="{{ $exportOnlyColumn ? 'true' : ($visibleInExport ? 'true' : 'false') }}"
              data-force-export="{{ var_export($forceExport) }}">
              {{-- Bulk checkbox --}}
              @if($loop->first && $crud->getOperationSetting('bulkActions'))
              {!! View::make('crud::columns.inc.bulk_actions_checkbox')->render() !!}
              @endif
              {!! $column['label'] !!}
            </th>
            @endforeach

            @if ( $crud->buttons()->where('stack', 'line')->count() )
            <th data-orderable="false"
              data-priority="{{ $crud->getActionsColumnPriority() }}"
              data-visible-in-export="false"
              data-action-column="true">{{ trans('backpack::crud.actions') }}</th>
            @endif
          </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
          <tr>
            {{-- Table columns --}}
            @foreach ($crud->columns() as $column)
            <th>
              {{-- Bulk checkbox --}}
              @if($loop->first && $crud->getOperationSetting('bulkActions'))
              {!! View::make('crud::columns.inc.bulk_actions_checkbox')->render() !!}
              @endif
              {!! $column['label'] !!}
            </th>
            @endforeach

            @if ( $crud->buttons()->where('stack', 'line')->count() )
            <th>{{ trans('backpack::crud.actions') }}</th>
            @endif
          </tr>
        </tfoot>
      </table>
    </div>

    @if ( $crud->buttons()->where('stack', 'bottom')->count() )
    <div id="bottom_buttons" class="d-print-none text-sm-left">
      @include('crud::inc.button_stack', ['stack' => 'bottom'])
      <div id="datatable_button_stack" class="float-right float-end text-right hidden-xs"></div>
    </div>
    @endif

  </div>

</div>

@endsection

@section('after_styles')
{{-- DATA TABLES --}}
@basset('https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css')
@basset('https://cdn.datatables.net/fixedheader/3.3.1/css/fixedHeader.dataTables.min.css')
@basset('https://cdn.datatables.net/responsive/2.4.0/css/responsive.dataTables.min.css')

{{-- CRUD LIST CONTENT - crud_list_styles stack --}}
@stack('crud_list_styles')
@endsection

@section('after_scripts')
@include('crud::inc.datatables_logic')

{{-- CRUD LIST CONTENT - crud_list_scripts stack --}}
@stack('crud_list_scripts')

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Ottiene tutti i parametri dell'URL corrente
    const urlParams = new URLSearchParams(window.location.search);

    // Itera su tutti i parametri
    for (const [key, value] of urlParams.entries()) {
      // Ignora parametri di sistema e valori vuoti
      if (key !== 'page' && key !== 'persistent-table' && value !== '') {
        // Trova tutte le label con class="filter-label" associate a questo campo
        const labels = document.querySelectorAll(`.filter-label[for="${key}"]`);

        // Se non ci sono label con for= esatto, cerca label all'interno del container del campo
        if (labels.length === 0) {
          const fieldContainers = document.querySelectorAll(`[name="${key}"]`);
          fieldContainers.forEach(container => {
            const parentCol = container.closest('.col-lg-3, .col-md-4, .col-sm-6');
            if (parentCol) {
              const fieldLabels = parentCol.querySelectorAll('.filter-label');
              fieldLabels.forEach(label => {
                // Evidenzia solo la label principale con classe filter-label
                label.classList.add('filter-active');
              });
            }
          });
        } else {
          // Applica classe alle label trovate direttamente
          labels.forEach(label => {
            label.classList.add('filter-active');
          });
        }
      }
    }

    // Assicurarsi che solo un collapse sia aperto alla volta
    const accordionBtns = document.querySelectorAll('.accordion-button');
    accordionBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        // Quando un pulsante viene cliccato, chiudi tutti gli altri collapse
        const target = this.getAttribute('data-bs-target');
        document.querySelectorAll('.accordion-collapse.show').forEach(collapse => {
          const collapseId = '#' + collapse.id;
          if (collapseId !== target) {
            // Trova il pulsante associato a questo collapse
            const button = document.querySelector(`[data-bs-target="${collapseId}"]`);
            if (button) {
              button.classList.add('collapsed');
              button.setAttribute('aria-expanded', 'false');
            }
            // Chiudi il collapse senza animazione per evitare conflitti
            collapse.classList.remove('show');
          }
        });
      });
    });
    
    // Verifica se ci sono filtri attivi e aggiungi classe al bottone Aggiungi
    const hasActiveFilters = Array.from(urlParams.entries()).some(([key, value]) => 
      key !== 'page' && key !== 'persistent-table' && value !== ''
    );
    
    if (hasActiveFilters) {
      const createButtons = document.querySelectorAll('.btn[bp-button="create"]');
      createButtons.forEach(button => {
        button.classList.add('has-active-filters');
        button.setAttribute('data-warning-message', "{{ trans('backpack::crud.warning_creating_with_filters') }}");
      });
    }
  });
</script>
@endsection
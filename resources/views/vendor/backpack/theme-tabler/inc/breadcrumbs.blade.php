@if (backpack_theme_config('breadcrumbs') && isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs))
	<nav aria-label="breadcrumb" class="d-none d-lg-block m-5 p-0">
	  <ol class="breadcrumb bg-transparent mx-3 {{ backpack_theme_config('html_direction') == 'rtl' ? 'justify-content-start' : 'justify-content-end' }}">
	  	@foreach ($breadcrumbs as $label => $link)
	  		@if ($link)
			    <li class="breadcrumb-item text-capitalize" ><a href="{{ $link }}" >{{ $label }}</a></li>
	  		@else
			    <li class="breadcrumb-item text-capitalize active" aria-current="page" style="color:{!! backpack_theme_config('project_logo_color') !!};">{{ $label }}</li>
	  		@endif
	  	@endforeach
	  </ol>
	</nav>
@endif

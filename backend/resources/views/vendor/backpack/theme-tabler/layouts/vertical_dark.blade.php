<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}" dir="{{ backpack_theme_config('html_direction') }}">

<head>
    @include(backpack_view('inc.head'))
</head>

<body class="{{ backpack_theme_config('classes.body') }}" bp-layout="vertical-dark">

@include(backpack_view('layouts.partials.light_dark_mode_logic'))

<div class="page">

    @include(backpack_view('layouts._vertical_dark.menu_container'))

    <div class="page-wrapper">
        <div class="row w-100 align-items-center" style="background-color:{!! backpack_theme_config('project_color') !!};">
            <div class="col-7 d-flex align-items-center">
                <h1 class="navbar-brand d-none d-lg-block align-self-center m-5" style="color:{!! backpack_theme_config('project_logo_color') !!};">
                    <a class="h2 text-decoration-none mb-0" href="{{ url(backpack_theme_config('home_link')) }}" title="{{ backpack_theme_config('project_name') }}">
                        {!! backpack_theme_config('project_logo') !!}
                    </a>
                </h1>
            </div>
            <div class="col-5 d-flex align-items-center justify-content-end">
                @includeWhen(isset($breadcrumbs), backpack_view('inc.breadcrumbs'))
                @if(config('backpack.language-switcher.setup_routes'))
                    @include('backpack.language-switcher::language-switcher')
                @endif
            </div>
        </div>
        <div class="page-body">
            
            <main class="{{ backpack_theme_config('options.useFluidContainers') ? 'container-fluid' : 'container-xl' }}">
                @yield('before_breadcrumbs_widgets')
                
                
                @yield('after_breadcrumbs_widgets')
                @yield('header')

                <div class="container-fluid animated fadeIn">
                    @yield('before_content_widgets')
                    @yield('content')
                    @yield('after_content_widgets')
                </div>
            </main>
        </div>

        @include(backpack_view('inc.footer'))
    </div>
</div>

@yield('before_scripts')
@stack('before_scripts')

@include(backpack_view('inc.scripts'))
@include(backpack_view('inc.theme_scripts'))

@yield('after_scripts')
@stack('after_scripts')
</body>
</html>

@extends(backpack_view('blank'))

@if (backpack_auth()->user()->getAttribute('backpack_role') != 'guest')
    @php
        $widgets['before_content'][] = [
            'type' => 'jumbotron',
            'heading' => "Templates - Admin Panel",
            'heading_class' =>
                'display-3 ' . (backpack_theme_config('layout') === 'horizontal_overlap' ? ' text-white' : ''),
            // 'content' => trans('backpack::base.use_sidebar'),
            'content_class' => backpack_theme_config('layout') === 'horizontal_overlap' ? 'text-white' : '',
            // 'button_link' => backpack_url('logout'),
            // 'button_text' => trans('backpack::base.logout'),
        ];

        // $widgets['before_content'][] = [
        //     'type'        => 'view',
        //     'view'        => backpack_view('inc.getting_started'),
        // ];

        // if(Auth::guard('backpack')->user()->getAttribute('backpack_role') == "developer") {
        //     $repository = 'humanbit-dev-org/all-together-pay';
        //     $gitHubService = new \App\Services\GitHubService();
        //     $commits = $gitHubService->getCommits($repository, 5);
        //     $issues = $gitHubService->getIssues($repository, 10);

        //     if($commits != null && $issues != null) {
        //         $widgets['after_content'][] = [
        //             'type' => 'view',
        //             'view' => backpack_view('widgets.github_activity'),
        //             'data' => compact('commits', 'issues'),
        //         ];
        //     }
        // }
    @endphp
@else
    @php
        $widgets['before_content'][] = [
                'type' => 'jumbotron',
                'heading' => "Attendi che un amministratore ti abiliti",
                'heading_class' =>
                    'display-3 ' . (backpack_theme_config('layout') === 'horizontal_overlap' ? ' text-white' : ''),
                'content_class' => backpack_theme_config('layout') === 'horizontal_overlap' ? 'text-white' : '',
            ];
    @endphp
@endif

@section('content')
@endsection
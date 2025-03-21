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

        $widgets['before_content'][] = [
            'type' => 'div',
            'class' => 'row mt-3',
            'content' => [
                [
                    'type' => 'progress_white',
                    'class' => 'card mb-3',
                    'statusBorder' => 'start',
                    'accentColor' => 'primary',
                    'ribbon' => ['top', 'la-user'],
                    'progressClass' => 'progress-bar',
                    'value' => \App\Models\User::count(),
                    'description' =>
                        '<a href="' .
                        backpack_url('user') .
                        '" class="btn btn-primary btn-sm">Registered users <i class="ms-1 las la-level-up-alt"></i></a>',
                    'progress' => (100 * (int) \App\Models\User::count()) / 100,
                    'hint' => 100 - \App\Models\User::count() . ' more until next milestone.',
                ],
                [
                    'type' => 'progress_white',
                    'class' => 'card mb-3',
                    'statusBorder' => 'start',
                    'accentColor' => 'green',
                    'ribbon' => ['top', 'la-info-circle'],
                    'progressClass' => 'progress-bar',
                    'value' => \App\Models\Group::count(),
                    'description' =>
                        '<a href="' .
                        backpack_url('group') .
                        '" class="btn btn-green btn-sm">Groups created <i class="ms-1 las la-level-up-alt"></i></a>',
                    'progress' => (100 * (int) \App\Models\Group::count()) / 50,
                    'hint' => 50 - \App\Models\Group::count() . ' more until next milestone.',
                ],
                [
                    'type' => 'progress_white',
                    'class' => 'card mb-3',
                    'statusBorder' => 'start',
                    'accentColor' => 'yellow',
                    'ribbon' => ['top', 'la-tag'],
                    'progressClass' => 'progress-bar',
                    'value' => \App\Models\Order::count(),
                    'description' =>
                        '<a href="' .
                        backpack_url('order') .
                        '" class="btn btn-yellow btn-sm">Orders made <i class="ms-1 las la-level-up-alt"></i></a>',
                    'progress' => (100 * (int) \App\Models\Order::count()) / 500,
                    'hint' => 500 - \App\Models\Order::count() . ' more until next milestone.',
                ],
            ],
        ];

        $widgets['before_content'][] = [
            'type'        => 'view',
            'view'        => backpack_view('inc.getting_started'),
        ];

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
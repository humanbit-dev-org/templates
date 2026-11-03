{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i>
        {{ trans('backpack::base.dashboard') }}</a></li>

@if (backpack_auth()->user()->backpack_role != 'guest')
    @php
        // Define always-open menu sections (no dropdown)
        $alwaysOpenSections = [
            'RAYS' => [
                ['name' => 'User', 'title' => 'Anagrafiche', 'icon' => 'la la-user-circle', 'url' => 'user'],
            ],
        ];

        // Define dropdown menu sections
        $dropdownSections = [
            'Contents' => [
                'icon' => 'la la-folder',
                'items' => [
                    ['name' => 'Contatto', 'title' => 'Contatti', 'icon' => 'la la-envelope', 'url' => 'contatto'],
                    ['name' => 'Institutional', 'title' => 'Institutionals', 'icon' => 'la la-paste', 'url' => 'institutional'],
                ]
            ],
            'Utilities' => [
                'icon' => 'la la-tools',
                'items' => [
                    ['name' => 'Media', 'title' => 'Media', 'icon' => 'la la-photo-video', 'url' => 'media'],
                    ['name' => 'Attachment', 'title' => 'Attachments', 'icon' => 'la la-paperclip', 'url' => 'attachment'],
                    ['name' => 'Metadata', 'title' => 'Metadata', 'icon' => 'la la-sitemap', 'url' => 'metadata'],
                    ['name' => 'Translate', 'title' => 'Translates', 'icon' => 'la la-language', 'url' => 'translate'],
                    ['name' => 'Notification', 'title' => 'Notifications', 'icon' => 'la la-bell', 'url' => 'notification'],
                ]
            ],
        ];

        // Developer-only sections (not model-based, requires developer role)
        $developerSections = [
            [
                'title' => 'Admin',
                'icon' => 'la la-user-shield',
                'items' => [
                    ['name' => 'Page', 'title' => 'Pages', 'icon' => 'la la-pager', 'url' => 'page'],
                ]
            ],
            [
                'title' => 'Permissions',
                'icon' => 'la la-eye',
                'items' => [
                    ['name' => 'BackpackRole', 'title' => 'Backend Roles', 'icon' => 'la la-user-cog', 'url' => 'backpack-role'],
                    ['name' => 'Role', 'title' => 'Web Roles', 'icon' => 'la la-user-cog', 'url' => 'role'],
                    ['name' => 'ModelPermission', 'title' => 'Model Permissions', 'icon' => 'la la-key', 'url' => 'model-permission'],
                    ['name' => 'UserTwoFactor', 'title' => 'User Two Factor', 'icon' => 'la la-key', 'url' => 'user-two-factor'],
                ]
            ],
            [
                'title' => 'Tools',
                'icon' => 'la la-code',
                'items' => [
                    // With CloudWatch on, straight to the console in a new tab.
                    ['title' => 'System Logs', 'icon' => 'la la-file-text', 'url' => 'logs', ...(filled(config('logging.cloudwatch.group'))
                        ? ['link' => \App\Http\Controllers\Admin\Action\LogAction::cloudWatchConsoleUrl(config('logging.cloudwatch.group')), 'target' => '_blank']
                        : [])],
                    ['title' => 'Upload CSV', 'icon' => 'la la-upload', 'url' => 'csv-upload'],
                    ['title' => 'CSV Rollback', 'icon' => 'la la-history', 'url' => 'csv-rollback'],
                    ['title' => 'Data Cleanup', 'icon' => 'la la-broom', 'url' => 'data-cleanup'],
                    ...(config('scheduler_monitor.enabled')
                        ? [['title' => 'Scheduled Jobs', 'icon' => 'la la-clock', 'url' => 'scheduled-jobs']]
                        : []),
                    ...(config('dependency_audit.enabled')
                        ? [['title' => 'Dependency Audit', 'icon' => 'la la-shield-alt', 'url' => 'dependency-audit']]
                        : []),
                ]
            ],
        ];
    @endphp

    {{-- Render always-open menu sections (RAYS, Banca) --}}
    @foreach ($alwaysOpenSections as $sectionTitle => $items)
        @if (\App\Http\Concerns\ChecksBackpackPermissions::userCanAccessAnyMenu($items))
            <x-backpack::menu-separator title="{{ $sectionTitle }}" />
            
            @foreach ($items as $item)
                @if (\App\Http\Concerns\ChecksBackpackPermissions::userCanAccessMenu($item['name']))
                    <x-backpack::menu-item 
                        title="{{ $item['title'] }}" 
                        icon="{{ $item['icon'] }}" 
                        :link="backpack_url($item['url'])" />
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Render dropdown menu sections (Contents, Utilities, Admin) --}}
    @php
        $hasAnyDropdownSection = false;
        foreach ($dropdownSections as $sectionData) {
            if (\App\Http\Concerns\ChecksBackpackPermissions::userCanAccessAnyMenu($sectionData['items'])) {
                $hasAnyDropdownSection = true;
                break;
            }
        }
    @endphp
    
    @if ($hasAnyDropdownSection)
        <x-backpack::menu-separator title="Gestione" />
        
        @foreach ($dropdownSections as $sectionTitle => $sectionData)
            @if (\App\Http\Concerns\ChecksBackpackPermissions::userCanAccessAnyMenu($sectionData['items']))
                <x-backpack::menu-dropdown title="{{ $sectionTitle }}" icon="{{ $sectionData['icon'] }}">
                    @foreach ($sectionData['items'] as $item)
                        @if (\App\Http\Concerns\ChecksBackpackPermissions::userCanAccessMenu($item['name']))
                            <x-backpack::menu-dropdown-item 
                                title="{{ $item['title'] }}" 
                                icon="{{ $item['icon'] }}" 
                                :link="backpack_url($item['url'])" />
                        @endif
                    @endforeach
                </x-backpack::menu-dropdown>
            @endif
        @endforeach
    @endif

    <hr class="my-4 border-top border-light opacity-50"/>

    {{-- Render developer-only sections --}}
    @if (\App\Http\Concerns\ChecksBackpackPermissions::isDeveloper())
        <x-backpack::menu-separator title="Developer" />
        
        @foreach ($developerSections as $dropdown)
            @php
                // Check if dropdown should be shown
                $shouldShow = false;
                if (isset($dropdown['items'])) {
                    // For items with 'name', check permissions
                    $itemsWithName = array_filter($dropdown['items'], fn($item) => isset($item['name']));
                    if (!empty($itemsWithName)) {
                        $shouldShow = \App\Http\Concerns\ChecksBackpackPermissions::userCanAccessAnyMenu($itemsWithName);
                    } else {
                        // For items without 'name' (like Developer Tools), always show if developer
                        $shouldShow = true;
                    }
                }
            @endphp
            
            @if ($shouldShow)
                <x-backpack::menu-dropdown title="{{ $dropdown['title'] }}" icon="{{ $dropdown['icon'] }}">
                    @foreach ($dropdown['items'] as $item)
                        @if (isset($item['name']))
                            @if (\App\Http\Concerns\ChecksBackpackPermissions::userCanAccessMenu($item['name']))
                                <x-backpack::menu-dropdown-item 
                                    title="{{ $item['title'] }}" 
                                    icon="{{ $item['icon'] }}" 
                                    :link="$item['link'] ?? backpack_url($item['url'])"
                                    :target="$item['target'] ?? null" />
                            @endif
                        @else
                            <x-backpack::menu-dropdown-item 
                                title="{{ $item['title'] }}" 
                                icon="{{ $item['icon'] }}" 
                                :link="$item['link'] ?? backpack_url($item['url'])"
                                :target="$item['target'] ?? null" />
                        @endif
                    @endforeach
                </x-backpack::menu-dropdown>
            @endif
        @endforeach
    @endif
@endif
@include(backpack_view('inc.footer'))
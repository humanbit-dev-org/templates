{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i>
        {{ trans('backpack::base.dashboard') }}</a></li>

@if (backpack_auth()->user()->backpack_role != 'guest')
    @php
        // Define menu sections with their items
        $menuSections = [
            'Content' => [
                ['name' => 'Article', 'title' => 'Articles', 'icon' => 'la la-copy', 'url' => 'article'],
                ['name' => 'Institutional', 'title' => 'Institutionals', 'icon' => 'la la-paste', 'url' => 'institutional'],
            ],
            'Utilities' => [
                ['name' => 'Media', 'title' => 'Media', 'icon' => 'la la-photo-video', 'url' => 'media'],
                ['name' => 'Attachment', 'title' => 'Attachments', 'icon' => 'la la-paperclip', 'url' => 'attachment'],
                ['name' => 'Metadata', 'title' => 'Metadata', 'icon' => 'la la-sitemap', 'url' => 'metadata'],
                ['name' => 'Translate', 'title' => 'Translates', 'icon' => 'la la-language', 'url' => 'translate'],
            ],
            'Admin' => [
                ['name' => 'User', 'title' => 'Users', 'icon' => 'la la-user-circle', 'url' => 'user'],
                ['name' => 'Page', 'title' => 'Pages', 'icon' => 'la la-pager', 'url' => 'page'],
            ]
        ];

        // Define dropdown menus
        $dropdownMenus = [
            [
                'title' => 'Permissions',
                'icon' => 'la la-eye',
                'items' => [
                    ['name' => 'BackpackRole', 'title' => 'Backend Roles', 'icon' => 'la la-user-cog', 'url' => 'backpack-role'],
                    ['name' => 'Role', 'title' => 'Web Roles', 'icon' => 'la la-user-cog', 'url' => 'role'],
                    ['name' => 'ModelPermission', 'title' => 'Model Permissions', 'icon' => 'la la-key', 'url' => 'model-permission'],
                ]
            ]
        ];
    @endphp

    {{-- Render regular menu sections --}}
    @foreach ($menuSections as $sectionTitle => $items)
        @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessAnyMenu($items))
            <x-backpack::menu-separator title="{{ $sectionTitle }}" />
            
            @foreach ($items as $item)
                @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu($item['name']))
                    <x-backpack::menu-item 
                        title="{{ $item['title'] }}" 
                        icon="{{ $item['icon'] }}" 
                        :link="backpack_url($item['url'])" />
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Render dropdown menus --}}
    @foreach ($dropdownMenus as $dropdown)
        @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessAnyMenu($dropdown['items']))
            <x-backpack::menu-dropdown title="{{ $dropdown['title'] }}" icon="{{ $dropdown['icon'] }}">
                @foreach ($dropdown['items'] as $item)
                    @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu($item['name']))
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
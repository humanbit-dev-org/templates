{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i>
        {{ trans('backpack::base.dashboard') }}</a></li>

@if (backpack_auth()->user()->getAttribute('backpack_role') != 'guest')
<x-backpack::menu-separator title="Content" />
<x-backpack::menu-dropdown title="Articles" icon="la la-newspaper">
    <x-backpack::menu-dropdown-item title="Articles" icon="la la-copy" :link="backpack_url('article')" />
    <x-backpack::menu-dropdown-item title="Institutionals" icon="la la-paste" :link="backpack_url('institutional')" />
</x-backpack::menu-dropdown>
<x-backpack::menu-dropdown title="Groups" icon="la la-handshake">
    <x-backpack::menu-dropdown-item title="Groups" icon="la la-users" :link="backpack_url('group')" />
    <x-backpack::menu-dropdown-item title="Group user" icon="la la-user-friends" :link="backpack_url('group-user')" />
    <x-backpack::menu-dropdown-item title="Categories" icon="la la-book" :link="backpack_url('category')" />
</x-backpack::menu-dropdown>

<x-backpack::menu-dropdown title="Orders" icon="la la-shopping-cart">
    <x-backpack::menu-dropdown-item title="Orders" icon="la la-shopping-bag" :link="backpack_url('order')" />
    <x-backpack::menu-dropdown-item title="Order user" icon="la la-tags" :link="backpack_url('order-user')" />
</x-backpack::menu-dropdown>
<x-backpack::menu-item title="Ecommerces" icon="la la-store-alt" :link="backpack_url('ecommerce')" />
<x-backpack::menu-item title="Invites" icon="la la-paper-plane" :link="backpack_url('invite')" />

<x-backpack::menu-separator title="Utilities" />
<x-backpack::menu-item title="Contacts" icon="la la-envelope" :link="backpack_url('contact')" />
<x-backpack::menu-item title="Media" icon="la la-photo-video" :link="backpack_url('media')" />
<x-backpack::menu-item title="Attachments" icon="la la-paperclip" :link="backpack_url('attachment')" />
<x-backpack::menu-item title="Seo meta information" icon="la la-sitemap" :link="backpack_url('seo-meta-information')" />
<x-backpack::menu-item title="Translates" icon="la la-language" :link="backpack_url('translate')" />

<x-backpack::menu-separator title="Admin" />
<x-backpack::menu-item title="Users" icon="la la-user-circle" :link="backpack_url('user')" />
<x-backpack::menu-item title="Pages" icon="la la-pager" :link="backpack_url('page')" />
<x-backpack::menu-dropdown title="Authentication" icon="la la-eye">
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-user-cog" :link="backpack_url('role')" />
    {{-- <x-backpack::menu-dropdown-item title="Permissions" icon="la la-key" :link="backpack_url('permissions')" /> --}}
</x-backpack::menu-dropdown>

@endif

{{-- @if (backpack_auth()->user()->getAttribute('backpack_role') == 'developer')
<x-backpack::menu-separator title="Developer" />
@php
    $files = \Storage::disk('public')->allFiles('github/wiki');
    $mdFiles = array_filter($files, function ($file) {
        return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'md';
    });
@endphp
@if (count($mdFiles) > 0)
    <x-backpack::menu-dropdown title="Documentation" icon="la la-book">
        @foreach ($mdFiles as $file)
            @php
                $filename = strstr(basename($file), '.md', true);
                $url = 'documentation/' . Str::lower($filename);
            @endphp
            <x-backpack::menu-dropdown-item title="{{ $filename }}" icon="la la-book-open" :link="backpack_url($url)" />
@endforeach
</x-backpack::menu-dropdown>
@else
<x-backpack::menu-item title="Documentation" icon="la la-book-open" :link="backpack_url('documentation')" />
@endif
@endif --}}
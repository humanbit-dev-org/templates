{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i>
        {{ trans('backpack::base.dashboard') }}</a></li>

@if (backpack_auth()->user()->backpack_role != 'guest')
<x-backpack::menu-separator title="Content" />
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Article'))
<x-backpack::menu-item title="Articles" icon="la la-copy" :link="backpack_url('article')" />
@endif
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Institutional'))
<x-backpack::menu-item title="Institutionals" icon="la la-paste" :link="backpack_url('institutional')" />
@endif

<x-backpack::menu-separator title="Utilities" />
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Media'))
<x-backpack::menu-item title="Media" icon="la la-photo-video" :link="backpack_url('media')" />
@endif
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Attachment'))
<x-backpack::menu-item title="Attachments" icon="la la-paperclip" :link="backpack_url('attachment')" />
@endif
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Metadata'))
<x-backpack::menu-item title="Metadata" icon="la la-sitemap" :link="backpack_url('metadata')" />
@endif
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Translate'))
<x-backpack::menu-item title="Translates" icon="la la-language" :link="backpack_url('translate')" />
@endif

<x-backpack::menu-separator title="Admin" />
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('User'))
<x-backpack::menu-item title="Users" icon="la la-user-circle" :link="backpack_url('user')" />
@endif
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Page'))
<x-backpack::menu-item title="Pages" icon="la la-pager" :link="backpack_url('page')" />
@endif

{{-- Permissions dropdown menu is visible if user can access any of its items --}}
@if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('BackpackRole') || 
     \App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Role') || 
     \App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('ModelPermission'))
<x-backpack::menu-dropdown title="Permissions" icon="la la-eye">
    @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('BackpackRole'))
    <x-backpack::menu-dropdown-item title="Backend Roles" icon="la la-user-cog" :link="backpack_url('backpack-role')" />
    @endif
    @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('Role'))
    <x-backpack::menu-dropdown-item title="Web Roles" icon="la la-user-cog" :link="backpack_url('role')" />
    @endif
    @if (\App\Http\Traits\ChecksBackpackPermissions::userCanAccessMenu('ModelPermission'))
    <x-backpack::menu-dropdown-item title="Model Permissions" icon="la la-key" :link="backpack_url('model-permission')" />
    @endif
</x-backpack::menu-dropdown>
@endif

@endif
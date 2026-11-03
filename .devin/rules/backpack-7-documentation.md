---
globs: backend/**
description: "Backpack 7 official documentation."
trigger: glob
---
<backpack-boost-guidelines>
# Backpack for Laravel 7.x — Free Features

> Official documentation: https://backpackforlaravel.com/docs/7.x
> Version: 7.x | Data collected: April 2026
> License: Backpack\CRUD is free & open-source (MIT License)

---

## Table of Contents

1. [Admin UI](#1-admin-ui)
   - [Alerts](#11-alerts--notifications)
   - [Breadcrumbs](#12-breadcrumbs)
   - [Themes](#13-themes)
   - [Widgets](#14-widgets)
   - [Blade Components](#15-blade-components)
2. [CRUD Panels](#2-crud-panels)
   - [Architecture](#21-crud-architecture)
   - [List Operation](#22-list-operation)
   - [Create Operation](#23-create-operation)
   - [Update Operation](#24-update-operation)
   - [Delete Operation](#25-delete-operation)
   - [Show Operation](#26-show-operation)
   - [Reorder Operation](#27-reorder-operation)
   - [Revise Operation](#28-revise-operation)
3. [Buttons](#3-buttons)
4. [Fields (FREE)](#4-fields-free)
5. [Columns (FREE)](#5-columns-free)
6. [Save Actions](#6-save-actions)
7. [Generating Code](#7-generating-code)
8. [Recommended Add-ons](#8-recommended-add-ons-v7-compatible)

---

## 1. Admin UI

The Admin UI module (`backpack/crud`) provides the visual interface for the admin panel: layout, authentication, menu, and theme.

### 1.1 Alerts / Notifications

**Alerts** (notification bubbles) allow you to send visual feedback to administrators from both PHP and JavaScript.

#### Usage from PHP (`prologue/alerts`)

```php
// In any Backpack controller
public function foo()
{
    \Alert::add('info',    'Informative blue message.');
    \Alert::add('warning', 'Yellow/orange message.');
    \Alert::add('error',   'Red message.');
    \Alert::add('success', '<strong>OK!</strong> Operation completed.');
    \Alert::add('primary', 'Dark blue.');
    \Alert::add('secondary', 'Gray.');
    \Alert::add('light',   'Light gray.');
    \Alert::add('dark',    'Black.');

    return view('some_view');
}

// With redirect (flash session)
public function bar()
{
    \Alert::add('success', 'Login successful.')->flash();
    return Redirect::to('some-url');
}
```

#### Usage from JavaScript (Noty)

```javascript
new Noty({
    type: "success",  // success | info | warning | error | primary | secondary | dark | light
    text: 'Operation completed!',
}).show();
```

---

### 1.2 Breadcrumbs

**Breadcrumbs** display the navigation path in the top corner of the panel.

#### Enable/Disable (config/backpack/ui.php)

```php
'breadcrumbs' => true, // true = show, false = hide
```

#### Define breadcrumb in Controller

```php
public function dashboard()
{
    $this->data['title'] = trans('backpack::base.dashboard');
    $this->data['breadcrumbs'] = [
        trans('backpack::crud.admin')       => backpack_url('dashboard'),
        trans('backpack::base.dashboard')   => false, // last element: no link
    ];

    return view('backpack::dashboard', $this->data);
}
```

#### Define breadcrumb in View

```blade
@extends('backpack::layout')

@php
$breadcrumbs = [
    'Admin'     => backpack_url('dashboard'),
    'Dashboard' => false,
];
@endphp

@section('content')
    {{-- page content --}}
@endsection
```

> **Note:** The last element must always have `false` as the link.

---

### 1.3 Themes

Backpack v7 includes 3 themes (all free):

| Theme | Notes |
|---|---|
| `backpack/theme-tabler` | **Default** — Bootstrap 5, dark mode, 9 layouts, multiple auth views |
| `backpack/theme-coreuiv4` | CoreUI v4, easy upgrade from v5 |
| `backpack/theme-coreuiv2` | Legacy, IE compatibility, for upgrade from v5 |

#### Change theme (config/backpack/ui.php)

```php
'view_namespace'          => 'backpack.theme-tabler::',
'view_namespace_fallback' => 'backpack.theme-tabler::',
```

#### Create a child theme (example)

```php
// In config/backpack/ui.php
'view_namespace'          => 'admin.theme-custom.',  // custom directory
'view_namespace_fallback' => 'backpack.theme-tabler::',
```

Backpack will look for files in this order:
1. `resources/views/admin/theme-custom/...`
2. `resources/views/vendor/backpack/theme-tabler/...`
3. `vendor/backpack/theme-tabler/resources/views/...`
4. `resources/views/vendor/backpack/ui/...`
5. `vendor/backpack/crud/src/resources/views/ui/...`

#### Uninstall a theme

```bash
composer remove backpack/theme-coreuiv2
rm -rf config/backpack/theme-coreuiv2.php
php artisan backpack:require:theme-tabler
```

---

### 1.4 Widgets

**Widgets** allow you to insert blade blocks (cards, charts, notifications, data) on any panel page.

#### Available sections

- `before_content` — above main content
- `after_content` — below main content

> Main views must extend `backpack::blank` to use widgets.

#### Widget API (PHP)

```php
use Backpack\CRUD\app\Library\Widget;

// Add a widget
Widget::add($widget_definition_array)->to('before_content');

// Fluent syntax
Widget::add()
    ->to('before_content')
    ->type('card')
    ->content(null);

// Give a name for future reference
Widget::add($array)->name('my_widget');

// Modify an existing widget
Widget::name('my_widget')->content('new content');

// Remove a widget
Widget::name('my_widget')->remove();

// Move to first/last position
Widget::name('my_widget')->makeFirst();
Widget::name('my_widget')->makeLast();
```

#### FREE Widget Types

**Alert Widget**
```php
Widget::add([
    'type'         => 'alert',
    'class'        => 'alert alert-danger mb-2',
    'heading'      => 'Attention!',
    'content'      => 'This is an important notice.',
    'close_button' => true,
]);
```

**Card Widget**
```php
Widget::add([
    'type'    => 'card',
    'wrapper' => ['class' => 'col-sm-6 col-md-4'],
    'content' => [
        'header' => 'Card Title',
        'body'   => 'Card content with <b>HTML</b>.',
    ],
]);
```

**Progress Widget**
```php
Widget::add([
    'type'        => 'progress',
    'class'       => 'card text-white bg-primary mb-2',
    'value'       => '11.456',
    'description' => 'Registered users.',
    'progress'    => 57, // percentage 0-100
    'hint'        => '8544 to next milestone.',
]);
```

**Progress White Widget**
```php
Widget::add([
    'type'          => 'progress_white',
    'class'         => 'card mb-2',
    'value'         => '11.456',
    'description'   => 'Registered users.',
    'progress'      => 57,
    'progressClass' => 'progress-bar bg-primary',
    'hint'          => '8544 to next milestone.',
]);
```

**Jumbotron Widget**
```php
Widget::add([
    'type'         => 'jumbotron',
    'heading'      => 'Welcome!',
    'content'      => 'Let\'s build something amazing together.',
    'button_link'  => backpack_url('logout'),
    'button_text'  => 'Logout',
]);
```

**Div Widget** (groups widgets in Bootstrap rows)
```php
Widget::add([
    'type'    => 'div',
    'class'   => 'row',
    'content' => [
        ['type' => 'card', 'content' => ['body' => 'One']],
        ['type' => 'card', 'content' => ['body' => 'Two']],
        ['type' => 'card', 'content' => ['body' => 'Three']],
    ],
]);
```

**Script Widget**
```php
Widget::add()
    ->type('script')
    ->content('assets/js/custom-script.js');

// From CDN with extra attributes
Widget::add()->type('script')
    ->content('https://code.jquery.com/ui/1.12.0/jquery-ui.min.js')
    ->integrity('sha256-0YPKAwZP7Mp3ALMRVB2i8GXeEndvCq3eSl/WsAl1Ryk=')
    ->crossorigin('anonymous');
```

**Style Widget**
```php
Widget::add()->type('style')->content('assets/css/custom.css');
```

**View Widget** (loads any blade view)
```php
Widget::add([
    'type'      => 'view',
    'view'      => 'path.to.custom.view',
    'someAttr'  => 'some value',  // available as $widget['someAttr'] in the view
]);
```

**Livewire Widget**
```php
Widget::add([
    'type'           => 'livewire',
    'content'        => 'my-livewire-component',
    'parameters'     => ['user' => backpack_user()],
    'livewireAssets' => false,
]);
```

**Datatable Widget** (displays a CRUD table)
```php
Widget::add([
    'type'       => 'datatable',
    'controller' => 'App\Http\Controllers\Admin\InvoiceCrudController',
    'name'       => 'invoices',
    'setup'      => function($crud, $parent) {
        if ($parent) {
            $crud->addClause('where', 'owner_id', $parent->id);
        }
    },
]);
```

**Dataform Widget** (displays a CRUD form)
```php
Widget::add([
    'type'       => 'dataform',
    'controller' => 'App\Http\Controllers\Admin\InvoiceCrudController',
    'name'       => 'invoice_form',
    'setup'      => function($crud, $parent) {
        $crud->removeField('notes');
    },
]);
```

**Chip Widget**
```php
Widget::add([
    'type'  => 'chip',
    'view'  => 'crud::chips.general',
    'title' => 'invoices',
    'entry' => Invoice::first(),
]);
```

#### Create a Custom Widget

```blade
{{-- resources/views/vendor/backpack/ui/widgets/well.blade.php --}}
@includeWhen(!empty($widget['wrapper']), 'backpack::widgets.inc.wrapper_start')
<div class="{{ $widget['class'] ?? 'well mb-2' }}">
    {!! $widget['content'] !!}
</div>
@includeWhen(!empty($widget['wrapper']), 'backpack::widgets.inc.wrapper_end')
```

```php
// Using the custom widget
Widget::add([
    'type'    => 'well',
    'wrapper' => ['class' => 'col-sm-12'],
    'content' => 'Text with <i>well</i> class.',
]);
```

> **Override an existing widget:**
> ```bash
> php artisan backpack:widget --from=card
> ```

---

### 1.5 Blade Components

**Blade Components** are reusable theme components for menus and data.

#### Syntax

```blade
{{-- Full syntax --}}
<x-backpack::menu-item />
{{-- Alias syntax (shorter) --}}
<x-bp-menu-item />
```

#### Menu Item

```blade
<x-backpack::menu-item
    title="Tags"
    icon="la la-tag"
    :link="backpack_url('tags')"
/>
```

#### Menu Separator

```blade
<x-backpack::menu-separator title="Content management" />
```

#### Menu Dropdown

```blade
<x-backpack::menu-dropdown title="Authentication" icon="la la-group">
    <x-backpack::menu-dropdown-item title="Users"     icon="la la-user"  :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Roles"      icon="la la-group" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permissions" icon="la la-key"   :link="backpack_url('permission')" />
</x-backpack::menu-dropdown>
```

#### Datatable Component

```blade
<x-bp-datatable controller="\App\Http\Controllers\Admin\InvoiceCrudController" />

{{-- Advanced configuration --}}
<x-bp-datatable
    controller="\App\Http\Controllers\Admin\InvoiceCrudController"
    name="invoices"
    form-operation="show"
    :setup="function($crud, $parent) {
        if ($parent) {
            $crud->addClause('where', 'customer_id', $parent->id);
        }
    }"
/>
```

#### Dataform Component

```blade
<x-bp-dataform controller="\App\Http\Controllers\Admin\InvoiceCrudController" />

{{-- Advanced configuration with update --}}
<x-bp-dataform
    controller="\App\Http\Controllers\Admin\InvoiceCrudController"
    name="invoice_form"
    form-operation="update"
    :entry="\App\Models\Invoice::find(1)"
    :setup="function($crud, $parent) {
        $crud->removeColumn('notes');
    }"
/>
```

#### Datagrid Component (entry attributes grid)

```blade
{{-- With existing CrudController --}}
<x-bp-datagrid
    controller="\App\Http\Controllers\Admin\InvoiceCrudController"
    :entry="\App\Models\Invoice::first()"
/>

{{-- Without CrudController (define columns manually) --}}
<x-bp-datagrid
    :entry="\App\User::find(1)"
    :columns="[
        ['label' => 'Name',       'type' => 'text',     'name' => 'name',       'size' => 6],
        ['label' => 'Email',      'type' => 'email',    'name' => 'email',      'size' => 6],
        ['label' => 'Created at', 'type' => 'datetime', 'name' => 'created_at'],
        ['label' => 'Updated',    'type' => 'datetime', 'name' => 'updated_at'],
    ]"
/>
```

#### Datalist Component (entry attributes table)

```blade
<x-bp-datalist
    controller="\App\Http\Controllers\Admin\InvoiceCrudController"
    :entry="\App\Models\Invoice::first()"
/>
```


---

## 2. CRUD Panels

A **CRUD Panel** is a complete admin interface for an Eloquent model, composed of:

- A **route** in `routes/backpack/custom.php`
- A **controller** in `app/Http/Controllers/Admin/`
- A **form request** (optional) in `app/Http/Requests/`

### 2.1 CRUD Architecture

```php
// app/Http/Controllers/Admin/ProductCrudController.php
<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;

class ProductCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings('product', 'products');
    }
}
```

#### Generate a CRUD from terminal

```bash
# For a single Model
php artisan backpack:crud tag

# For all Models without CRUD
php artisan backpack:build
```

---

### 2.2 List Operation

**The List operation** displays a paginated, searchable table of all entries, with DataTables and AJAX support.

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

protected function setupListOperation()
{
    // Define columns
    CRUD::column('name');
    CRUD::column('email');
    CRUD::column('created_at');
}
```

#### Custom query

```php
protected function setupListOperation()
{
    // Filter displayed entries
    CRUD::addClause('active');                  // local scope
    CRUD::addClause('where', 'status', '=', 'published');
    CRUD::addClause('whereHas', 'posts', function($q) {
        $q->activePosts();
    });
    CRUD::groupBy();
    CRUD::limit();

    // Custom order (only if user hasn't already ordered)
    if (!CRUD::getRequest()->has('order')) {
        CRUD::orderBy('updated_at', 'desc');
    }
}
```

#### Row buttons dropdown

```php
protected function setupListOperation()
{
    // Collapse row buttons into a dropdown
    CRUD::setOperationSetting('lineButtonsAsDropdown', true);
    CRUD::setOperationSetting('lineButtonsAsDropdownMinimum', 3);   // min 3 buttons to activate
    CRUD::setOperationSetting('lineButtonsAsDropdownShowBefore', 2); // show 2 inline, rest in dropdown
}
```

#### Details Row (expandable row)

```php
protected function setupListOperation()
{
    CRUD::enableDetailsRow();

    // Add widgets in details row
    Widget::add()
        ->to('details_row')
        ->type('progress')
        ->value(135)
        ->description('Progress')
        ->progress(50);

    // Or use a custom view
    CRUD::setDetailsRowView('admin.articles_details_row');
}
```

#### Persistent Table (remembers filters and pagination)

```php
protected function setupListOperation()
{
    CRUD::enablePersistentTable();
    CRUD::setOperationSetting('persistentTableDuration', 120); // minutes (false = forever)
}
```

#### Responsive Table

```php
protected function setupListOperation()
{
    CRUD::disableResponsiveTable(); // disable only for this CRUD
}
```

#### Large tables (millions of entries)

```php
protected function setupListOperation()
{
    CRUD::setOperationSetting('showEntryCount', false); // hide total count
}
```

#### Add Widgets to List

```php
use Backpack\CRUD\app\Library\Widget;

public function setupListOperation()
{
    $userCount = \App\Models\User::count();

    Widget::add()->to('before_content')->type('div')->class('row')->content([
        Widget::make()
            ->type('progress')
            ->class('card border-0 text-white bg-primary')
            ->progressClass('progress-bar')
            ->value($userCount)
            ->description('Registered users.')
            ->progress(100 * (int)$userCount / 1000)
            ->hint(1000 - $userCount . ' to next milestone.'),

        Widget::make([
            'type'    => 'card',
            'class'   => 'card bg-dark text-white',
            'wrapper' => ['class' => 'col-sm-3 col-md-3'],
            'content' => [
                'header' => 'Sample Widget',
                'body'   => 'Positioned in before_content on the same row.',
            ],
        ]),
    ]);
}
```

---

### 2.3 Create Operation

**The Create operation** allows administrators to add new entries to the database.

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;

protected function setupCreateOperation()
{
    // Validation with FormRequest
    CRUD::setValidation(\App\Http\Requests\ProductRequest::class);

    // Or inline validation
    CRUD::setValidation([
        'name'  => 'required|min:2',
        'email' => 'required|email|unique:users',
    ]);

    // Define fields
    CRUD::field('name')->type('text')->label('Name');
    CRUD::field('email')->type('email');
    CRUD::field('description')->type('textarea');
}
```

#### Validation directly on field

```php
CRUD::field('email')->validationRules('required|email|unique:users,email');

CRUD::addField([
    'name'               => 'content',
    'type'               => 'textarea',
    'validationRules'    => 'required|min:10',
    'validationMessages' => [
        'required' => 'Content is required.',
        'min'      => 'Minimum 10 characters.',
    ],
]);
```

#### Use Eloquent Events in setup

```php
public function setupCreateOperation()
{
    Product::creating(function($entry) {
        $entry->author_id = backpack_user()->id;
    });
}
```

#### Override the store() method

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }

public function store()
{
    // logic before save
    $response = $this->traitStore();
    // logic after save
    return $response;
}
```

---

### 2.4 Update Operation

**The Update operation** allows modifying existing entries.

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

protected function setupUpdateOperation()
{
    // Reuse Create fields
    $this->setupCreateOperation();

    // Or define separate fields
    CRUD::field('name')->type('text');
    CRUD::field('status')->type('select_from_array')->options([
        'draft'     => 'Draft',
        'published' => 'Published',
    ]);

    // Separate validation for Update
    CRUD::setValidation(\App\Http\Requests\UpdateProductRequest::class);

    // Eager load relationships
    CRUD::setOperationSetting('eagerLoadRelationships', true);

    // Show Delete button in Update form
    CRUD::setOperationSetting('showDeleteButton', true);
}
```

#### Translatable model

```php
// app/Models/Product.php
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

class Product extends Model
{
    use CrudTrait, HasTranslations;

    protected $translatable = ['name', 'description'];
}
```

---

### 2.5 Delete Operation

**The Delete operation** removes single entries (or soft-delete if the Model supports it).

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

// No additional configuration required
// A "Delete" button will appear automatically in the List view
```

#### Override the destroy() method

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as traitDestroy; }

public function destroy($id)
{
    CRUD::hasAccessOrFail('delete');
    // custom logic before deletion
    return CRUD::delete($id);
}
```

> **Note:** BulkDelete (bulk deletion) is a **PRO** feature.

---

### 2.6 Show Operation

**The Show operation** displays details of a single entry in read-only mode.

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

// Automatic configuration (shows all DB columns)
// No additional setup required for base configuration

// Manual configuration
protected function setupShowOperation()
{
    // Use same columns as List
    $this->setupListOperation();

    // Or: auto-setup + customizations
    $this->autoSetupShowOperation();

    CRUD::column([
        'name'  => 'my_custom_html',
        'label' => 'Custom HTML',
        'type'  => 'custom_html',
        'value' => '<span class="text-danger">Something</span>',
    ]);

    // Remove a column
    CRUD::column('text')->remove();
}
```

#### Vertical/horizontal tabs in Show

```php
protected function setupShowOperation()
{
    CRUD::column(['name' => 'name',        'tab' => 'General']);
    CRUD::column(['name' => 'description', 'tab' => 'Details']);
    CRUD::column('email')->tab('Contacts');

    // Tab type: 'horizontal' (default) or 'vertical'
    $this->crud->setOperationSetting('tabsType', 'vertical');
}
```

#### Override show()

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation { show as traitShow; }

public function show($id)
{
    $content = $this->traitShow($id);
    // custom logic
    return $content;
}
```

---

### 2.7 Reorder Operation

**The Reorder operation** allows ordering and nesting entries via drag & drop.

#### Requirements

The Model must have these integer fields (default 0):
- `parent_id` (nullable)
- `lft`
- `rgt`
- `depth`

```php
use \Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;

protected function setupReorderOperation()
{
    // Attribute displayed on draggable elements
    CRUD::set('reorder.label', 'name');

    // Maximum nesting depth (0 = infinite)
    CRUD::set('reorder.max_level', 2);

    // Escape label attribute (security)
    CRUD::set('reorder.escaped', true);
}
```

#### Custom column names

```php
protected function setupReorderOperation()
{
    CRUD::setOperationSetting('reorderColumnNames', [
        'parent_id' => 'custom_parent_id',
        'lft'       => 'left',
        'rgt'       => 'right',
        'depth'     => 'deep',
    ]);
    CRUD::set('reorder.label', 'title');
    CRUD::set('reorder.max_level', 3);
}
```

---

### 2.8 Revise Operation

**The Revise operation** (or Revision) stores a history of changes and allows undoing them.

#### Installation

```bash
composer require backpack/revise-operation

# Create revisions table
cp vendor/venturecraft/revisionable/src/migrations/2013_04_09_062329_create_revisions_table.php database/migrations/
php artisan migrate
```

#### Setup on Model

```php
namespace App\Models;

class Article extends Eloquent
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    use \Venturecraft\Revisionable\RevisionableTrait;

    public function identifiableName()
    {
        return $this->name; // attribute to identify the entry
    }

    public static function boot()
    {
        parent::boot();
    }
}
```

#### Setup in CrudController

```php
use \Backpack\ReviseOperation\ReviseOperation;

class ArticleCrudController extends CrudController
{
    use ReviseOperation;
    // ... other traits
}
```


---

## 3. Buttons

**Buttons** appear in the List Operation and Show Operation and allow triggering operations.

### Available stacks

| Stack | Position | Default |
|---|---|---|
| `top` | Above the table | "Add" button |
| `line` | Next to each row | Edit, Delete |
| `bottom` | Below the table | — |

### Button API

```php
protected function setupListOperation()
{
    // Add a button from a blade view
    CRUD::addButtonFromView('line', 'moderate', 'moderate', 'beginning');
    CRUD::addButtonFromView('top',  'import',   'import',   'end');

    // Add a button from a Model function
    CRUD::addButtonFromModelFunction('line', 'open_google', 'openGoogle', 'beginning');

    // Remove buttons
    CRUD::removeButton('delete');
    CRUD::removeButtonFromStack('delete', 'line');

    // Remove all buttons from a stack
    CRUD::removeAllButtonsFromStack('line');

    // Order buttons
    CRUD::orderButtons('line', ['update', 'delete', 'show']);

    // Move a button
    CRUD::moveButton('show', 'after', 'delete');
}
```

### Quick Button (quick method)

```php
// Simple button
CRUD::button('email')->stack('line')->view('crud::buttons.quick');

// With full customization
CRUD::button('email')->stack('line')->view('crud::buttons.quick')->meta([
    'access' => true,
    'label'  => 'Send Email',
    'icon'   => 'la la-envelope',
    'wrapper' => [
        'element' => 'a',
        'href'    => url('something'),
        'target'  => '_blank',
        'title'   => 'Send a new email',
    ],
]);

// Dynamic URL with closure
CRUD::button('email')->stack('line')->view('crud::buttons.quick')->meta([
    'wrapper' => [
        'href' => function ($entry, $crud) {
            return backpack_url("invoice/{$entry->id}/email");
        },
    ],
]);
```

### Quick Button with AJAX

```php
CRUD::button('email')->stack('line')->view('crud::buttons.quick')->meta([
    'label' => 'Payment Reminder',
    'icon'  => 'la la-envelope',
    'wrapper' => [
        'href'  => function ($entry, $crud) {
            return backpack_url("invoice/{$entry->id}/send-reminder");
        },
        'ajax' => [
            'method'          => 'POST',
            'refreshCrudTable'=> true,
            'success_title'   => 'Reminder Sent',
            'success_message' => 'The reminder was sent successfully.',
            'error_title'     => 'Error',
            'error_message'   => 'Unable to send the reminder.',
        ],
    ],
]);

// Controller method
public function sendReminder($id)
{
    CRUD::hasAccessOrFail('email');
    $invoice = CRUD::getEntry($id);
    $invoice->sendPaymentReminder();
    return response()->json(['message' => 'Reminder sent.']);
}
```

### Create a Custom button with blade

```bash
php artisan backpack:button moderate
```

```blade
{{-- resources/views/vendor/backpack/crud/buttons/moderate.blade.php --}}
@if ($crud->hasAccess('update', $entry))
    <a href="{{ url($crud->route.'/'.$entry->getKey().'/moderate') }}"
       class="btn btn-xs btn-default">
        <i class="la la-ban"></i> Moderate
    </a>
@endif
```

```php
// routes/backpack/custom.php
Route::get('user/{id}/moderate', 'UserCrudController@moderate');

// UserCrudController
protected function setupListOperation()
{
    CRUD::addButtonFromView('line', 'moderate', 'moderate', 'beginning');
}

public function moderate()
{
    // moderation logic
}
```

### Button visible only for some entries

```php
// Access control per entry
$this->crud->setAccessCondition('approve', function ($entry) {
    return $entry->status !== 'approved';
});

// Access control per user
$this->crud->setAccessCondition('approve', function ($entry) {
    return backpack_user()->hasRole('admin');
});
```

---

## 4. Fields (FREE)

**Fields** are the input types available in Create and Update operations. Backpack CRUD includes **28 free field types**.

### Quick reference — Free Field Types

| Field Type | Description |
|---|---|
| `checkbox` | Single checkbox |
| `checklist` | Checkbox list |
| `ckeditor` | CKEditor WYSIWYG |
| `color` | Color picker |
| `date` | Date picker |
| `datetime` | Date and time picker |
| `email` | Email input with validation |
| `enum` | Dropdown from DB ENUM type |
| `hidden` | Hidden field |
| `month` | Month picker |
| `number` | Numeric input |
| `password` | Password input |
| `radio` | Radio buttons |
| `range` | Range slider |
| `select` | Dropdown from belongsTo relation |
| `select_grouped` | Grouped dropdown |
| `select_multiple` | Multi-select from relation |
| `select_from_array` | Dropdown from PHP array |
| `summernote` | Summernote WYSIWYG |
| `switch` | Toggle switch |
| `text` | Text input |
| `textarea` | Text area |
| `time` | Time picker |
| `tinymce` | TinyMCE WYSIWYG |
| `upload` | Single file upload |
| `upload_multiple` | Multiple file upload |
| `url` | URL input |
| `view` | Custom blade view |
| `week` | Week picker |

### Practical Field examples

```php
protected function setupCreateOperation()
{
    // Basic text field
    CRUD::field('name')
        ->type('text')
        ->label('Product Name')
        ->hint('Enter the full product name.')
        ->placeholder('e.g. Running Shoes');

    // Email
    CRUD::field('email')
        ->type('email')
        ->label('Email');

    // Number
    CRUD::field('price')
        ->type('number')
        ->label('Price (€)')
        ->attributes(['step' => '0.01', 'min' => '0']);

    // Textarea
    CRUD::field('description')
        ->type('textarea')
        ->label('Description');

    // Select from BelongsTo relation
    CRUD::field('category_id')
        ->type('select')
        ->label('Category')
        ->entity('category')
        ->model(\App\Models\Category::class)
        ->attribute('name');

    // Select from PHP array
    CRUD::field('status')
        ->type('select_from_array')
        ->label('Status')
        ->options([
            'draft'     => 'Draft',
            'published' => 'Published',
            'archived'  => 'Archived',
        ])
        ->allows_null(false);

    // Checkbox
    CRUD::field('is_active')
        ->type('checkbox')
        ->label('Active');

    // Date
    CRUD::field('published_at')
        ->type('date')
        ->label('Publication date');

    // File upload
    CRUD::field('thumbnail')
        ->type('upload')
        ->label('Main image')
        ->upload(true);

    // Multiple upload
    CRUD::field('gallery')
        ->type('upload_multiple')
        ->label('Image gallery')
        ->upload(true);

    // CKEditor
    CRUD::field('content')
        ->type('ckeditor')
        ->label('Content');

    // Hidden
    CRUD::field('author_id')
        ->type('hidden')
        ->default(backpack_user()->id);

    // Switch toggle
    CRUD::field('featured')
        ->type('switch')
        ->label('Featured');

    // Radio buttons
    CRUD::field('gender')
        ->type('radio')
        ->label('Gender')
        ->options(['M' => 'Male', 'F' => 'Female']);

    // Color picker
    CRUD::field('brand_color')
        ->type('color')
        ->label('Brand color');
}
```

### Inline validation on fields

```php
CRUD::field('title')->validationRules('required|min:3|max:200');
CRUD::field('slug')->validationRules('required|unique:products,slug');
CRUD::field('price')->validationRules('required|numeric|min:0');
```

### Organize fields in tabs

```php
protected function setupCreateOperation()
{
    CRUD::field('name')->tab('General information');
    CRUD::field('description')->type('ckeditor')->tab('General information');
    CRUD::field('price')->tab('Prices & Stock');
    CRUD::field('stock')->tab('Prices & Stock');
    CRUD::field('thumbnail')->type('upload')->tab('Media');
    CRUD::field('gallery')->type('upload_multiple')->tab('Media');
}
```

### Separate Create/Update validation

```php
use App\Http\Requests\CreateProductRequest as StoreRequest;
use App\Http\Requests\UpdateProductRequest as UpdateRequest;

public function setupCreateOperation()
{
    CRUD::setValidation(StoreRequest::class);
}

public function setupUpdateOperation()
{
    CRUD::setValidation(UpdateRequest::class);
}
```


---

## 5. Columns (FREE)

**Columns** determine how attributes are displayed in List and Show operations. Backpack CRUD includes **28 free column types**.

### Quick reference — Free Column Types

| Column Type | Description |
|---|---|
| `boolean` | Shows true/false as icons |
| `check` | Shows checkmark ✓ or ✗ |
| `checkbox` | Checkbox in table |
| `checklist` | Checkbox list |
| `checklist_dependency` | List with dependencies |
| `closure` | Output from PHP closure |
| `color` | Color sample |
| `custom_html` | Custom HTML |
| `date` | Formatted date |
| `datetime` | Formatted date and time |
| `email` | Email link |
| `enum` | Readable ENUM value |
| `hidden` | Hidden column (used for export) |
| `image` | Image thumbnail |
| `json` | Formatted JSON |
| `model_function` | Output from Model method |
| `model_function_attribute` | Model attribute |
| `month` | Formatted month |
| `number` | Formatted number |
| `select` | Value from belongsTo relation |
| `select_from_array` | Label from PHP array |
| `select_multiple` | Multiple values from relation |
| `text` | Plain text |
| `url` | Clickable link |
| `view` | Custom blade view |

### Column API

```php
protected function setupListOperation()
{
    // Short form (for text columns)
    CRUD::column('name');
    CRUD::column('email');

    // Full form with array
    CRUD::column([
        'name'  => 'name',
        'label' => 'Product Name',
        'type'  => 'text',
    ]);

    // Fluent syntax
    CRUD::column('price')
        ->type('number')
        ->label('Price')
        ->prefix('€ ')
        ->decimals(2);
}
```

### Practical Column Type examples

```php
// Plain text
CRUD::column('name')->type('text')->label('Name');

// Boolean with icons
CRUD::column('is_active')
    ->type('boolean')
    ->label('Active')
    ->options([false => 'No', true => 'Yes']);

// Formatted date
CRUD::column('created_at')
    ->type('date')
    ->label('Creation date')
    ->format('DD/MM/YYYY');

// Date and time
CRUD::column('updated_at')
    ->type('datetime')
    ->label('Last modified')
    ->format('DD/MM/YYYY HH:mm');

// Image
CRUD::column('thumbnail')
    ->type('image')
    ->label('Image')
    ->height('50px')
    ->width('50px');

// Select from relation
CRUD::column('category_id')
    ->type('select')
    ->label('Category')
    ->entity('category')
    ->attribute('name')
    ->model(\App\Models\Category::class);

// Multiple select
CRUD::column('tags')
    ->type('select_multiple')
    ->label('Tags')
    ->entity('tags')
    ->attribute('name')
    ->model(\App\Models\Tag::class);

// Select from array
CRUD::column('status')
    ->type('select_from_array')
    ->options([
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ]);

// Custom HTML
CRUD::column('title')
    ->type('custom_html')
    ->value(function ($entry) {
        return '<span class="badge badge-primary">' . $entry->title . '</span>';
    });

// Closure (output from PHP function)
CRUD::column('full_name')
    ->type('closure')
    ->label('Full Name')
    ->function(function ($entry) {
        return $entry->first_name . ' ' . $entry->last_name;
    });

// Model function
CRUD::column('full_address')
    ->type('model_function')
    ->label('Full Address')
    ->function_name('getFullAddressAttribute');

// Clickable URL
CRUD::column('website')
    ->type('url')
    ->label('Website');

// Hidden column (visible only in export)
CRUD::column('internal_code')
    ->type('hidden')
    ->label('Internal code')
    ->visibleInTable(false)
    ->visibleInExport(true);

// Formatted JSON
CRUD::column('metadata')
    ->type('json')
    ->label('Metadata');
```

### Custom search per column

```php
CRUD::column('full_name')
    ->type('text')
    ->searchLogic(function ($query, $column, $searchTerm) {
        $query->orWhere('first_name', 'like', '%' . $searchTerm . '%')
              ->orWhere('last_name',  'like', '%' . $searchTerm . '%');
    });
```

### Column visibility in export

```php
CRUD::column('name')
    ->visibleInTable(true)     // visible in table
    ->visibleInExport(true);   // included in export

CRUD::column('secret_code')
    ->visibleInTable(false)    // hidden in table
    ->visibleInExport(false);  // excluded from export

CRUD::column('tracking_id')
    ->exportOnlyColumn(true);  // only in export, not in table
```

---

## 6. Save Actions

**Save Actions** are the options available in the "Save" button of Create/Update forms.

### Default Save Actions (all FREE)

| Name | Behavior |
|---|---|
| `save_and_back` | Save and return to previous page |
| `save_and_edit` | Save and stay on same entry |
| `save_and_new` | Save and open new create form |
| `save_and_preview` | Save and go to Show operation |

### Add/Remove Save Actions

```php
protected function setupCreateOperation()
{
    // Remove a specific action
    CRUD::removeSaveAction('save_and_new');
    CRUD::removeSaveActions(['save_and_new', 'save_and_preview']);

    // Add a custom action (array)
    CRUD::addSaveAction([
        'name'        => 'save_and_publish',
        'button_text' => 'Save and Publish',
        'visible'     => function($crud) { return true; },
        'redirect'    => function($crud, $request, $itemId) {
            return $crud->route;
        },
        'order'       => 1,
    ]);

    // Replace all actions
    CRUD::replaceSaveActions([
        [
            'name'     => 'save_and_back',
            'redirect' => function($crud, $request, $itemId) {
                return $crud->route;
            },
        ],
    ]);

    // Reorder
    CRUD::orderSaveActions(['save_and_edit', 'save_and_back', 'save_and_new']);
}
```

### Create a Save Action as Class (Backpack v7+)

```php
// app/Backpack/Crud/SaveActions/SaveAndApprove.php
<?php

namespace App\Backpack\Crud\SaveActions;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\SaveActions\AbstractSaveAction;
use Illuminate\Http\Request;

class SaveAndApprove extends AbstractSaveAction
{
    protected ?int $order = 2;

    public function getName(): string
    {
        return 'save_and_approve';
    }

    public function getButtonText(): string
    {
        return 'Save and Approve';
    }

    public function isVisible(CrudPanel $crud): bool
    {
        return $crud->hasAccess('update') && $crud->entry?->isPending();
    }

    public function getRedirectUrl(CrudPanel $crud, Request $request, $itemId = null): ?string
    {
        return route('admin.invoices.approve', $itemId ?? $request->input('id'));
    }
}
```

```php
// Usage in CrudController
use App\Backpack\Crud\SaveActions\SaveAndApprove;
use Backpack\CRUD\app\Library\CrudPanel\SaveActions\SaveAndBack;

protected function setupUpdateOperation()
{
    CRUD::replaceSaveActions([
        new SaveAndApprove(),
        SaveAndBack::class,
    ]);
}
```

```blade
{{-- Usage in Blade component --}}
<x-bp-dataform
    controller="\App\Http\Controllers\Admin\InvoiceCrudController"
    :save-actions="[
        new App\Backpack\Crud\SaveActions\SaveAndApprove(),
        Backpack\CRUD\app\Library\CrudPanel\SaveActions\SaveAndBack::class,
    ]"
/>
```

### Disable Save Action change notification

```php
CRUD::setOperationSetting('showSaveActionChange', false);
```

---

## 7. Generating Code

Backpack includes tools to generate code automatically.

### Main Artisan commands

```bash
# Generate Model, Request, Controller, Route and sidebar entry for an entity
php artisan backpack:crud tag

# Generate CRUD for all Models without existing CRUD
php artisan backpack:build

# Generate a custom button
php artisan backpack:button moderate

# Publish a view to customize (e.g. list.blade.php)
php artisan backpack:publish crud/list

# Publish a widget to customize
php artisan backpack:widget --from=card

# Publish a button to customize
php artisan backpack:button --from=delete

# Install Tabler theme (default v7)
php artisan backpack:require:theme-tabler
```


---

## 8. Recommended Add-ons (v7 compatible)

Below are **free** and **third-party** add-ons compatible with Backpack 7.x.

### 8.1 Official FREE Add-ons (first-party)

#### PermissionManager (TOP RATED)
- **Repo:** github.com/Laravel-Backpack/PermissionManager
- **Description:** CRUD interface for managing users, roles and permissions with `spatie/laravel-permission`.
- **Installation:** `composer require backpack/permissionmanager`

#### Settings
- **Repo:** github.com/Laravel-Backpack/Settings
- **Description:** Modify global site settings. Each setting can have a different type.
- **Installation:** `composer require backpack/settings`

#### PageManager
- **Repo:** github.com/Laravel-Backpack/PageManager
- **Description:** Page management via customizable templates.
- **Installation:** `composer require backpack/pagemanager`

#### MenuCRUD
- **Repo:** github.com/Laravel-Backpack/MenuCRUD
- **Description:** Create, edit, delete and reorder menu items.
- **Installation:** `composer require backpack/menucrud`

#### NewsCRUD
- **Repo:** github.com/Laravel-Backpack/NewsCRUD
- **Description:** Article, category and tag management. Ideal for blogs.
- **Installation:** `composer require backpack/newscrud`

#### BackupManager
- **Repo:** github.com/Laravel-Backpack/BackupManager
- **Description:** File and DB backup with `spatie/laravel-backup`. Supports S3, Dropbox, etc.
- **Installation:** `composer require backpack/backupmanager`

#### LogManager
- **Repo:** github.com/Laravel-Backpack/LogManager
- **Description:** View, download and delete Laravel logs from the admin panel.
- **Installation:** `composer require backpack/logmanager`

#### ActivityLog (NEW)
- **Repo:** github.com/Laravel-Backpack/ActivityLog
- **Description:** Audit trail — who changed what and when, with `spatie/laravel-activitylog`.
- **Installation:** `composer require backpack/activity-log`

#### TranslationManager (NEW)
- **Repo:** github.com/Laravel-Backpack/TranslationManager
- **Description:** UI for translating language strings with `spatie/laravel-translation-loader`.
- **Installation:** `composer require backpack/translation-manager`

#### MediaLibrary Uploaders
- **Repo:** github.com/Laravel-Backpack
- **Description:** Attach files to Eloquent models with `spatie/laravel-medialibrary`.
- **Installation:** `composer require backpack/medialibrary-uploaders`

#### ReviseOperation
- **Repo:** github.com/Laravel-Backpack/ReviseOperation
- **Description:** Change history for entries with rollback, via `venturecraft/revisionable`.
- **Installation:** `composer require backpack/revise-operation`

#### Download Operation
- **Repo:** github.com/Laravel-Backpack
- **Description:** Generate and download PDFs related to entries with `spatie/laravel-browsershot`.
- **Installation:** `composer require backpack/download-operation`

---

### 8.2 Community Add-ons (third-party) — v7 compatible

#### EduardoRandah/UserManager
- **Repo:** github.com/eduardoarandah/UserManager
- **Type:** CRUD
- **Description:** CRUD for Laravel's default users table.
- **Installation:** `composer require eduardoarandah/userManager`

#### pestopancake/laravel-backpack-database-notifications (NEW)
- **Repo:** github.com/pestopancake/laravel-backpack-database-notifications
- **Type:** Notifications
- **Description:** Database notifications in the panel with auto-updating sidebar button. Uses standard Laravel notifications.
- **Installation:** `composer require pestopancake/laravel-backpack-database-notifications`

#### redsquirrelstudio/laravel-backpack-import-operation
- **Repo:** github.com/redsquirrelstudio/laravel-backpack-import-operation
- **Type:** Operation
- **Description:** Import Eloquent data in bulk from CSV/Excel.
- **Installation:** `composer require redsquirrelstudio/laravel-backpack-import-operation`

#### maurohmartinez/compare-operation-backpack-laravel (NEW)
- **Repo:** github.com/maurohmartinez/compare-operation-backpack-laravel
- **Type:** Operation
- **Description:** Compare two or more entries side by side. Useful for review and moderation.

#### ziming/json-field-for-backpack
- **Repo:** github.com/ziming/json-field-for-backpack
- **Type:** Field
- **Description:** Field for complex JSON with syntax highlighting, color picker, undo/redo.
- **Installation:** `composer require ziming/json-field-for-backpack`

#### DoDSoftware/DynamicFieldHintsForBackpack
- **Repo:** github.com/DoDSoftware/DynamicFieldHintsForBackpack
- **Type:** Feature
- **Description:** Uses DB column comments automatically as form field hints.
- **Installation:** `composer require dodsoftware/dynamic-field-hints-for-backpack`

#### iMokhles/signature-field-for-backpack
- **Repo:** github.com/iMokhles/signature-field-for-backpack
- **Type:** Field
- **Description:** Field for drawing and saving a digital signature.
- **Installation:** `composer require imokhles/signature-field-for-backpack`

#### soufiene-slimi/star-field-for-backpack
- **Repo:** github.com/soufiene-slimi/star-field-for-backpack
- **Type:** Field
- **Description:** Field for rating entries with stars.
- **Installation:** `composer require soufiene-slimi/star-field-for-backpack`

---

## FREE vs PRO Feature Summary

| Feature | FREE | PRO |
|---|:---:|:---:|
| Admin UI (layout, auth, menu) | YES | YES |
| Alerts (PHP + JS) | YES | YES |
| Breadcrumbs | YES | YES |
| Themes (Tabler, CoreUI v4, CoreUI v2) | YES | YES |
| Widgets (9 base types) | YES | YES + chart |
| Blade Components | YES | YES |
| List Operation | YES | YES |
| Columns (28 free types) | YES | YES + 29 PRO |
| Buttons | YES | YES |
| Filters | NO | YES (10+ types) |
| Export Buttons | NO | YES |
| Details Row | YES | YES |
| Create/Update Operations | YES | YES |
| Fields (28 free types) | YES | YES + 29 PRO |
| Validation (3 methods) | YES | YES |
| Form tabs | YES | YES |
| Translatable models | YES | YES |
| Save Actions | YES | YES |
| Delete Operation | YES | YES |
| BulkDelete | NO | YES |
| Show Operation | YES | YES |
| Reorder Operation | YES | YES |
| Revise Operation | YES (free add-on) | YES |
| Clone Operation | NO | YES |
| BulkClone Operation | NO | YES |
| Fetch Operation | NO | YES |
| InlineCreate Operation | NO | YES |
| Custom List Views | NO | YES |

---

*Source: https://backpackforlaravel.com/docs/7.x — April 2026*
*Backpack\CRUD is released under the MIT License.*
</backpack-boost-guidelines>

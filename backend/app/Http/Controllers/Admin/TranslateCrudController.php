<?php

namespace App\Http\Controllers\Admin;

use App\Models\Translate;
use Illuminate\Support\Facades\File;
use App\Http\Requests\TranslateRequest;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Traits\ChecksBackpackPermissions;
/**
 * Class TranslateCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class TranslateCrudController extends CrudController
{
	use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
	use ChecksBackpackPermissions;

	/**
	 * Configure the CrudPanel object. Apply settings to all operations.
	 *
	 * @return void
	 */
	public function setup()
	{
		CRUD::setModel(\App\Models\Translate::class);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/translate");
		CRUD::setEntityNameStrings("translate", "translates");

		$this->setupPermissionChecks();
	}

	/**
	 * Define what happens when the List operation is loaded.
	 *
	 * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
	 * @return void
	 */
	protected function setupListOperation()
	{
		HelperBackend::setFieldsView(new \App\Models\Translate());

		/**
		 * Columns can be defined using the fluent syntax:
		 * - CRUD::column('price')->type('number');
		 */
	}

	/**
	 * Define what happens when the Create operation is loaded.
	 *
	 * @see https://backpackforlaravel.com/docs/crud-operation-create
	 * @return void
	 */
	protected function setupCreateOperation()
	{
		CRUD::setValidation(TranslateRequest::class);
		HelperBackend::setFields(new \App\Models\Translate());

		/**
		 * Fields can be defined using the fluent syntax:
		 * - CRUD::field('price')->type('number');
		 */
	}

	/**
	 * Define what happens when the Update operation is loaded.
	 *
	 * @see https://backpackforlaravel.com/docs/crud-operation-update
	 * @return void
	 */
	protected function setupUpdateOperation()
	{
		$this->setupCreateOperation();
	}
}

<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\File;
use App\Http\Requests\ModelPermissionRequest;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\ModelPermission;

/**
 * Class ModelPermissionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ModelPermissionCrudController extends CrudController
{
	use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

	/**
	 * Configure the CrudPanel object. Apply settings to all operations.
	 *
	 * @return void
	 */
	public function setup()
	{
		CRUD::setModel(\App\Models\ModelPermission::class);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/model-permission");
		CRUD::setEntityNameStrings("model permission", "model permissions");
	}

	/**
	 * Define what happens when the List operation is loaded.
	 *
	 * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
	 * @return void
	 */
	protected function setupListOperation()
	{
		HelperBackend::setFieldsView(new \App\Models\ModelPermission());

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
		CRUD::setValidation(ModelPermissionRequest::class);

		$models = [];
		foreach (File::allFiles(app_path("Models")) as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			$models[$name] = $name;
		}

		CRUD::addField([
			"name" => "model_name",
			"type" => "select_from_array",
			"options" => $models,
			"label" => "Model",
			"placeholder" => "Select a model",
			"multiple" => true,
		]);

		CRUD::addField([
			"name" => "backpack_role_id",
			"type" => "select_from_array",
			"label" => "Backpack Role",
			"options" => \App\Models\BackpackRole::all()->pluck("name", "id"),
		]);

		CRUD::addField([
			"name" => "role_id",
			"type" => "select_from_array",
			"label" => "Role",
			"options" => \App\Models\Role::all()->pluck("name", "id"),
		]);

		CRUD::addField([
			"name" => "can_create",
			"type" => "checkbox",
			"label" => "Create",
			"wrapper" => [
				"class" => "form-group col-md-3",
			],
		]);

		CRUD::addField([
			"name" => "can_read",
			"type" => "checkbox",
			"label" => "Read",
			"wrapper" => [
				"class" => "form-group col-md-3",
			],
		]);

		CRUD::addField([
			"name" => "can_update",
			"type" => "checkbox",
			"label" => "Update",
			"wrapper" => [
				"class" => "form-group col-md-3",
			],
		]);

		CRUD::addField([
			"name" => "can_delete",
			"type" => "checkbox",
			"label" => "Delete",
			"wrapper" => [
				"class" => "form-group col-md-3",
			],
		]);
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

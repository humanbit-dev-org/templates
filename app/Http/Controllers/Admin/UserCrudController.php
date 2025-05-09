<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Traits\ChecksBackpackPermissions;

/**
 * Class UserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
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
		CRUD::setModel(\App\Models\User::class);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/user");
		CRUD::setEntityNameStrings("user", "users");

		// Applica i controlli di autorizzazione
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
		//CRUD::setFromDb(); // set columns from db columns.
		HelperBackend::setFieldsView(new \App\Models\User());

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
		//CRUD::setFromDb(); // set fields from db columns.
		CRUD::setValidation(UserRequest::class);
		HelperBackend::setFields(new \App\Models\User());

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
		CRUD::addField([
			"name" => "password",
			"label" => "Password",
			"type" => "password",
			"value" => "",
			"hint" => "Leave blank if you don’t want to change the password.",
		]);
		if ($this->crud->getCurrentEntry() && $this->crud->getCurrentEntry()->email_verified_at) {
			CRUD::addField([
				"name" => "email_verified_at",
				"label" => "Email verified at",
				"type" => "datetime",
				"hint" => "Field not editable",
				"attributes" => ["readonly" => "readonly"],
			]);
		}
		CRUD::getCurrentEntry()->saving(function ($entry) {
			$request = request();
			if ($request->has("password") && !empty($request->input("password"))) {
				$entry->password = bcrypt($request->input("password"));
			} else {
				unset($entry->password);
			}
		});
	}

	protected function setupShowOperation()
	{
		$this->setupListOperation();
	}
}

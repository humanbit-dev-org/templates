<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Traits\ChecksBackpackPermissions;
use App\Http\Controllers\Admin\Helper\HelperBackend;
/**
 * Class NotificationCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class NotificationCrudController extends CrudController
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
		CRUD::setModel(\App\Models\Notification::class);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/notification");
		CRUD::setEntityNameStrings("notification", "notifications");

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
		// Define columns manually to handle JSON data field properly
		CRUD::column("id")->type("text")->label("ID");
		CRUD::column("type")->type("text")->label("Type");
		CRUD::column("data")->type("json")->label("Data");
		CRUD::column("notifiable_type")->type("text")->label("Notifiable Type");
		CRUD::column("notifiable_id")->type("text")->label("Notifiable ID");
		CRUD::column("read_at")->type("datetime")->label("Read At");
		CRUD::column("created_at")->type("datetime")->label("Created At");

		CRUD::removeButton("create");
		CRUD::removeButton("delete");
		CRUD::removeButton("show");
		CRUD::removeButton("update");
	}

	/**
	 * Define what happens when the Create operation is loaded.
	 *
	 * @see https://backpackforlaravel.com/docs/crud-operation-create
	 * @return void
	 */
	protected function setupCreateOperation() {}

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

	/**
	 * Define what happens when the Show operation is loaded.
	 *
	 * @return void
	 */
	protected function setupShowOperation()
	{
		HelperBackend::setFieldsShow(new \App\Models\Notification());
	}
}

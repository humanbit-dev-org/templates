<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Group;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use Backpack\CRUD\app\Library\Widget;
use App\Http\Requests\OrderUserRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class OrderuserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class OrderUserCrudController extends CrudController
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
		CRUD::setModel(\App\Models\OrderUser::class);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/order-user");
		CRUD::setEntityNameStrings("order user", "order user");
	}

	/**
	 * Define what happens when the List operation is loaded.
	 *
	 * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
	 * @return void
	 */
	protected function setupListOperation()
	{
		HelperBackend::setFieldsView(new \App\Models\OrderUser());

		// CRUD::addButtonFromView('line', 'custom_action', 'custom_button', 'beginning');
		CRUD::addButtonFromView("top", "export_csv", "export_csv", "ending");

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
		CRUD::setValidation(OrderUserRequest::class);
		Widget::add()->type("script")->content("assets/js/admin/helper.js");
		CRUD::addField([
			"name" => "custom_js", // any name you like, it won't appear as a real field
			"type" => "custom_html",
			"value" => '<script>
                            const mode = "create";
                        </script>',
		]);
		HelperBackend::setFields(new \App\Models\OrderUser());
		HelperBackend::setAjaxSelect("order_id", \App\Models\Order::class, "name", null, false, "user_id", "group->users");
		HelperBackend::setSelect("user_id", \App\Models\User::class, "email", null, true);

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
		$order_id = \App\Models\OrderUser::find($this->crud->getCurrentEntry()->id)->order_id;
		$this->crud->setValidation(OrderUserRequest::class);
		$this->crud->addField([
			"name" => "custom_js", // any name you like, it won't appear as a real field
			"type" => "custom_html",
			"value" => '<script>
                            const mode = "update";
                        </script>',
		]);
		Widget::add()->type("script")->content("assets/js/admin/helper.js");
		//CRUD::setFromDb(); // set fields from db columns.
		HelperBackend::setFields(new \App\Models\OrderUser());
		HelperBackend::setAjaxSelect("order_id", \App\Models\Order::class, "name", null, false, "user_id", "group->members");
		HelperBackend::setSelect(
			"user_id",
			\App\Models\User::class,
			"email",
			\App\Models\Order::find($order_id)->group->members
		);
	}

	public function exportCsv()
	{
		$entries = $this->crud->getEntries();

		$filename = "export.csv";
		$handle = fopen($filename, "w+");
		fputcsv($handle, array_keys($entries->first()->getAttributes()));

		foreach ($entries as $entry) {
			fputcsv($handle, $entry->getAttributes());
		}

		fclose($handle);

		return response()->download($filename)->deleteFileAfterSend(true);
	}

	protected function setupShowOperation()
	{
		$this->setupListOperation();
	}
}

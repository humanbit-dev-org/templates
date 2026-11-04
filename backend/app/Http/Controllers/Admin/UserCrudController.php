<?php

namespace App\Http\Controllers\Admin;

use App\Support\Backpack\BackpackHelper;
use App\Http\Requests\UserRequest;
use App\Http\Concerns\ChecksBackpackPermissions;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Hash;

/**
 * Class UserCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
{
	use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
	use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
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

<<<<<<< HEAD
		// Applica i controlli di autorizzazione
		$this->setupPermissionChecks();
	}
=======
        // Apply the Backpack permission checks of the current role
        $this->setupPermissionChecks();
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	/**
	 * Define what happens when the List operation is loaded.
	 *
	 * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
	 *
	 * @return void
	 */
	protected function setupListOperation()
	{
		BackpackHelper::setFieldsView(new \App\Models\User());

		/**
		 * Columns can be defined using the fluent syntax:
		 * - CRUD::column('price')->type('number');
		 */
	}

	/**
	 * Define what happens when the Create operation is loaded.
	 *
	 * @see https://backpackforlaravel.com/docs/crud-operation-create
	 *
	 * @return void
	 */
	protected function setupCreateOperation()
	{
		CRUD::setValidation(UserRequest::class);
		BackpackHelper::setFields(new \App\Models\User());

		/**
		 * Fields can be defined using the fluent syntax:
		 * - CRUD::field('price')->type('number');
		 */
	}

<<<<<<< HEAD
	/**
	 * Define what happens when the Update operation is loaded.
	 *
	 * @see https://backpackforlaravel.com/docs/crud-operation-update
	 *
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
			"hint" => "Leave blank if you don't want to change the password.",
			"tab" => "Avanzati",
			"tab" => "Dati",
			"wrapper" => ["class" => "form-group col-md-6"],
		]);
		if ($this->crud->getCurrentEntry() && $this->crud->getCurrentEntry()->email_verified_at) {
			CRUD::addField([
				"name" => "email_verified_at",
				"label" => "Email verified at",
				"type" => "datetime",
				"hint" => trans("backpack::crud.field_not_editable"),
				"attributes" => ["disabled" => "disabled"],
				"tab" => "Avanzati",
			]);
		}
		CRUD::getCurrentEntry()->saving(function ($entry) {
			$request = request();
			if ($request->has("password") && !empty($request->input("password"))) {
				$entry->password = Hash::make($request->input("password"));
			} else {
				unset($entry->password);
			}
		});
	}
=======
    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
        CRUD::addField([
            'name' => 'password',
            'label' => 'Password',
            'type' => 'password',
            'value' => '',
            'hint' => "Leave blank if you don't want to change the password.",
            'tab' => 'Dati',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        if ($this->crud->getCurrentEntry() && $this->crud->getCurrentEntry()->email_verified_at) {
            CRUD::addField([
                'name' => 'email_verified_at',
                'label' => 'Email verified at',
                'type' => 'datetime',
                'hint' => trans('backpack::crud.field_not_editable'),
                'attributes' => ['disabled' => 'disabled'],
                'tab' => 'Avanzati',
            ]);
        }
        // A blank password field keeps the current password: drop the attribute
        // before saving instead of writing an empty value.
        CRUD::getCurrentEntry()->saving(function ($entry) {
            $request = request();
            if ($request->has('password') && ! empty($request->input('password'))) {
                $entry->password = Hash::make($request->input('password'));
            } else {
                unset($entry->password);
            }
        });
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	protected function setupShowOperation()
	{
		BackpackHelper::setFieldsShow(new \App\Models\User());
	}
}

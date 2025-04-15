<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Library\Widget;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class FilterHandler
{
    /**
     * Applies filters to CRUD based on request parameters
     * 
     * Processes filter values from the request and adds
     * corresponding where clauses to the CRUD query
     * 
     * @param Model $model Current model
     */
    public static function applyFilters(Model $model)
    {
        $request = request();
        $filters = $request->except("page"); // Don't remove default page parameter, as it's handled separately

        // Add filter for page_filter
        if ($pageFilter = $request->get("page_filter")) {
            CRUD::addClause("where", "page", "=", $pageFilter);
        }

        foreach ($filters as $key => $value) {
            // Skip completely empty values but allow "0"
            if ($value === "" || is_array($value)) {
                continue;
            }

            // Special handling for path fields
            if (strpos($key, '_path') !== false) {
                self::handlePathFieldFilter($key, $value);
                continue;
            }

            if (Schema::hasColumn($model->getTable(), $key)) {
                // Get column type
                $columnType = Schema::getColumnType($model->getTable(), $key);

                // Check if column is nullable
                $table = $model->getTable();
                $database = env("DB_DATABASE");
                $isNullable =
                    DB::table("INFORMATION_SCHEMA.COLUMNS")
                    ->where("TABLE_SCHEMA", $database)
                    ->where("TABLE_NAME", $table)
                    ->where("COLUMN_NAME", $key)
                    ->value("IS_NULLABLE") === "YES";

                if ($columnType === "boolean" || $columnType === "tinyint") {
                    // Explicitly check for "0" and "1", allowing null if field is nullable
                    if ($value === "1" || $value === "0") {
                        CRUD::addClause("where", $key, "=", $isNullable && $value === "0" ? null : (int) $value);
                    }
                } else {
                    // Apply LIKE filter for text fields
                    CRUD::addClause("where", $key, "LIKE", "%" . $value . "%");
                }
            }
        }

        self::configureButtons();
    }

    /**
     * Handles filtering for fields with '_path' in their name
     * 
     * @param string $key Field name
     * @param string $value Filter value
     */
    private static function handlePathFieldFilter($key, $value)
    {
        // For path fields, check if they're empty or not based on checkbox value
        if ($value === '1') {
            // Has file (not empty)
            CRUD::addClause('whereNotNull', $key);
            CRUD::addClause('where', $key, '!=', '');
        } elseif ($value === '0') {
            // No file (empty)
            CRUD::addClause(function ($query) use ($key) {
                $query->whereNull($key)->orWhere($key, '');
            });
        }
    }

    /**
     * Configures buttons and widgets for list view
     */
    public static function configureButtons()
    {
        CRUD::removeButton("create");
        CRUD::addButtonFromView("top", "export_csv", "export_csv", "end");
        CRUD::addButtonFromView("top", "create", "create_filters");
    }

    /**
     * Configures drag-and-drop sorting functionality
     */
    public static function configureDragSortButton()
    {
        Widget::add()->type("script")->content("static/js/draggable-sort.js");
        CRUD::addButtonFromView("top", "draggable_button", "draggable_button");
    }

    /**
     * Performs sorting of the specified model
     * 
     * Updates the order field for each model instance based
     * on the new sort order provided in the request
     * 
     * @param Request $request HTTP request
     * @param string $modelName Model name to sort
     * @return \Illuminate\Http\RedirectResponse
     */
    public static function sort(Request $request, $modelName)
    {
        $modelName = ucfirst($modelName);
        $newOrder = $request->newSortOrder;
        $newOrderArray = explode(",", $newOrder);
        $model = "App\\Models\\$modelName";

        for ($i = 0; $i < count($newOrderArray); $i++) {
            $item = $model::find($newOrderArray[$i]);
            if ($item) {
                $item->order = $i + 1;
                $item->save();
            }
        }

        return redirect()->back();
    }

    /**
     * Adds a has/no file filter for path fields
     * 
     * @param string $fieldName The path field name to filter
     * @param string $label Custom label for the filter (optional)
     */
    public static function addPathFieldFilter($fieldName, $label = null)
    {
        if (strpos($fieldName, '_path') === false) {
            $fieldName .= '_path';
        }

        $filterLabel = $label ?? ucfirst(str_replace(['_path', '_'], ['', ' '], $fieldName));

        CRUD::addFilter(
            [
                'name'  => $fieldName,
                'type'  => 'simple',
                'label' => $filterLabel
            ],
            [
                '1' => trans('backpack::filters.with_file'),
                '0' => trans('backpack::filters.without_file'),
            ],
            function ($value) use ($fieldName) {
                if ($value == 1) {
                    CRUD::addClause('whereNotNull', $fieldName);
                    CRUD::addClause('where', $fieldName, '!=', '');
                } else {
                    CRUD::addClause(function ($query) use ($fieldName) {
                        $query->whereNull($fieldName)->orWhere($fieldName, '');
                    });
                }
            }
        );
    }
}

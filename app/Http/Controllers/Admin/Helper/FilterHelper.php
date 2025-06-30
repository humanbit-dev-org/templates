<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;

class FilterHelper
{
    /**
     * Get filter configuration for the CRUD list view
     */
    public static function getFilterConfiguration(CrudPanel $crud): array
    {
        // Check if any filters are applied (excluding pagination)
        $hasFilters = collect(request()->except(['page']))
            ->filter(function ($value) {
                return $value !== null && $value !== "";
            })->isNotEmpty();

        // Get all columns excluding pagination and other non-filterable fields
        $columns = collect($crud->columns())->filter(function ($column) {
            return isset($column['name'])
                && !in_array($column['name'], ['created_at', 'updated_at', 'order'])
                && (!isset($column['relation_type']) || $column['relation_type'] !== 'HasMany');
        });

        // Group columns by type
        $textColumns = $columns->filter(function($column) {
            return !str_ends_with($column['name'], '_id')
                && !str_ends_with($column['name'], '_path')
                && ($column['type'] ?? '') != 'switch'
                && $column['name'] !== 'page';
        });

        // Path fields
        $pathFields = $columns->filter(function($column) {
            return str_ends_with($column['name'], '_path');
        });

        // Boolean filters (excluding path fields)
        $booleanColumns = $columns->filter(function($column) {
            return ($column['type'] ?? '') == 'switch' && !str_ends_with($column['name'], '_path');
        });

        $relationColumns = $columns->filter(function($column) {
            return str_ends_with($column['name'], '_id') || $column['name'] === 'page';
        });

        // Get count of applied filters per section
        $textFilterCount = self::getTextFilterCount($textColumns);
        $booleanFilterCount = self::getBooleanFilterCount($booleanColumns);
        $pathFilterCount = self::getPathFilterCount($pathFields);
        $relationFilterCount = self::getRelationFilterCount($relationColumns);

        // Check if specific sections have active filters
        $hasTextFilters = $textFilterCount > 0;
        $hasBooleanFilters = $booleanFilterCount > 0;
        $hasPathFilters = $pathFilterCount > 0;
        $hasRelationFilters = $relationFilterCount > 0;

        // Determine if multiple filter sections are active
        $activeFilterSections = 0;
        if ($hasTextFilters) $activeFilterSections++;
        if ($hasBooleanFilters) $activeFilterSections++;
        if ($hasPathFilters) $activeFilterSections++;
        if ($hasRelationFilters) $activeFilterSections++;

        // Only auto-open sections if exactly one section has filters
        $autoOpenSection = $activeFilterSections == 1;

        return [
            'hasFilters' => $hasFilters,
            'columns' => $columns,
            'textColumns' => $textColumns,
            'pathFields' => $pathFields,
            'booleanColumns' => $booleanColumns,
            'relationColumns' => $relationColumns,
            'textFilterCount' => $textFilterCount,
            'booleanFilterCount' => $booleanFilterCount,
            'pathFilterCount' => $pathFilterCount,
            'relationFilterCount' => $relationFilterCount,
            'hasTextFilters' => $hasTextFilters,
            'hasBooleanFilters' => $hasBooleanFilters,
            'hasPathFilters' => $hasPathFilters,
            'hasRelationFilters' => $hasRelationFilters,
            'openTextFilters' => $hasTextFilters && $autoOpenSection,
            'openBooleanFilters' => $hasBooleanFilters && $autoOpenSection,
            'openPathFilters' => $hasPathFilters && $autoOpenSection,
            'openRelationFilters' => $hasRelationFilters && $autoOpenSection,
        ];
    }

    /**
     * Get active filters for display
     */
    public static function getActiveFilters(Collection $columns): Collection
    {
        return collect(request()->except(['page', 'persistent-table']))
            ->filter(function ($value, $key) {
                return $value !== null && $value !== "" && $key !== '_token';
            });
    }

    /**
     * Get display value for active filter
     */
    public static function getFilterDisplayValue($key, $value, $columns, $crud): string
    {
        $columnName = $key === 'page_filter' ? 'page' : $key;
        $column = $columns->firstWhere('name', $columnName);
        
        if (!$column) {
            return $value;
        }

        // Handle path fields
        if (str_ends_with($columnName, '_path')) {
            return trans('backpack::filters.with_file');
        }
        
        // Handle relation fields
        elseif (str_ends_with($columnName, '_id')) {
            return self::getRelationDisplayValue($columnName, $value, $crud);
        }
        
        // Handle boolean fields
        elseif (($column['type'] ?? '') == 'switch') {
            return $value == '1' ? trans('backpack::filters.yes') : trans('backpack::filters.no');
        }
        
        return $value;
    }

    /**
     * Get options for relation filter
     */
    public static function getRelationOptions($column, $crud): array
    {
        // First try to get the related model from the actual relation method
        $relationName = str_replace('_id', '', $column['name']);
        $relatedModel = null;
        
        // Check if the relation method exists in the model
        if (method_exists($crud->model, $relationName)) {
            try {
                $relationQuery = $crud->model->$relationName();
                $relatedModel = get_class($relationQuery->getRelated());
            } catch (\Exception $e) {
                // Fallback to the old method if relation method fails
                $relatedModelName = Str::studly($relationName);
                $relatedModel = "App\Models\\" . $relatedModelName;
            }
        } else {
            // Fallback to the old method
            $relatedModelName = Str::studly($relationName);
            $relatedModel = "App\Models\\" . $relatedModelName;
        }
        
        $options = [];

        if (class_exists($relatedModel)) {
            $collection = call_user_func([$relatedModel, 'all']);
            $options = $collection->mapWithKeys(function ($item) {
                // Check for getDisplayAttribute method first
                if (method_exists($item, 'getDisplayAttribute')) {
                    return [$item->id => $item->getDisplayAttribute()];
                } else {
                    // Fallback to ID if no other field found
                    $displayValue = $item->id;
                }

                return [$item->id => $displayValue];
            })->toArray();

            // Sort options alphabetically
            asort($options);
        }

        return $options;
    }

    /**
     * Get text filter count
     */
    private static function getTextFilterCount(Collection $textColumns): int
    {
        return collect(request()->all())
            ->filter(function($value, $key) use ($textColumns) {
                return $textColumns->contains('name', $key) && $value !== null && $value !== '';
            })->count();
    }

    /**
     * Get boolean filter count
     */
    private static function getBooleanFilterCount(Collection $booleanColumns): int
    {
        return collect(request()->all())
            ->filter(function($value, $key) use ($booleanColumns) {
                return $booleanColumns->contains('name', $key) && $value !== null && $value !== '';
            })->count();
    }

    /**
     * Get path filter count
     */
    private static function getPathFilterCount(Collection $pathFields): int
    {
        return collect(request()->all())
            ->filter(function($value, $key) use ($pathFields) {
                return $pathFields->contains('name', $key) && $value !== null && $value !== '';
            })->count();
    }

    /**
     * Get relation filter count
     */
    private static function getRelationFilterCount(Collection $relationColumns): int
    {
        return collect(request()->all())
            ->filter(function($value, $key) use ($relationColumns) {
                if ($key === 'page_filter' && $value !== null && $value !== '') {
                    return true;
                }
                return $relationColumns->contains('name', $key) && $value !== null && $value !== '';
            })->count();
    }

    /**
     * Get relation display value for active filters
     */
    private static function getRelationDisplayValue($columnName, $value, $crud): string
    {
        // Get related model using the same improved logic
        $relationName = str_replace('_id', '', $columnName);
        $relatedModel = null;
        
        // Check if the relation method exists in the model
        if (method_exists($crud->model, $relationName)) {
            try {
                $relationQuery = $crud->model->$relationName();
                $relatedModel = get_class($relationQuery->getRelated());
            } catch (\Exception $e) {
                // Fallback
                $relatedModelName = Str::studly($relationName);
                $relatedModel = "App\\Models\\" . $relatedModelName;
            }
        } else {
            // Fallback
            $relatedModelName = Str::studly($relationName);
            $relatedModel = "App\\Models\\" . $relatedModelName;
        }
        
        $displayValue = 'ID: ' . $value; // Default fallback

        if (class_exists($relatedModel)) {
            $item = call_user_func([$relatedModel, 'find'], $value);
            if ($item) {
                // Check for getDisplayAttribute method first
                if (method_exists($item, 'getDisplayAttribute')) {
                    $displayValue = $item->getDisplayAttribute();
                } else {
                    $displayValue = $item->id;
                }
            }
        }

        return $displayValue;
    }
} 
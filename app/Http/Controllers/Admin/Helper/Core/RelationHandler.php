<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class RelationHandler
{
	/**
	 * Creates select fields for BelongsToMany and BelongsTo relations
	 *
	 * Handles both BelongsToMany and BelongsTo relations,
	 * creating appropriate field types with correct options.
	 *
	 * @param string $methodName Method name representing the relation
	 * @param mixed $result Result of relation query
	 * @param string $table Table name
	 */
	public static function createSelectFieldsForRelation($methodName, $result, $table)
	{
		// Get related model
		$relatedModel = $result->getRelated();
		$relatedModelClass = get_class($relatedModel);

		// Get correct table name
		$tableName = $relatedModel->getTable();

		// Ensure table exists before checking columns
		if (!Schema::hasTable($tableName)) {
			return; // Prevent errors if table doesn't exist
		}

		// Determine primary attribute dynamically
		$primaryAttribute = method_exists($relatedModel, "getDisplayAttribute")
			? $relatedModel->getDisplayAttribute()
			: $relatedModel->id;

		// Handle BelongsToMany relation
		if ($result instanceof BelongsToMany) {
			CRUD::field($methodName)
				->type("select_multiple")
				->entity($methodName)
				->wrapper(["class" => "form-group col-md-6"])
				->model($relatedModelClass)
				->attribute($primaryAttribute)
				->pivot(true);
		}
		// Handle BelongsTo relation
		elseif ($result instanceof BelongsTo) {
			// Get foreign key from relation
			$foreignKey = $result->getForeignKeyName();

			// Check if foreign key is present in URL
			$defaultValue = request($foreignKey, null);

			// Create select field with preselected value using actual foreign key name
			$field = CRUD::field($foreignKey)
				->type("select_from_array")
				->label(self::generateFieldLabel($methodName))
				->entity($methodName)
				->model($relatedModelClass)
				->wrapper(["class" => "form-group col-sm-12"])
				->allows_null(true)
				->options(
					$relatedModelClass
						::all()
						->mapWithKeys(function ($elem) use ($methodName) {
							$displayText = method_exists($elem, "getDisplayAttribute")
								? $elem->getDisplayAttribute()
								: $elem->id;
							return [$elem->id => $displayText];
						})
						->toArray()
				)
				->value($defaultValue); // Pre-fill value from URL if available

			// Determine tab dynamically
			$tabName = null;
			if ($table == "media") {
				switch ($methodName) {
					case "page":
						$tabName = "Hero";
						break;
				}
			}

			// Assign tab to field
			if ($tabName) {
				$field->tab($tabName);
			}

			// Add preview field with same tab
			$previewField = CRUD::field($foreignKey . "_preview")
				->type("custom_html")
				->value(
					'<a id="' .
						$foreignKey .
						'_preview_link" href="#" class="small text-capitalize" style="display: none;">
                        <i class="la la-link"></i> ' .
						trans("backpack::crud.open") .
						$methodName .
						' 
                    </a>'
				)
				->wrapper(["class" => "form-group col-12 z-3"]);

			if ($tabName) {
				$previewField->tab($tabName);
			}

			// Include script to dynamically update preview button
			CRUD::addField([
				"name" => "script_" . $foreignKey,
				"type" => "custom_html",
				"value" =>
					"<script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const select = document.querySelector('[name=\"" .
					$foreignKey .
					"\"]');
                            const previewLink = document.getElementById('" .
					$foreignKey .
					"_preview_link');
                            
                            // Detect if we are in edit mode
                            const isEditMode = " .
					(CRUD::getCurrentEntryId() ? "true" : "false") .
					";
                            const basePath = isEditMode ? '../../../admin/' : '../../admin/';
                            const routeName = '" . self::generateRouteSlug($methodName) . "';
            
                            if (select && previewLink) {
                                select.addEventListener('change', function () {
                                    const selectedId = select.value;
                                    if (selectedId) {
                                        previewLink.href = basePath + routeName + '/' + selectedId + '/edit';
                                        previewLink.style.display = 'inline-block';
                                    } else {
                                        previewLink.style.display = 'none';
                                    }
                                });
            
                                const initialSelectedId = select.value;
                                if (initialSelectedId) {
                                    previewLink.href = basePath + routeName + '/' + initialSelectedId + '/edit';
                                    previewLink.style.display = 'inline-block';
                                }
                            }
                        });
                    </script>",
				"wrapper" => ["class" => ""],
				"tab" => $tabName, // Assign same tab dynamically
			]);
		}
	}

	/**
	 * Creates a list interface for HasMany relations
	 *
	 * Generates a field with list of related entries
	 * and a button to add new ones
	 *
	 * @param string $methodName Method name representing the relation
	 * @param mixed $result Result of relation query
	 * @param string $table Table name
	 */
	public static function createHasManyRelationList($methodName, $result, $table)
	{
		// Ensure relation is of type HasMany
		if (!($result instanceof HasMany)) {
			return;
		}

		// Get related model and its class
		$relatedModel = $result->getRelated();
		$relatedModelClass = get_class($relatedModel);

		// Get current entry ID (if in edit mode)
		$id = CRUD::getCurrentEntryId();

		// If no ID exists, we're in creation mode, so don't show button
		if (!$id) {
			return;
		}

		$relatedModelBaseName = class_basename($relatedModelClass);

		// Convert to kebab-case (for URL consistency)
		$relatedModelUrlSegment = Str::kebab($relatedModelBaseName); // Example: invoice_item → invoice-item

		// Get foreign key (already correct)
		$foreignKey = $result->getForeignKeyName();

		// Get related entries
		$relatedEntries = $relatedModelClass::where($foreignKey, $id)->get();

		// Build related items list
		$relationList = "";
		if ($relatedEntries->count() > 0) {
			$relationList .= '<ul style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">';
			foreach ($relatedEntries as $entry) {
				$entryId = $entry->id;
				$entryLabel = $entry->name ?? ($entry->title ?? "ID: $entryId"); // Use meaningful field
				$relationList .=
					'<li style="margin-bottom: 4px;">
                                    <a href="' .
					url("admin/{$relatedModelUrlSegment}/" . $entryId . "/edit") .
					'" >
                                        <i class="la la-link"></i> ' .
					e($entryLabel) .
					'
                                    </a>
                                </li>';
			}
		} else {
			$relationList .= '<span style="color: #999; font-size: 13px;">' . trans("backpack::crud.infoEmpty") . "</span>";
		}
		$relationList .= "</ul>";

		// Create custom HTML field in "Relations" tab, with title, button and list of related items
		CRUD::addField([
			"name" => $methodName . "_relation_section",
			"type" => "custom_html",
			"value" =>
				'<div style="display: flex; flex-direction: column; align-items: flex-start; gap: 5px;">
                    <h5 style="margin-bottom: 5px; font-weight: bold;">' .
				ucfirst($methodName) .
				'</h5>
                    <a href="' .
				url("admin/{$relatedModelUrlSegment}/create?{$foreignKey}=" . $id) .
				'" 
                    class="btn btn-primary reset-btn align-items-center" style="font-size: 0.75rem;padding-top: 0.125rem !important;padding-bottom: 0.125rem !important;padding-left: 0.25rem !important;padding-right: 0.25rem !important;">
                        <i class="la la-plus"></i> ' .
				trans("backpack::crud.add") .
				ucfirst($methodName) .
				'
                    </a>
                    ' .
				$relationList .
				'
                </div>
                <style>
                    .relation-link {
                        text-decoration: none;
                        color: #007bff;
                        font-size: 14px;
                        transition: color 0.2s ease-in-out;
                    }
                    .relation-link:hover {
                        color: #0056b3;
                        text-decoration: underline;
                    }
                </style>',
			"wrapper" => ["class" => "form-group col-md-12"],
			"tab" => trans("backpack::crud.relations"),
		]);
	}

	/**
	 * Creates columns for displaying BelongsToMany or HasMany relations in CRUD List operation
	 *
	 * Generates custom HTML columns showing related entries with links
	 *
	 * @param string $methodName Method name representing the relation
	 * @param mixed $result Result of relation query
	 * @param string $backendUrl Backend CRUD URL
	 */
	public static function createRelationalFieldsView($methodName, $result, $backendUrl)
	{
		// Get related model and details
		$relatedModel = $result->getRelated();
		$relatedModelName = class_basename($relatedModel);
		$formattedRelatedModelName = strtolower(preg_replace("/([a-z])([A-Z])/", '$1-$2', $relatedModelName));
		$formattedRelatedModelName = str_replace("_", "-", $formattedRelatedModelName);
		$relatedModelClass = get_class($relatedModel);
		$tableModel = $relatedModel->getTable();

		// Format label by splitting camelCase into separate words
		$readableLabel = ucwords(preg_replace("/(?<!^)[A-Z]/", ' $0', $methodName));

		// Create column for related model name with links
		CRUD::column($methodName)
			->type("custom_html")
			->entity($methodName)
			->label($readableLabel)
			->model($relatedModelClass)
			->value(function ($entry) use ($formattedRelatedModelName, $methodName, $backendUrl) {
				$links = [];
				foreach ($entry->$methodName as $elem) {
					$links[] =
						'<a target="_blank" href="' .
						$backendUrl .
						"/" .
						$formattedRelatedModelName .
						"/" .
						$elem->id .
						'/edit">' .
						(method_exists($elem, "getDisplayAttribute") ? $elem->getDisplayAttribute() : $elem->id) .
						"</a>";
				}
				return implode(", ", $links);
			});

		// Create column for pivot table percentage attribute for BelongsToMany relations
		if ($result instanceof BelongsToMany) {
			CRUD::column($methodName . "_pivot")
				->label($readableLabel . " Pivot")
				->type("custom_html")
				->entity($methodName)
				->model($relatedModelClass)
				->value(function ($entry) use ($methodName, $backendUrl) {
					$pivotTableName = $entry->$methodName()->getTable();
					$pivotTableName = str_replace("_", "-", $pivotTableName);
					$links = [];
					foreach ($entry->$methodName as $elem) {
						$pivotId = $elem->pivot->id;
						$links[] =
							'<a target="_blank" href="' .
							$backendUrl .
							"/" .
							$pivotTableName .
							"/" .
							$pivotId .
							'/edit">' .
							(method_exists($elem, "getDisplayAttribute") ? $elem->getDisplayAttribute() : $elem->id) .
							"</a>";
					}
					return implode(", ", $links);
				});
		}
	}

	/**
	 * Configures columns for foreign key relations in list view
	 *
	 * Creates links to related entries with appropriate display text
	 *
	 * @param string $column Column name
	 * @param string $relationName Relation name
	 * @param string $projectBaseUrl Project base URL
	 */
	public static function configureRelationColumnView($column, $relationName, $projectBaseUrl)
	{
		// Deal with camelCase relation names (e.g. backpackRole -> backpack_role)
		$camelCaseRelationName = Str::camel($relationName);

		CRUD::column($column)
			->label(ucwords(str_replace("_", " ", $relationName)))
			->type("custom_html")
			->value(function ($entry) use ($relationName, $camelCaseRelationName, $projectBaseUrl) {
				// Try with original name first
				$relation = $entry->$relationName ?? null;

				// If relation is null, try with camelCase version
				if ($relation === null && $relationName !== $camelCaseRelationName) {
					$relation = $entry->$camelCaseRelationName ?? null;
				}

				if ($relation) {
					$id = $relation->id;

					$displayText = method_exists($relation, "getDisplayAttribute")
						? $relation->getDisplayAttribute()
						: $relation->name ?? ($relation->title ?? $id);

					$modelName = strtolower(class_basename($relation));

					return '<a target="_blank" href="' .
						$projectBaseUrl .
						"/" .
						Str::kebab($modelName) .
						"/" .
						$id .
						'/edit">' .
						$displayText .
						"</a>";
				}

				return null;
			});
	}

	/**
	 * Generate appropriate field label for relation
	 *
	 * @param string $methodName Method name representing the relation
	 * @return string Generated label
	 */
	private static function generateFieldLabel($methodName): string
	{
		// Convert camelCase to readable format
		$readable = ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $methodName));
		
		// Don't add "Role" if the name already contains "role" (case insensitive)
		if (stripos($methodName, 'role') === false) {
			$readable .= ' Role';
		}
		
		return $readable;
	}

	/**
	 * Generate route slug from method name (converts camelCase to kebab-case)
	 *
	 * @param string $methodName Method name representing the relation
	 * @return string Route slug in kebab-case
	 */
	private static function generateRouteSlug($methodName): string
	{
		// Convert camelCase to kebab-case
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $methodName));
	}
}

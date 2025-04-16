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
		$primaryAttribute = collect([
			"email",
			"transaction_id",
			"import",
			"name",
			"title_italian",
			"title",
			"title_english",
			"id",
		])->first(fn($column) => Schema::hasColumn($tableName, $column), "id"); // Default to "id";

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

			// Create select field with preselected value
			$field = CRUD::field($methodName . "_id")
				->type("select_from_array")
				->model($relatedModelClass)
				->wrapper(["class" => "form-group col-sm-12"])
				->options(
					$relatedModelClass
						::all()
						->mapWithKeys(function ($elem) use ($methodName, $primaryAttribute) {
							$displayText = $elem->$primaryAttribute ?: $elem->id;
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
			$previewField = CRUD::field($methodName . "_preview")
				->type("custom_html")
				->value(
					'<a id="' .
						$methodName .
						'_preview_link" href="#" class="btn btn-sm btn-link" style="display: none;">
                        <i class="la la-question-circle"></i> ' .
						trans("backpack::crud.open") .
						$methodName .
						' 
                    </a>'
				)
				->wrapper(["class" => "form-group col-md-1 z-3"]);

			if ($tabName) {
				$previewField->tab($tabName);
			}

			// Include script to dynamically update preview button
			CRUD::addField([
				"name" => "script_" . $methodName,
				"type" => "custom_html",
				"value" =>
					"<script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const select = document.querySelector('[name=\"" .
					$methodName .
					"_id\"]');
                            const previewLink = document.getElementById('" .
					$methodName .
					"_preview_link');
                            
                            // Detect if we are in edit mode
                            const isEditMode = " .
					(CRUD::getCurrentEntryId() ? "true" : "false") .
					";
                            const basePath = isEditMode ? '../../../admin/' : '../../admin/';
            
                            if (select && previewLink) {
                                select.addEventListener('change', function () {
                                    const selectedId = select.value;
                                    if (selectedId) {
                                        previewLink.href = basePath + '" .
					$methodName .
					"/' + selectedId + '/edit';
                                        previewLink.style.display = 'inline-block';
                                    } else {
                                        previewLink.style.display = 'none';
                                    }
                                });
            
                                const initialSelectedId = select.value;
                                if (initialSelectedId) {
                                    previewLink.href = basePath + '" .
					$methodName .
					"/' + initialSelectedId + '/edit';
                                    previewLink.style.display = 'inline-block';
                                }
                            }
                        });
                    </script>",
				"wrapper" => ["class" => "form-group col-md-1"],
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
		$relationList = '<ul style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">';
		if ($relatedEntries->count() > 0) {
			foreach ($relatedEntries as $entry) {
				$entryId = $entry->id;
				$entryLabel = $entry->name ?? ($entry->title ?? "ID: $entryId"); // Use meaningful field
				$relationList .=
					'<li style="margin-bottom: 4px;">
                                    <a href="' .
					url("admin/{$relatedModelUrlSegment}/" . $entryId . "/edit") .
					'"  
                                    class="relation-link">
                                        <i class="la la-file"></i> ' .
					e($entryLabel) .
					'
                                    </a>
                                </li>';
			}
		} else {
			$relationList .= '<li style="color: #999; font-size: 13px;">No related records found.</li>';
		}
		$relationList .= "</ul>";

		// Create custom HTML field in "Associations" tab, with title, button and list of related items
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
                    class="btn btn-sm btn-primary"
                    style="padding: 4px 10px; font-size: 13px;">
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
			"tab" => trans("backpack::crud.associations"),
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

		// Determine attribute to use based on column availability
		$primaryAttribute = collect([
			"email",
			"transaction_id",
			"import",
			"name",
			"title_it",
			"title",
			"title_en",
			"id",
		])->first(fn($column) => Schema::hasColumn($tableModel, $column), "id");

		// Create column for related model name with links
		CRUD::column($methodName)
			->type("custom_html")
			->entity($methodName)
			->model($relatedModelClass)
			->value(function ($entry) use ($formattedRelatedModelName, $methodName, $primaryAttribute, $backendUrl) {
				$links = [];
				foreach ($entry->$methodName as $elem) {
					$displayValue = $elem->$primaryAttribute ?: $elem->id; // Ensure fallback to ID if attribute missing

					$links[] =
						'<a target="_blank" href="' .
						$backendUrl .
						"/" .
						$formattedRelatedModelName .
						"/" .
						$elem->id .
						'/edit">' .
						($primaryAttribute == "import" ? "€" : "") .
						$displayValue .
						"</a>";
				}
				return implode(", ", $links);
			});

		// Create column for pivot table percentage attribute for BelongsToMany relations
		if ($result instanceof BelongsToMany) {
			CRUD::column($methodName . "_pivot")
				->label(ucfirst($methodName . " pivot"))
				->type("custom_html")
				->entity($methodName)
				->model($relatedModelClass)
				->value(function ($entry) use ($methodName, $primaryAttribute, $backendUrl) {
					$pivotTableName = $entry->$methodName()->getTable();
					$pivotTableName = str_replace("_", "-", $pivotTableName);
					$links = [];
					foreach ($entry->$methodName as $elem) {
						$displayValue = $elem->$primaryAttribute ?: $elem->id; // Ensure fallback to ID if attribute missing
						$percentage = $elem->pivot->percentage;
						$pivotId = $elem->pivot->id;
						$links[] =
							'<a target="_blank" href="' .
							$backendUrl .
							"/" .
							$pivotTableName .
							"/" .
							$pivotId .
							'/edit">' .
							$displayValue .
							" (" .
							$percentage .
							"%)</a>";
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
		CRUD::column($column)
			->label(ucfirst($relationName))
			->type("custom_html")
			->value(function ($entry) use ($relationName, $projectBaseUrl) {
				if ($entry->$relationName) {
					$id = $entry->$relationName->id;

					// Determine display text based on available attributes
					$displayText = match (true) {
						isset($entry->$relationName->email) => $entry->$relationName->email,
						isset($entry->$relationName->surname, $entry->$relationName->name) => $entry->$relationName->name .
							" " .
							$entry->$relationName->surname,
						isset($entry->$relationName->author_surname, $entry->$relationName->author_name) => $entry
							->$relationName->author_name .
							" " .
							$entry->$relationName->author_surname,
						isset($entry->$relationName->title) => $entry->$relationName->title,
						isset($entry->$relationName->name) => $entry->$relationName->name,
						default => $id,
					};

					return '<a target="_blank" href="' .
						$projectBaseUrl .
						"/" .
						strtolower(class_basename($entry->$relationName)) .
						"/" .
						$id .
						'/edit">' .
						$displayText .
						"</a>";
				}
			});
	}
}

<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class RelationHandler
{
	// Track if we've added the separator for external relations
	private static $hasManySeparatorAdded = false;
	// Track if we've added the preview modal
	private static $previewModalAdded = false;

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

		// Determine tab dynamically
		$tabName = null;
		if ($table == "media") {
			switch ($methodName) {
				case "page":
					$tabName = "Hero";
					break;
			}
		}
		// For translates table, put relations in "Dati" tab since there's only one mandatory relation
		if ($table == "translates") {
			$tabName = "Dati";
		}
		$finalTabName = $tabName ? $tabName : "Relazioni";

		// Get current entry ID for edit mode
		$currentEntryId = CRUD::getCurrentEntryId();
		$isEditMode = $currentEntryId ? true : false;
		$basePath = $isEditMode ? "../../../admin/" : "../../admin/";
		$routeName = self::generateRouteSlug($methodName);

		// Handle BelongsToMany relation
		if ($result instanceof BelongsToMany) {
			// Get related entries if in edit mode
			$relatedEntries = [];
			if ($currentEntryId) {
				$currentEntry = CRUD::getCurrentEntry();
				if ($currentEntry && method_exists($currentEntry, $methodName)) {
					$relatedEntries = $currentEntry->$methodName;
				}
			}

			// Build buttons HTML for each related entry
			$buttonsHtml = "";
			if (count($relatedEntries) > 0) {
				foreach ($relatedEntries as $entry) {
					// Use getRouteKey() to respect custom route key names (e.g., numero_pratica, codice)
					$entryRouteKey = method_exists($entry, "getRouteKeyName") ? $entry->getRouteKey() : $entry->getKey();

					$entryId = $entryRouteKey;

					$displayText = method_exists($entry, "getDisplayAttribute")
						? $entry->getDisplayAttribute()
						: $entry->name ?? ($entry->title ?? ($entry->numero_pratica ?? "ID: $entryId"));

					$buttonsHtml .= self::generateRelationButtonHtml(
						$methodName,
						$entryId,
						$displayText,
						$routeName,
						$basePath,
						$relatedModelClass
					);
				}
			} else {
				$buttonsHtml .=
					'<span style="color: #9ca3af; font-size: 0.875rem; font-style: italic;">Nessuna relazione selezionata</span>';
			}

			// Create custom HTML field with buttons
			CRUD::addField([
				"name" => $methodName . "_relation_buttons",
				"type" => "custom_html",
				"label" => self::generateFieldLabel($methodName),
				"value" =>
					'<div class="relation-buttons-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">' .
					$buttonsHtml .
					"</div>",
				"wrapper" => ["class" => "form-group col-md-12"],
				"tab" => $finalTabName,
			]);

			// Add hidden select field for form submission (maintains compatibility)
			CRUD::field($methodName)
				->type("select_multiple")
				->entity($methodName)
				->wrapper(["class" => "form-group col-md-6 d-none"])
				->model($relatedModelClass)
				->attribute("id")
				->pivot(true)
				->tab($finalTabName);
		}
		// Handle BelongsTo relation - keep select field editable, add inline preview button in label
		elseif ($result instanceof BelongsTo) {
			// Get foreign key from relation
			$foreignKey = $result->getForeignKeyName();

			// Get value from current entry if in edit mode, otherwise from request
			$defaultValue = $currentEntryId
				? CRUD::getCurrentEntry()->{$foreignKey} ?? request($foreignKey, null)
				: request($foreignKey, null);

			// Get model name for preview
			$modelName = class_basename($relatedModelClass);

			// Get owner key name for finding the related model
			$ownerKeyName = $result->getOwnerKeyName();

			// Find the related model to get its route key (if foreign key value exists)
			$routeKeyValue = null;
			if ($defaultValue) {
				$relatedEntry = $relatedModelClass::where($ownerKeyName, $defaultValue)->first();
				if ($relatedEntry) {
					$routeKeyValue = method_exists($relatedEntry, "getRouteKeyName")
						? $relatedEntry->getRouteKey()
						: $relatedEntry->getKey();
				}
			}

			// Generate inline preview button HTML
			$fieldName = ucwords(str_replace("_", " ", $foreignKey));
			$inlineButtonHtml = self::generateInlinePreviewButton(
				$foreignKey,
				$routeKeyValue ?? $defaultValue, // Use route key if found, otherwise fallback to foreign key value
				$routeName,
				$basePath,
				$modelName,
				$relatedModelClass,
				$fieldName
			);

			// Create label with inline preview button
			$labelHtml =
				'<div class="d-flex align-items-center justify-content-between gap-2" style="width: 100%;">
				<span>' .
				e(self::generateFieldLabel($methodName)) .
				'</span>
				' .
				$inlineButtonHtml .
				'
			</div>';

			// Get owner key name (the column in the related model that the foreign key references)
			$ownerKeyName = $result->getOwnerKeyName();

			// Build options array with route keys as data attributes
			$options = [];
			$routeKeysMap = []; // Map owner key value => route key value

			foreach ($relatedModelClass::all() as $elem) {
				// Use owner key value (e.g., numero_pratica) as the option key, not getKey() (id)
				$optionKey = isset($elem->$ownerKeyName) ? $elem->$ownerKeyName : $elem->getKey();

				// Get route key for this element
				$routeKey = method_exists($elem, "getRouteKeyName") ? $elem->getRouteKey() : $elem->getKey();

				$routeKeysMap[$optionKey] = $routeKey;

				$displayText = method_exists($elem, "getDisplayAttribute")
					? $elem->getDisplayAttribute()
					: $elem->name ?? ($elem->title ?? ($elem->numero_pratica ?? $optionKey));

				$options[$optionKey] = $displayText;
			}

			// Create select field (editable, not hidden) - this is a foreign key field that needs to be modifiable
			$field = CRUD::field($foreignKey)
				->type("select_from_array")
				->label($labelHtml)
				->set("label_html", true)
				->entity($methodName)
				->model($relatedModelClass)
				->wrapper(["class" => "form-group col-sm-12"])
				->allows_null(true)
				->options($options)
				->value($defaultValue)
				->attributes([
					"class" => "form-control relation-preview-select",
					"data-relation-field" => $foreignKey,
					"data-route-keys" => json_encode($routeKeysMap), // Store route keys map as JSON
				])
				->tab($finalTabName);
		}

		// Add modal HTML and JavaScript (only once)
		if (!self::$previewModalAdded) {
			self::$previewModalAdded = true;
			CRUD::addField([
				"name" => "relation_preview_modal",
				"type" => "custom_html",
				"value" => self::generateModalHtml(),
				"wrapper" => ["class" => ""],
				"tab" => $finalTabName,
			]);
		}
	}

	/**
	 * Generate HTML for relation preview button
	 *
	 * @param string $methodName Relation method name
	 * @param int $entryId Related entry ID
	 * @param string $displayText Display text for button
	 * @param string $routeName Route slug
	 * @param string $basePath Base path for URLs
	 * @param string $relatedModelClass Related model class
	 * @return string HTML for button
	 */
	/**
	 * Generate inline preview button for BelongsTo relations (internal)
	 *
	 * @param string $foreignKey Foreign key field name
	 * @param mixed $entryId Related entry ID
	 * @param string $routeName Route slug
	 * @param string $basePath Base path for URLs
	 * @param string $modelName Model name
	 * @param string $relatedModelClass Related model class
	 * @return string HTML for inline button
	 */
	private static function generateInlinePreviewButton(
		$foreignKey,
		$entryId,
		$routeName,
		$basePath,
		$modelName,
		$relatedModelClass,
		$fieldName = null
	) {
		$disabled = $entryId ? "" : "disabled";
		$editUrl = $entryId ? $basePath . $routeName . "/" . $entryId . "/edit" : "";

		// Generate tooltip text based on whether relation is selected
		if ($fieldName === null) {
			$fieldName = ucwords(str_replace("_", " ", $foreignKey));
		}
		$tooltipText = $entryId
			? trans("backpack::crud.relation_preview_tooltip")
			: trans("backpack::crud.relation_preview_tooltip_no_relation", ["fieldName" => $fieldName]);
		$tooltipTextNoRelation = trans("backpack::crud.relation_preview_tooltip_no_relation", ["fieldName" => $fieldName]);
		$tooltipTextWithRelation = trans("backpack::crud.relation_preview_tooltip");

		return '<button type="button"
				class="btn btn-sm btn-outline-primary relation-preview-btn relation-preview-inline"
				data-relation-field="' .
			e($foreignKey) .
			'"
				data-entry-id="' .
			e($entryId ?? "") .
			'"
				data-route-name="' .
			$routeName .
			'"
				data-base-path="' .
			$basePath .
			'"
				data-edit-url="' .
			$editUrl .
			'"
				data-model-name="' .
			e($modelName) .
			'"
				data-tooltip-no-relation="' .
			e($tooltipTextNoRelation) .
			'"
				data-tooltip-with-relation="' .
			e($tooltipTextWithRelation) .
			'"
				data-tooltip-text="' .
			e($tooltipText) .
			'"
				' .
			$disabled .
			'
				style="padding: 0.2rem 0.5rem; flex-shrink: 0; position: relative;">
			<i class="las la-eye" style="font-size: 0.875rem;"></i>
			<span class="relation-preview-tooltip">' .
			e($tooltipText) .
			'</span>
		</button>';
	}

	private static function generateRelationButtonHtml(
		$methodName,
		$entryId,
		$displayText,
		$routeName,
		$basePath,
		$relatedModelClass
	) {
		$editUrl = $basePath . $routeName . "/" . $entryId . "/edit";
		$previewUrl = $basePath . $routeName . "/" . $entryId . "/show";
		$modalId = "relationModal_" . $methodName . "_" . $entryId;

		// Get model name from class
		$modelName = class_basename($relatedModelClass);

		// Generate tooltip text - for relation buttons, entryId is always present (they're only shown when relation exists)
		$fieldName = ucwords(preg_replace("/(?<!^)[A-Z]/", ' $0', $methodName));
		$tooltipText = trans("backpack::crud.relation_preview_tooltip");

		return '<a href="#"
				class="relation-badge relation-preview-btn relation-preview-badge"
				data-entry-id="' .
			$entryId .
			'"
				data-route-name="' .
			$routeName .
			'"
				data-base-path="' .
			$basePath .
			'"
				data-edit-url="' .
			$editUrl .
			'"
				data-method-name="' .
			$methodName .
			'"
				data-model-name="' .
			e($modelName) .
			'"
				data-tooltip-text="' .
			e($tooltipText) .
			'"
				style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.6rem; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; font-size: 0.75rem; font-weight: 500; transition: all 0.2s ease; line-height: 1.2; cursor: pointer; position: relative;">
			<i class="las la-eye" style="font-size: 0.7rem; color: #6b7280;"></i>
			<span>' .
			e($displayText) .
			'</span>
			<span class="relation-preview-tooltip relation-preview-tooltip-badge">' .
			e($tooltipText) .
			'</span>
		</a>';
	}

	/**
	 * Generate modal HTML for relation preview
	 *
	 * @return string Modal HTML
	 */
	private static function generateModalHtml()
	{
		return '<div id="relationPreviewModal" class="custom-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1055; overflow: auto;">
			<div class="custom-modal-backdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1054;"></div>
			<div class="custom-modal-dialog" style="position: relative; z-index: 1055; margin: 1rem auto; max-width: 600px; width: calc(100% - 2rem); max-height: 90vh; display: flex; align-items: center; min-height: calc(100% - 2rem);">
				<div class="custom-modal-content" style="background: white; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
					<div class="custom-modal-header" style="padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
						<h5 class="custom-modal-title" id="relationPreviewModalLabel" style="font-size: 1rem; font-weight: 600; margin: 0;">Preview Relazione</h5>
						<button type="button" class="custom-modal-close" aria-label="Close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; color: #6b7280;">&times;</button>
					</div>
					<div class="custom-modal-body" id="relationPreviewModalBody" style="padding: 1rem; min-height: 300px; max-height: calc(90vh - 120px); overflow-y: auto; flex: 1;">
						<div class="text-center py-4">
							<div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
								<span class="visually-hidden">Caricamento...</span>
							</div>
							<p class="mt-2 text-muted" style="font-size: 0.875rem;">Caricamento preview...</p>
						</div>
					</div>
					<div class="custom-modal-footer" style="padding: 0.75rem 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.5rem; flex-shrink: 0;">
						<button type="button" class="btn btn-secondary btn-sm custom-modal-close-btn">Chiudi</button>
						<a id="relationPreviewEditLink" href="#" class="btn btn-primary btn-sm" target="_blank">
							<i class="la la-external-link"></i> Apri Pagina Dedicata
						</a>
					</div>
				</div>
			</div>
		</div>
		<style>
			.custom-modal {
				animation: fadeIn 0.2s ease;
			}
			.custom-modal-backdrop {
				animation: fadeIn 0.2s ease;
			}
			.custom-modal-dialog {
				animation: slideDown 0.3s ease;
			}
			@keyframes fadeIn {
				from { opacity: 0; }
				to { opacity: 1; }
			}
			@keyframes slideDown {
				from { transform: translateY(-20px); opacity: 0; }
				to { transform: translateY(0); opacity: 1; }
			}
			@media (min-width: 576px) {
				.custom-modal-dialog {
					max-width: 500px;
				}
			}
			@media (min-width: 768px) {
				.custom-modal-dialog {
					max-width: 550px;
				}
			}
			@media (min-width: 992px) {
				.custom-modal-dialog {
					max-width: 600px;
				}
			}
			@media (min-width: 1200px) {
				.custom-modal-dialog {
					max-width: 650px;
				}
			}
			.custom-modal-close:hover {
				color: #16108a !important;
			}
			#relationPreviewModalBody .card {
				margin-bottom: 0.5rem;
			}
			#relationPreviewModalBody .card-header {
				padding: 0.5rem 0.75rem;
				font-size: 0.875rem;
			}
			#relationPreviewModalBody .card-body {
				padding: 0.75rem;
				font-size: 0.875rem;
			}
			#relationPreviewModalBody table {
				font-size: 0.875rem;
			}
			#relationPreviewModalBody .table td,
			#relationPreviewModalBody .table th {
				padding: 0.5rem;
			}
			/* Ensure bp-datagrid styles work correctly in modal */
			#relationPreviewModalBody .bp-datagrid {
				display: grid;
				grid-gap: 1.5rem;
				grid-template-columns: 1fr;
			}
			#relationPreviewModalBody .bp-datagrid-title {
				font-size: .625rem;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: .04em;
				line-height: 1rem;
				color: #66626c;
				margin-bottom: 0.25rem;
			}
			#relationPreviewModalBody .bp-datagrid-content {
				font-size: 0.875rem;
				color: #241f2d;
			}
			/* Small screens (≥768px) — 6 columns */
			@media (min-width: 768px) {
				#relationPreviewModalBody .bp-datagrid {
					grid-template-columns: repeat(6, 1fr);
				}
				#relationPreviewModalBody .bp-datagrid-item.size-1 {
					grid-column: span 1 / span 1;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-2 {
					grid-column: span 2 / span 2;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-3 {
					grid-column: span 3 / span 3;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-4 {
					grid-column: span 4 / span 4;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-5 {
					grid-column: span 5 / span 5;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-6 {
					grid-column: span 6 / span 6;
				}
			}
			/* Large screens (≥1024px) — 12 columns */
			@media (min-width: 1024px) {
				#relationPreviewModalBody .bp-datagrid {
					grid-template-columns: repeat(12, 1fr);
				}
				#relationPreviewModalBody .bp-datagrid-item.size-1 {
					grid-column: span 1 / span 1;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-2 {
					grid-column: span 2 / span 2;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-3 {
					grid-column: span 3 / span 3;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-4 {
					grid-column: span 4 / span 4;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-5 {
					grid-column: span 5 / span 5;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-6 {
					grid-column: span 6 / span 6;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-7 {
					grid-column: span 7 / span 7;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-8 {
					grid-column: span 8 / span 8;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-9 {
					grid-column: span 9 / span 9;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-10 {
					grid-column: span 10 / span 10;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-11 {
					grid-column: span 11 / span 11;
				}
				#relationPreviewModalBody .bp-datagrid-item.size-12 {
					grid-column: span 12 / span 12;
				}
			}
		</style>
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			// Function to update tooltip state based on select value
			function updateTooltipState(inlineButton, selectElement) {
				if (!inlineButton || !selectElement) return;

				const tooltipElement = inlineButton.querySelector(".relation-preview-tooltip");
				if (!tooltipElement) return;

				const foreignKeyValue = selectElement.value;

				if (foreignKeyValue && foreignKeyValue !== "") {
					// Get route key from data attribute map
					const routeKeysMapJson = selectElement.getAttribute("data-route-keys");
					let routeKey = foreignKeyValue;

					if (routeKeysMapJson) {
						try {
							const routeKeysMap = JSON.parse(routeKeysMapJson);
							routeKey = routeKeysMap[foreignKeyValue] || foreignKeyValue;
						} catch (e) {
							// Invalid JSON, use fallback
						}
					}

					inlineButton.setAttribute("data-entry-id", routeKey);
					inlineButton.removeAttribute("disabled");
					inlineButton.classList.remove("disabled");
					inlineButton.style.opacity = "1";
					inlineButton.style.cursor = "pointer";

					// Update tooltip when relation is selected
					const tooltipWithRelation = inlineButton.getAttribute("data-tooltip-with-relation");
					if (tooltipWithRelation) {
						tooltipElement.textContent = tooltipWithRelation;
						tooltipElement.style.padding = "6px 10px";
						tooltipElement.style.opacity = "";
					}
				} else {
					inlineButton.setAttribute("data-entry-id", "");
					inlineButton.setAttribute("disabled", "disabled");
					inlineButton.classList.add("disabled");
					inlineButton.style.opacity = "0.5";
					inlineButton.style.cursor = "not-allowed";

					// Update tooltip when relation is not selected
					const tooltipNoRelation = inlineButton.getAttribute("data-tooltip-no-relation");
					if (tooltipNoRelation) {
						tooltipElement.textContent = tooltipNoRelation;
						tooltipElement.style.padding = "8px 12px";
						tooltipElement.style.opacity = "";
					}
				}
			}

			// Custom tooltip handler for relation preview buttons
			function setupCustomTooltips() {
				// Setup tooltips for inline buttons
				document.querySelectorAll(".relation-preview-inline").forEach(function(btn) {
					const tooltip = btn.querySelector(".relation-preview-tooltip");
					if (!tooltip) return;

					// Find associated select element
					const fieldName = btn.getAttribute("data-relation-field");
					if (fieldName) {
						const selectElement = document.querySelector(\'select[name="\' + fieldName + \'"], select[data-relation-field="\' + fieldName + \'"]\');
						if (selectElement) {
							// Update tooltip state based on current select value
							updateTooltipState(btn, selectElement);
						}
					}

					// Set initial padding based on whether relation is selected
					const entryId = btn.getAttribute("data-entry-id");
					if (entryId && entryId !== "") {
						tooltip.style.padding = "6px 10px";
					} else {
						tooltip.style.padding = "8px 12px";
					}

					// Show tooltip immediately on mouseenter
					btn.addEventListener("mouseenter", function() {
						tooltip.classList.add("show");
					});

					// Hide tooltip on mouseleave
					btn.addEventListener("mouseleave", function() {
						tooltip.classList.remove("show");
					});
				});

				// Setup tooltips for badge buttons (positioned above)
				document.querySelectorAll(".relation-preview-badge").forEach(function(btn) {
					const tooltip = btn.querySelector(".relation-preview-tooltip");
					if (!tooltip) return;

					// Badge buttons always have a relation, so use smaller padding
					tooltip.style.padding = "6px 10px";

					// Show tooltip immediately on mouseenter
					btn.addEventListener("mouseenter", function() {
						tooltip.classList.add("show");
					});

					// Hide tooltip on mouseleave
					btn.addEventListener("mouseleave", function() {
						tooltip.classList.remove("show");
					});
				});
			}

			// Initialize tooltips
			setupCustomTooltips();

			const modalElement = document.getElementById("relationPreviewModal");
			const modalBackdrop = modalElement.querySelector(".custom-modal-backdrop");
			const modalCloseBtn = modalElement.querySelector(".custom-modal-close");
			const modalCloseBtnFooter = modalElement.querySelector(".custom-modal-close-btn");

			// Function to show modal
			function showModal() {
				modalElement.style.display = "block";
				document.body.style.overflow = "hidden";
			}

			// Function to hide modal
			function hideModal() {
				modalElement.style.display = "none";
				document.body.style.overflow = "";
			}

			// Close modal handlers
			modalCloseBtn.addEventListener("click", hideModal);
			modalCloseBtnFooter.addEventListener("click", hideModal);
			modalBackdrop.addEventListener("click", hideModal);

			// Close on ESC key
			document.addEventListener("keydown", function(e) {
				if (e.key === "Escape" && modalElement.style.display === "block") {
					hideModal();
				}
			});

			// Sync inline preview buttons with select changes for BelongsTo relations
			document.querySelectorAll(".relation-preview-select").forEach(function(select) {
				const fieldName = select.getAttribute("name") || select.getAttribute("data-relation-field");
				const inlineButton = document.querySelector(\'.relation-preview-inline[data-relation-field="\' + fieldName + \'"]\');

				if (inlineButton) {
					const routeName = inlineButton.getAttribute("data-route-name");
					const basePath = inlineButton.getAttribute("data-base-path");

					// Update on change
					select.addEventListener("change", function() {
						const foreignKeyValue = this.value;

						if (foreignKeyValue && foreignKeyValue !== "") {
							// Get route key from data attribute map
							const routeKeysMapJson = this.getAttribute("data-route-keys");
							let routeKey = foreignKeyValue;

							if (routeKeysMapJson) {
								try {
									const routeKeysMap = JSON.parse(routeKeysMapJson);
									routeKey = routeKeysMap[foreignKeyValue] || foreignKeyValue;
								} catch (e) {
									// Invalid JSON, use fallback
								}
							}

							inlineButton.setAttribute("data-edit-url", basePath + routeName + "/" + routeKey + "/edit");
						} else {
							inlineButton.setAttribute("data-edit-url", "");
						}

						// Update tooltip state
						updateTooltipState(inlineButton, this);
					});

					// Also update on page load if select has a value
					if (select.value && select.value !== "") {
						updateTooltipState(inlineButton, select);
					}
				}
			});

			// Handle relation preview button clicks
			document.querySelectorAll(".relation-preview-btn").forEach(function(btn) {
				btn.addEventListener("click", function(e) {
					e.preventDefault();
					const entryId = this.getAttribute("data-entry-id");
					if (!entryId || entryId === "") {
						return;
					}
					const routeName = this.getAttribute("data-route-name");
					const basePath = this.getAttribute("data-base-path");
					const editUrl = this.getAttribute("data-edit-url");
					const modelName = this.getAttribute("data-model-name") || "Relazione";

					// Update title
					document.getElementById("relationPreviewModalLabel").textContent = "Preview " + modelName + " #" + entryId;

					// Update edit link
					document.getElementById("relationPreviewEditLink").href = editUrl;

					// Show modal
					showModal();

					// Load preview content
					const previewUrl = basePath + routeName + "/" + entryId + "/show";
					const modalBody = document.getElementById("relationPreviewModalBody");

					// Show loading state
					modalBody.innerHTML = \'<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Caricamento...</span></div><p class="mt-3 text-muted">Caricamento preview...</p></div>\';

					// Fetch preview content
					fetch(previewUrl)
						.then(response => {
							if (!response.ok) {
								throw new Error("Errore nel caricamento della preview");
							}
							return response.text();
						})
						.then(html => {
							// Extract main content from response
							const parser = new DOMParser();
							const doc = parser.parseFromString(html, "text/html");

							// Try to find main content area
							const mainContent = doc.querySelector(".content") ||
											   doc.querySelector("main") ||
											   doc.querySelector(".card-body") ||
											   doc.querySelector("body");

							if (mainContent) {
								// Remove scripts and styles that might conflict
								mainContent.querySelectorAll("script").forEach(function(s) {
									s.remove();
								});
								mainContent.querySelectorAll("style").forEach(function(s) {
									if (!s.textContent.includes("relationPreviewModal")) {
										s.remove();
									}
								});

								// Remove action buttons from page header, but preserve the header structure
								mainContent.querySelectorAll("[bp-section=\"page-header\"]").forEach(function(header) {
									// Remove "torna alla lista" / "back to list" section
									header.querySelectorAll("[bp-section=\"page-subheading-back-button\"]").forEach(function(element) {
										element.remove();
									});

									// Remove "torna alla lista" / "back to list" links
									header.querySelectorAll("a, p, small").forEach(function(element) {
										const text = element.textContent.toLowerCase().trim();
										if (text.includes("torna alla lista") || text.includes("back to") ||
											(text.includes("torna") && text.includes("lista"))) {
											element.remove();
										}
									});

									// Remove only action buttons from header, not the entire header
									header.querySelectorAll("a, button").forEach(function(btn) {
										const text = btn.textContent.toLowerCase().trim();
										const href = btn.getAttribute("href") || "";
										const onclick = btn.getAttribute("onclick") || "";
										const buttonType = btn.getAttribute("data-button-type") || "";
										const bpButton = btn.getAttribute("bp-button") || "";

										// Remove "torna alla lista" links
										if (text.includes("torna alla lista") || text.includes("back to") ||
											(text.includes("torna") && text.includes("lista"))) {
											btn.remove();
											return;
										}

										// Remove edit buttons
										if (href.includes("/edit") || text.includes("modifica") || text.includes("edit") ||
											btn.querySelector("i.la-edit") || btn.querySelector("i.las la-edit")) {
											btn.remove();
										}
										// Remove delete buttons
										if (onclick.includes("delete") || text.includes("cancella") || text.includes("delete") ||
											text.includes("elimina") || btn.querySelector("i.la-trash") || btn.querySelector("i.las la-trash")) {
											btn.remove();
										}
										// Remove duplicate buttons
										if (onclick.includes("duplicate") || text.includes("duplica") || text.includes("duplicate") ||
											buttonType === "duplicate" || bpButton === "duplicate" ||
											btn.querySelector("i.la-clone") || btn.querySelector("i.las la-clone")) {
											btn.remove();
										}
										// Remove cancel buttons
										if (text.includes("annulla") || text.includes("cancel")) {
											btn.remove();
										}
									});

									// If header is now empty or only contains whitespace, remove it
									const headerText = header.textContent.trim();
									if (!headerText || headerText === "") {
										header.remove();
									}
								});

								// Remove action buttons from entire content (not just header)
								mainContent.querySelectorAll("a, button").forEach(function(btn) {
									const text = btn.textContent.toLowerCase().trim();
									const href = btn.getAttribute("href") || "";
									const onclick = btn.getAttribute("onclick") || "";
									const buttonType = btn.getAttribute("data-button-type") || "";
									const bpButton = btn.getAttribute("bp-button") || "";
									const isRelationLinkElement = btn.hasAttribute("data-relation-link");

									// Skip if button is inside a relation section (preserve relation buttons)
									const isInRelationSection = btn.closest(".relation-buttons-container") ||
																  btn.closest("[data-relation-section]") ||
																  btn.classList.contains("relation-preview-btn") ||
																  isRelationLinkElement;
									if (isInRelationSection) {
										return;
									}

									// Remove edit buttons
									if (href.includes("/edit") || text.includes("modifica") || text.includes("edit") ||
										btn.querySelector("i.la-edit") || btn.querySelector("i.las la-edit")) {
										btn.remove();
									}
									// Remove delete buttons
									if (onclick.includes("delete") || text.includes("cancella") || text.includes("delete") ||
										text.includes("elimina") || btn.querySelector("i.la-trash") || btn.querySelector("i.las la-trash")) {
										btn.remove();
									}
									// Remove duplicate buttons
									if (onclick.includes("duplicate") || text.includes("duplica") || text.includes("duplicate") ||
										buttonType === "duplicate" || bpButton === "duplicate" ||
										btn.querySelector("i.la-clone") || btn.querySelector("i.las la-clone")) {
										btn.remove();
									}
									// Remove cancel buttons
									if (text.includes("annulla") || text.includes("cancel")) {
										btn.remove();
									}
								});

								// Remove the "Azioni" / "Actions" row in tables (leave other relation rows intact)
								mainContent.querySelectorAll("table tr").forEach(function(row) {
									const label = row.querySelector("td strong");
									if (!label) {
										return;
									}
									const labelText = label.textContent.toLowerCase().trim();
									if (labelText === "azioni" || labelText === "actions") {
										row.remove();
									}
								});

								// Preserve cells with dash (-) which indicates empty values in Backpack
								// Do not remove cells that contain "-" as they represent null/empty values
								// Also preserve all cells in data rows to maintain table structure
								mainContent.querySelectorAll("td, th").forEach(function(cell) {
									const text = cell.textContent.trim();
									const hasVisibleContent = cell.querySelector("a, button, input, select, textarea, img, svg");
									const parentRow = cell.closest("tr");
									const isDataRow = parentRow && parentRow.querySelector("td strong");

									// Always preserve cells in data rows (they contain field labels and values)
									if (isDataRow) {
										return;
									}

									// For other cells, only remove if truly empty (no text, no content, no dash)
									if (text === "" && !hasVisibleContent) {
										const innerHTML = cell.innerHTML.trim();
										// Remove only if completely empty (not even a dash)
										if (innerHTML === "" || innerHTML === "&nbsp;") {
											cell.remove();
										}
									}
								});

								// Remove empty table rows (but preserve rows with data cells)
								mainContent.querySelectorAll("tr").forEach(function(row) {
									const cells = row.querySelectorAll("td, th");
									if (cells.length === 0) {
										row.remove();
									} else {
										// Check if all cells are empty (but preserve rows that might have "-" values)
										let allEmpty = true;
										cells.forEach(function(cell) {
											const text = cell.textContent.trim();
											const hasVisibleContent = cell.querySelector("a, button, input, select, textarea, img, svg");
											// A cell with "-" or any text is not empty
											if (text !== "" || hasVisibleContent || text === "-") {
												allEmpty = false;
											}
										});
										// Only remove if truly empty AND not a data row
										if (allEmpty && !row.querySelector("td strong")) {
											row.remove();
										}
									}
								});

								// Fix Bootstrap 4 classes to Bootstrap 5 before inserting
								let fixedContent = mainContent.innerHTML;
								// Replace Bootstrap 4 classes with Bootstrap 5 equivalents
								fixedContent = fixedContent.replace(/\bfloat-right\b/g, "float-end");
								fixedContent = fixedContent.replace(/\bfloat-left\b/g, "float-start");
								fixedContent = fixedContent.replace(/\bml-(\d+)\b/g, "ms-$1");
								fixedContent = fixedContent.replace(/\bmr-(\d+)\b/g, "me-$1");
								fixedContent = fixedContent.replace(/\bpl-(\d+)\b/g, "ps-$1");
								fixedContent = fixedContent.replace(/\bpr-(\d+)\b/g, "pe-$1");
								fixedContent = fixedContent.replace(/\btext-left\b/g, "text-start");
								fixedContent = fixedContent.replace(/\btext-right\b/g, "text-end");
								
								// Convert bp-datagrid to HTML table (like bp-datalist)
								const tempDiv = document.createElement("div");
								tempDiv.innerHTML = fixedContent;
								
								// Find all bp-datagrid elements
								tempDiv.querySelectorAll(".bp-datagrid").forEach(function(datagrid) {
									// Create table element
									const table = document.createElement("table");
									table.className = "table table-striped m-0 p-0";
									
									const tbody = document.createElement("tbody");
									
									// Convert each datagrid item to a table row
									// Filter out "Azioni" / "Actions" items before conversion
									let rowIndex = 0;
									datagrid.querySelectorAll(".bp-datagrid-item").forEach(function(item) {
										const title = item.querySelector(".bp-datagrid-title");
										const content = item.querySelector(".bp-datagrid-content");
										
										if (title && content) {
											// Check if this is the "Azioni" / "Actions" row
											const titleText = title.textContent.trim().toLowerCase();
											if (titleText === "azioni" || titleText === "actions") {
												return; // Skip this item
											}
											
											const tr = document.createElement("tr");
											
											const tdLabel = document.createElement("td");
											if (rowIndex === 0) {
												tdLabel.className = "border-top-0";
											}
											const strong = document.createElement("strong");
											strong.innerHTML = title.innerHTML;
											tdLabel.appendChild(strong);
											
											const tdValue = document.createElement("td");
											if (rowIndex === 0) {
												tdValue.className = "border-top-0";
											}
											tdValue.innerHTML = content.innerHTML;
											
											tr.appendChild(tdLabel);
											tr.appendChild(tdValue);
											tbody.appendChild(tr);
											rowIndex++;
										}
									});
									
									table.appendChild(tbody);
									
									// Replace datagrid with table
									datagrid.parentNode.replaceChild(table, datagrid);
								});
								
								modalBody.innerHTML = tempDiv.innerHTML;
							} else {
								// Fix Bootstrap 4 classes in full HTML as well
								let fixedHtml = html;
								fixedHtml = fixedHtml.replace(/\bfloat-right\b/g, "float-end");
								fixedHtml = fixedHtml.replace(/\bfloat-left\b/g, "float-start");
								fixedHtml = fixedHtml.replace(/\bml-(\d+)\b/g, "ms-$1");
								fixedHtml = fixedHtml.replace(/\bmr-(\d+)\b/g, "me-$1");
								fixedHtml = fixedHtml.replace(/\bpl-(\d+)\b/g, "ps-$1");
								fixedHtml = fixedHtml.replace(/\bpr-(\d+)\b/g, "pe-$1");
								fixedHtml = fixedHtml.replace(/\btext-left\b/g, "text-start");
								fixedHtml = fixedHtml.replace(/\btext-right\b/g, "text-end");
								
								// Convert bp-datagrid to HTML table (like bp-datalist)
								const tempDiv = document.createElement("div");
								tempDiv.innerHTML = fixedHtml;
								
								// Find all bp-datagrid elements
								tempDiv.querySelectorAll(".bp-datagrid").forEach(function(datagrid) {
									// Create table element
									const table = document.createElement("table");
									table.className = "table table-striped m-0 p-0";
									
									const tbody = document.createElement("tbody");
									
									// Convert each datagrid item to a table row
									// Filter out "Azioni" / "Actions" items before conversion
									let rowIndex = 0;
									datagrid.querySelectorAll(".bp-datagrid-item").forEach(function(item) {
										const title = item.querySelector(".bp-datagrid-title");
										const content = item.querySelector(".bp-datagrid-content");
										
										if (title && content) {
											// Check if this is the "Azioni" / "Actions" row
											const titleText = title.textContent.trim().toLowerCase();
											if (titleText === "azioni" || titleText === "actions") {
												return; // Skip this item
											}
											
											const tr = document.createElement("tr");
											
											const tdLabel = document.createElement("td");
											if (rowIndex === 0) {
												tdLabel.className = "border-top-0";
											}
											const strong = document.createElement("strong");
											strong.innerHTML = title.innerHTML;
											tdLabel.appendChild(strong);
											
											const tdValue = document.createElement("td");
											if (rowIndex === 0) {
												tdValue.className = "border-top-0";
											}
											tdValue.innerHTML = content.innerHTML;
											
											tr.appendChild(tdLabel);
											tr.appendChild(tdValue);
											tbody.appendChild(tr);
											rowIndex++;
										}
									});
									
									table.appendChild(tbody);
									
									// Replace datagrid with table
									datagrid.parentNode.replaceChild(table, datagrid);
								});
								
								modalBody.innerHTML = tempDiv.innerHTML;
							}
						})
						.catch(error => {
							modalBody.innerHTML = \'<div class="alert alert-danger"><i class="la la-exclamation-triangle"></i> Errore nel caricamento della preview: \' + error.message + \'</div>\';
						});
				});
			});
		});
		</script>';
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

		// Get the owner key value (supports custom primary keys like numero_pratica, codice, ndg)
		$currentEntry = CRUD::getCurrentEntry();
		if (!$currentEntry) {
			return;
		}

		// For HasMany, getLocalKeyName() returns the owner key column name
		// Use reflection to access protected method if needed, or use the relation's local key
		$ownerKeyName = $result->getLocalKeyName();
		$ownerKeyValue = $currentEntry->$ownerKeyName ?? $currentEntry->getKey();

		// Get related entries using the correct owner key value
		$relatedEntries = $relatedModelClass::where($foreignKey, $ownerKeyValue)->get();

		// Determine base path for URLs
		$isEditMode = true; // We're always in edit mode here since we check for $id
		$basePath = "../../../admin/";

		// Build compact related items list using buttons with preview
		$relationList = "";
		if ($relatedEntries->count() > 0) {
			$relationList .=
				'<div class="relation-buttons-container" style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem;">';
			foreach ($relatedEntries as $entry) {
				// Use getRouteKey() to respect custom route key names (e.g., numero_pratica, codice)
				$entryRouteKey = method_exists($entry, "getRouteKeyName") ? $entry->getRouteKey() : $entry->getKey();

				$entryId = $entryRouteKey;

				// Check if model has custom relation display method
				$entryLabel = method_exists($entry, "getRelationDisplayAttribute")
					? $entry->getRelationDisplayAttribute()
					: $entry->name ?? ($entry->title ?? ($entry->numero_pratica ?? "ID: $entryId")); // Fallback to existing logic

				$relationList .= self::generateRelationButtonHtml(
					$methodName,
					$entryId,
					$entryLabel,
					$relatedModelUrlSegment,
					$basePath,
					$relatedModelClass
				);
			}
			$relationList .= "</div>";
		} else {
			$relationList .=
				'<span style="color: #9ca3af; font-size: 0.75rem; font-style: italic; margin-top: 0.5rem; display: block;">' .
				trans("backpack::crud.infoEmpty") .
				"</span>";
		}

		// Add separator before first HasMany relation
		$separatorHtml = "";
		if (!self::$hasManySeparatorAdded) {
			self::$hasManySeparatorAdded = true;
			$separatorHtml = '
                <div class="relation-separator" style="margin: 1.5rem 0 1rem 0; padding-top: 1.5rem; border-top: 2px solid #e5e7eb; position: relative;">
                    <div style="position: absolute; top: -0.6rem; left: 0; background: #ffffff; padding: 0 0.5rem; color: #6b7280; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                        Relazioni Esterne
                    </div>
                </div>';
		}

		// Create compact custom HTML field in "Relazioni" tab
		CRUD::addField([
			"name" => $methodName . "_relation_section",
			"type" => "custom_html",
			"value" =>
				$separatorHtml .
				'
                <div class="relation-section-compact" style="margin-bottom: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <h6 style="margin: 0; font-size: 0.8rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.02em;">' .
				ucfirst($methodName) .
				' <span style="color: #9ca3af; font-weight: 400; font-size: 0.7rem;">(' .
				$relatedEntries->count() .
				')</span></h6>
                        <a href="' .
				url("admin/{$relatedModelUrlSegment}/create?{$foreignKey}=" . $id) .
				'" class="btn-add-relation" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; padding: 0; background: #16108a; color: white; border: none; border-radius: 50%; text-decoration: none; transition: opacity 0.2s ease; opacity: 1; position: relative;">
                            <i class="las la-plus" style="font-size: 0.85rem; font-weight: 700; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); line-height: 1; margin: 0; padding: 0;"></i>
                        </a>
                    </div>
                    ' .
				$relationList .
				'
                </div>',
			"wrapper" => ["class" => "form-group col-md-12"],
			"tab" => "Relazioni",
		]);

		// Add modal HTML and JavaScript (only once)
		if (!self::$previewModalAdded) {
			self::$previewModalAdded = true;
			CRUD::addField([
				"name" => "relation_preview_modal",
				"type" => "custom_html",
				"value" => self::generateModalHtml(),
				"wrapper" => ["class" => ""],
				"tab" => "Relazioni",
			]);
		}
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
				// Check if relation method exists
				if (!method_exists($entry, $methodName)) {
					return "-";
				}

				try {
					// Force load relation if not already loaded (important for show page)
					if (!$entry->relationLoaded($methodName)) {
						$entry->load($methodName);
					}

					// Get relation data
					$relationData = $entry->$methodName;

					// Handle null relations
					if (is_null($relationData)) {
						return "-";
					}

					// Handle empty collections
					if (is_countable($relationData) && count($relationData) === 0) {
						return "-";
					}

					$links = [];
					foreach ($relationData as $elem) {
						if ($elem) {
							// Use getRouteKey() to respect custom route key names (e.g., numero_pratica, codice)
							$elemRouteKey = method_exists($elem, "getRouteKeyName") ? $elem->getRouteKey() : $elem->getKey();

							if (!$elemRouteKey) {
								continue;
							}

							// For lists and show pages, use getDisplayAttribute (not getRelationDisplayAttribute)
							$displayText = method_exists($elem, "getDisplayAttribute")
								? $elem->getDisplayAttribute()
								: $elem->name ?? ($elem->title ?? ($elem->ndg ?? ($elem->numero_pratica ?? $elemRouteKey)));

							$links[] =
								'<a data-relation-link="true" target="_blank" href="' .
								$backendUrl .
								"/" .
								$formattedRelatedModelName .
								"/" .
								$elemRouteKey .
								'/edit">' .
								$displayText .
								"</a>";
						}
					}

					// Return links or dash if no valid links were created
					return count($links) > 0 ? implode(", ", $links) : "-";
				} catch (\Exception $e) {
					// If relation access fails, return dash
					return "-";
				}
			});

		// Create column for pivot table percentage attribute for BelongsToMany relations
		if ($result instanceof BelongsToMany) {
			CRUD::column($methodName . "_pivot")
				->label($readableLabel . " Pivot")
				->type("custom_html")
				->entity($methodName)
				->model($relatedModelClass)
				->value(function ($entry) use ($methodName, $backendUrl) {
					// Force load relation if not already loaded (important for show page)
					if (!$entry->relationLoaded($methodName)) {
						$entry->load($methodName);
					}

					$pivotTableName = $entry->$methodName()->getTable();
					$pivotTableName = str_replace("_", "-", $pivotTableName);
					$links = [];
					foreach ($entry->$methodName as $elem) {
						$pivotId = $elem->pivot->id;

						// Use getRouteKey() to respect custom route key names for related model
						$elemRouteKey = method_exists($elem, "getRouteKeyName") ? $elem->getRouteKey() : $elem->getKey();

						// For lists and show pages, use getDisplayAttribute (not getRelationDisplayAttribute)
						$displayText = method_exists($elem, "getDisplayAttribute")
							? $elem->getDisplayAttribute()
							: $elem->name ?? ($elem->title ?? ($elem->ndg ?? ($elem->numero_pratica ?? $elemRouteKey)));

						$links[] =
							'<a data-relation-link="true" target="_blank" href="' .
							$backendUrl .
							"/" .
							$pivotTableName .
							"/" .
							$pivotId .
							'/edit">' .
							$displayText .
							"</a>";
					}
					return count($links) > 0 ? implode(", ", $links) : "-";
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
			->value(function ($entry) use ($column, $relationName, $camelCaseRelationName, $projectBaseUrl) {
				// Try with original name first
				$relation = $entry->$relationName ?? null;

				// If relation is null, try with camelCase version
				if ($relation === null && $relationName !== $camelCaseRelationName) {
					$relation = $entry->$camelCaseRelationName ?? null;
				}

				// If relation is still null, try to load it using the foreign key value
				if ($relation === null && isset($entry->$column) && $entry->$column !== null) {
					// Get the related model class from the relation
					try {
						$relationMethod = $entry->$relationName();
						if ($relationMethod instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
							$relatedModelClass = get_class($relationMethod->getRelated());
							$foreignKeyValue = $entry->$column;

							// Get the referenced column from the foreign key constraint
							$referencedColumn = self::getReferencedColumnForForeignKey($entry->getTable(), $column);

							if ($referencedColumn) {
								// Search by the referenced column (works for custom FKs like numero_pratica, codice, etc.)
								$relation = $relatedModelClass::where($referencedColumn, $foreignKeyValue)->first();
							} else {
								// Fallback: try primary key
								$relatedModelInstance = new $relatedModelClass();
								$searchKey = $relatedModelInstance->getKeyName();
								$relation = $relatedModelClass::where($searchKey, $foreignKeyValue)->first();
							}
						}
					} catch (\Exception $e) {
						// If loading fails, relation stays null
					}
				}

				if ($relation) {
					// Use getRouteKey() to respect custom route key names (e.g., numero_pratica, codice)
					$routeKey = method_exists($relation, "getRouteKeyName") ? $relation->getRouteKey() : $relation->getKey();

					// For lists and show pages, use getDisplayAttribute (not getRelationDisplayAttribute)
					$displayText = method_exists($relation, "getDisplayAttribute")
						? $relation->getDisplayAttribute()
						: $relation->name ??
							($relation->title ?? ($relation->ndg ?? ($relation->numero_pratica ?? $routeKey)));

					$modelName = strtolower(class_basename($relation));

					return '<a data-relation-link="true" target="_blank" href="' .
						$projectBaseUrl .
						"/" .
						Str::kebab($modelName) .
						"/" .
						$routeKey .
						'/edit">' .
						$displayText .
						"</a>";
				}

				// If relation is null but we have a foreign key value, show it as plain text
				if (isset($entry->$column) && $entry->$column !== null) {
					return $entry->$column;
				}

				return "-";
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
		return ucwords(preg_replace("/(?<!^)[A-Z]/", ' $0', $methodName));
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
		return strtolower(preg_replace("/(?<!^)[A-Z]/", '-$0', $methodName));
	}

	/**
	 * Get the referenced column name for a foreign key
	 *
	 * @param string $tableName Table name
	 * @param string $columnName Foreign key column name
	 * @return string|null Referenced column name or null if not found
	 */
	private static function getReferencedColumnForForeignKey(string $tableName, string $columnName): ?string
	{
		try {
			$result = DB::select(
				"
				SELECT REFERENCED_COLUMN_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = ?
				AND COLUMN_NAME = ?
				AND REFERENCED_TABLE_NAME IS NOT NULL
				LIMIT 1
			",
				[$tableName, $columnName]
			);

			if (!empty($result)) {
				return $result[0]->REFERENCED_COLUMN_NAME;
			}
		} catch (\Exception $e) {
			// Return null on error
		}

		return null;
	}

	/**
	 * Build a user-friendly display text for relation items
	 *
	 * @param \Illuminate\Database\Eloquent\Model $model
	 * @return string
	 */
	private static function formatRelationDisplayText($model): string
	{
		if (!$model) {
			return "-";
		}

		$candidates = [];

		if (method_exists($model, "getRelationDisplayAttribute")) {
			$candidates[] = $model->getRelationDisplayAttribute();
		}

		if (method_exists($model, "getDisplayAttribute")) {
			$candidates[] = $model->getDisplayAttribute();
		}

		$commonAttributes = ["name", "title", "label", "description", "code", "codice", "ndg", "ragione_sociale"];

		foreach ($commonAttributes as $attribute) {
			if (isset($model->$attribute)) {
				$candidates[] = $model->$attribute;
			}
		}

		// Add ID as last resort
		$candidates[] = $model->getKey() ?? null;

		foreach ($candidates as $text) {
			if (is_string($text)) {
				$trimmed = trim(strip_tags($text));
				if ($trimmed !== "") {
					return e($trimmed);
				}
			} elseif (!is_null($text)) {
				return e((string) $text);
			}
		}

		return $model->getKey() ? "ID #" . $model->getKey() : "-";
	}
}

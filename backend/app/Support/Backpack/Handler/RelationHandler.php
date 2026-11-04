<?php

namespace App\Support\Backpack\Handler;

use App\Models\Previsionale;
use App\Models\Storico;
use App\Models\User;
use App\Support\Backpack\BackpackHelper;
use App\Support\Backpack\FilterHelper;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RelationHandler
{
	// Track if we've added the separator for external relations
	private static $hasManySeparatorAdded = false;

	// Track if we've added the BelongsTo AJAX search modal assets
	private static $belongsToSearchModalAdded = false;

	/**
	 * Creates select fields for BelongsToMany and BelongsTo relations
	 *
	 * Handles both BelongsToMany and BelongsTo relations,
	 * creating appropriate field types with correct options.
	 *
	 * @param  string  $methodName  Method name representing the relation
	 * @param  mixed  $result  Result of relation query
	 * @param  string  $table  Table name
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
		// translates: keep Page on Dati (optional convenience); Banca belongs on Relazioni with other FK-style relations
		if ($table == "translates") {
			$tabName = $methodName === "banca" ? "Relazioni" : "Dati";
		}
		$finalTabName = $tabName ? $tabName : "Relazioni";
		$modalTabName = $table === "translates" ? "Relazioni" : $finalTabName;

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
						BackpackHelper::renderDisplayValue($displayText, true),
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

			// Add visible select_multiple field for pivot relations (user can add/remove relations)
			// Attribute is auto-guessed via model's identifiableAttribute() when omitted
			CRUD::field($methodName)
				->type("select_multiple")
				->entity($methodName)
				->wrapper(["class" => "form-group col-md-12"])
				->model($relatedModelClass)
				->pivot(true)
				->tab($finalTabName);
		}
		// Handle BelongsTo relation:
		// - if related table has <= 25 rows, render a normal select_from_array with all options;
		// - if it has > 25 rows, show current value + a button that opens an AJAX search modal.
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

			$totalCount = $relatedModelClass::count();

			if ($totalCount <= 25) {
				// Classic behavior: plain select with all options
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
				CRUD::field($foreignKey)
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
			} else {
				// Too many options: use hidden field + AJAX search button + current value display
				$currentLabel = null;
				if ($defaultValue) {
					$currentEntry = $relatedModelClass::where($ownerKeyName, $defaultValue)->first();
					if ($currentEntry) {
						if (method_exists($currentEntry, "getRelationDisplayAttribute")) {
							$currentLabel = $currentEntry->getRelationDisplayAttribute();
						} elseif (method_exists($currentEntry, "getDisplayAttribute")) {
							$currentLabel = $currentEntry->getDisplayAttribute();
						} else {
							$currentLabel =
								$currentEntry->name ??
								($currentEntry->title ??
									($currentEntry->user_id ?? ($currentEntry->numero_pratica ?? (string) $defaultValue)));
						}
					}
				}

				CRUD::field($foreignKey)
					->type("hidden")
					->value($defaultValue)
					->attributes([
						"data-belongs-to-ajax" => "1",
						"data-related-model" => $relatedModelClass,
						"data-owner-key" => $ownerKeyName,
					]);

				$searchPlaceholder = FilterHelper::buildBelongsToSearchPlaceholder(
					$relatedModelClass,
					self::generateFieldLabel($methodName)
				);

				CRUD::addField([
					"name" => $foreignKey . "_ajax_search",
					"type" => "custom_html",
					"tab" => $finalTabName,
					"value" =>
						'
                        <div class="mb-2" style="width:100%; max-width: 520px;">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <label class="form-label text-uppercase mb-0">' .
						e(self::generateFieldLabel($methodName)) .
						'</label>
                                <button type="button"
                                    class="btn btn-primary btn-sm js-bt-search"
                                    data-model="' .
						e($relatedModelClass) .
						'"
                                    data-foreign-key="' .
						e($foreignKey) .
						'"
                                    data-owner-key="' .
						e($ownerKeyName) .
						'"
                                    data-base-path="' .
						e($basePath) .
						'"
                                    data-route-name="' .
						e($routeName) .
						'"
                                    data-label="' .
						e(self::generateFieldLabel($methodName)) .
						'"
                                    data-search-placeholder="' .
						e($searchPlaceholder) .
						'">
                                    <i class="la la-search me-1"></i> Cerca e seleziona
                                </button>
                                <button type="button"
                                    class="btn btn-outline-danger btn-sm js-bt-clear"
                                    data-foreign-key="' .
						e($foreignKey) .
						'"
                                    style="' .
						($defaultValue ? "" : "display:none;") .
						'">
                                    <i class="la la-times me-1"></i> Rimuovi
                                </button>
                            </div>
                            <a id="bt-current-' .
						e($foreignKey) .
						'"
                               href="' .
						e($routeKeyValue ? $basePath . $routeName . "/" . $routeKeyValue . "/edit" : "#") .
						'"
                               class="crud-link-pill bt-current-value mt-1"
                               data-relation-field="' .
						e($foreignKey) .
						'"
                               style="position:relative;' .
						($defaultValue ? "" : "opacity:.55;pointer-events:none;") .
						'">
                                <span class="bt-current-label">' .
						e($currentLabel ?? "Nessun valore selezionato") .
						'</span>
                                <span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza dettaglio</span>
                            </a>
                        </div>',
					"wrapper" => ["class" => "form-group col-md-6 col-lg-5"],
				]);
			}
		}

		if (!self::$belongsToSearchModalAdded) {
			self::$belongsToSearchModalAdded = true;
			CRUD::addField([
				"name" => "belongs_to_search_modal",
				"type" => "custom_html",
				"value" => self::generateBelongsToSearchModalHtml(),
				"wrapper" => ["class" => ""],
				"tab" => $modalTabName,
			]);
		}
	}

	/**
	 * Generate HTML for relation preview button
	 *
	 * @param  string  $methodName  Relation method name
	 * @param  int  $entryId  Related entry ID
	 * @param  string  $displayText  Display text for button
	 * @param  string  $routeName  Route slug
	 * @param  string  $basePath  Base path for URLs
	 * @param  string  $relatedModelClass  Related model class
	 * @return string HTML for button
	 */
	/**
	 * Generate inline preview button for BelongsTo relations (internal)
	 *
	 * @param  string  $foreignKey  Foreign key field name
	 * @param  mixed  $entryId  Related entry ID
	 * @param  string  $routeName  Route slug
	 * @param  string  $basePath  Base path for URLs
	 * @param  string  $modelName  Model name
	 * @param  string  $relatedModelClass  Related model class
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
		$hasValue = $entryId !== null && $entryId !== "";
		$editUrl = $hasValue ? $basePath . $routeName . "/" . $entryId . "/edit" : "#";

		return '<a href="' .
			e($editUrl) .
			'"' .
			' class="crud-link-pill relation-preview-inline js-relation-preview-link"' .
			' data-relation-field="' .
			e($foreignKey) .
			'"' .
			' data-base-path="' .
			e($basePath) .
			'"' .
			' data-route-name="' .
			e($routeName) .
			'"' .
			' title="Visualizza dettaglio"' .
			($hasValue ? "" : ' disabled aria-disabled="true"') .
			' style="position:relative;">' .
			'<i class="la la-external-link" style="font-size:0.75rem;opacity:.8;"></i>' .
			'<span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza dettaglio</span>' .
			"</a>";
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

		return '<a href="' .
			e($editUrl) .
			'" class="crud-link-pill" style="display:inline-flex;align-items:center;gap:0.35rem;position:relative;">' .
			'<i class="la la-link" style="font-size:0.75rem;opacity:.8;"></i>' .
			"<span>" .
			$displayText .
			"</span>" .
			'<span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza dettaglio</span>' .
			"</a>";
	}

	/**
	 * Generate modal HTML and JavaScript for BelongsTo AJAX search (>25 options).
	 *
	 * @return string Modal HTML and scripts
	 */
	private static function generateBelongsToSearchModalHtml()
	{
		return '<style>
            .custom-modal { animation: fadeIn 0.2s ease; }
            .custom-modal-backdrop { animation: fadeIn 0.2s ease; }
            .custom-modal-dialog { animation: slideDown 0.3s ease; }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            .custom-modal-close:hover { color: #16108a !important; }
            .bt-current-value {
                display: inline-flex;
                align-items: center;
                min-height: 2.25rem;
                padding: 0.25rem 0.6rem;
                border-radius: 0.5rem;
                background: #f3f4f6;
                border: 1px solid #e5e7eb;
                font-size: 0.875rem;
                color: #111827;
                position: relative;
                overflow: visible;
            }
            .bt-current-value .bt-current-label {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            #btSearchResults .bt-result-item {
                background-color: #ffffff;
                border-color: #e5e7eb;
                color: #111827;
                font-size: 0.875rem;
            }
            #btSearchResults .bt-result-item:hover {
                background-color: #e5f0ff;
                border-color: #c7d2fe;
                color: #111827;
            }
        </style>
        <div id="btSearchModal" class="custom-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1056; overflow:auto;">
            <div class="custom-modal-backdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1055;"></div>
            <div class="custom-modal-dialog" style="position: relative; z-index: 1056; margin: 1rem auto; max-width: 800px; width: calc(100% - 2rem); max-height: 90vh; display: flex; align-items: center; min-height: calc(100% - 2rem);">
                <div class="custom-modal-content" style="background: #ffffff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
                    <div class="custom-modal-header" style="padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                        <h5 class="custom-modal-title" id="btSearchModalLabel" style="font-size: 1rem; font-weight: 600; margin: 0;">Seleziona relazione</h5>
                        <button type="button" class="custom-modal-close bt-search-close" aria-label="Close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; color: #6b7280;">&times;</button>
                    </div>
                    <div class="custom-modal-body" style="padding: 1rem; flex: 1; display:flex; flex-direction:column; gap:.75rem;">
                        <input type="text" id="btSearchInput" class="form-control" placeholder="Cerca..." autocomplete="off">
                        <div id="btSearchResults" style="max-height: 320px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: .25rem; background:#f9fafb;"></div>
                    </div>
                    <div class="custom-modal-footer" style="padding: 0.75rem 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.5rem; flex-shrink: 0;">
                        <button type="button" class="btn btn-secondary btn-sm bt-search-close">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var btModal = document.getElementById("btSearchModal");
            if (!btModal) {
                return;
            }

            var btBackdrop = btModal.querySelector(".custom-modal-backdrop");
            var btCloseButtons = btModal.querySelectorAll(".bt-search-close");
            var btInput = document.getElementById("btSearchInput");
            var btResults = document.getElementById("btSearchResults");
            var btCurrentField = null;
            var btFieldName = null;
            var btBasePath = null;
            var btRouteName = null;

            function openBtModal(label, fieldName, placeholder) {
                btModal.style.display = "block";
                document.body.style.overflow = "hidden";
                document.getElementById("btSearchModalLabel").textContent = "Seleziona " + (label || fieldName);
                btInput.placeholder = placeholder || "Cerca...";
                btInput.value = "";
                btResults.innerHTML = "";
                btInput.focus();
            }

            function closeBtModal() {
                btModal.style.display = "none";
                document.body.style.overflow = "";
                btCurrentField = null;
                btFieldName = null;
                btBasePath = null;
                btRouteName = null;
            }

            btBackdrop.addEventListener("click", closeBtModal);
            btCloseButtons.forEach(function(btn) { btn.addEventListener("click", closeBtModal); });

            btInput.addEventListener("input", function() {
                var q = this.value.trim();
                if (!btCurrentField || q.length < 2) {
                    btResults.innerHTML = "";
                    return;
                }
                var model = btCurrentField.getAttribute("data-related-model");
                var url = (window.backpack && window.backpack.baseUrl ? window.backpack.baseUrl : "/admin") + "/ajax/belongs-to-search";
                var params = new URLSearchParams({ model: model, q: q }).toString();

                btResults.innerHTML = \'<div class="text-muted px-2 py-1" style="font-size:.8rem;">Ricerca in corso...</div>\';

                fetch(url + "?" + params)
                    .then(function(r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function(data) {
                        btResults.innerHTML = "";
                        if (!data.results || !data.results.length) {
                            btResults.innerHTML = \'<div class="text-muted px-2 py-1" style="font-size:.8rem;">Nessun risultato</div>\';
                            return;
                        }
                        data.results.forEach(function(item) {
                            var btn = document.createElement("button");
                            btn.type = "button";
                            btn.className = "btn btn-outline-secondary btn-sm w-100 text-start mb-1 bt-result-item";
                            btn.textContent = item.text;
                            btn.addEventListener("click", function() {
                                btCurrentField.value = item.id;
                                var field = btFieldName;
                                var valueBox = field ? document.querySelector(\'.bt-current-value[data-relation-field="\' + field + \'"]\') : null;
                                if (valueBox) {
                                    var labelEl = valueBox.querySelector(".bt-current-label");
                                    if (labelEl) labelEl.textContent = item.text;
                                }
                                var clearBtn = document.querySelector(\'.js-bt-clear[data-foreign-key="\' + field + \'"]\');
                                if (clearBtn) {
                                    clearBtn.style.display = "";
                                }
                                if (field) {
                                    var currentLink = document.getElementById("bt-current-" + field);
                                    if (currentLink) {
                                        var basePath = btBasePath || "";
                                        var routeName = btRouteName || "";
                                        currentLink.href = basePath + routeName + "/" + item.id + "/edit";
                                        currentLink.style.pointerEvents = "";
                                        currentLink.style.opacity = "";
                                    }
                                }
                                btCurrentField.dispatchEvent(new Event("change", { bubbles: true }));
                                closeBtModal();
                            });
                            btResults.appendChild(btn);
                        });
                    })
                    .catch(function() {
                        btResults.innerHTML = \'<div class="text-danger px-2 py-1" style="font-size:.8rem;">Errore nella ricerca</div>\';
                    });
            });

            document.querySelectorAll(".js-bt-search").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var field = this.getAttribute("data-foreign-key");
                    var label = this.getAttribute("data-label");
                    btFieldName = field;
                    btBasePath = this.getAttribute("data-base-path") || "";
                    btRouteName = this.getAttribute("data-route-name") || "";
                    btCurrentField = document.querySelector(\'input[name="\' + field + \'"][data-belongs-to-ajax="1"]\');
                    if (!btCurrentField) {
                        return;
                    }
                    openBtModal(
                        label,
                        field,
                        this.getAttribute("data-search-placeholder") || "Cerca..."
                    );
                });
            });

            document.querySelectorAll(".js-bt-clear").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var field = this.getAttribute("data-foreign-key");
                    var targetField = document.querySelector(\'input[name="\' + field + \'"][data-belongs-to-ajax="1"]\');
                    var valueBox = document.querySelector(\'.bt-current-value[data-relation-field="\' + field + \'"]\');
                    if (targetField) {
                        targetField.value = "";
                        targetField.dispatchEvent(new Event("change", { bubbles: true }));
                    }
                    if (valueBox) {
                        var labelEl = valueBox.querySelector(".bt-current-label");
                        if (labelEl) labelEl.textContent = "Nessun valore selezionato";
                    }
                    var currentLink = document.getElementById("bt-current-" + field);
                    if (currentLink) {
                        currentLink.href = "#";
                        currentLink.style.pointerEvents = "none";
                        currentLink.style.opacity = ".55";
                    }
                    this.style.display = "none";
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
	 * @param  string  $methodName  Method name representing the relation
	 * @param  mixed  $result  Result of relation query
	 * @param  string  $table  Table name
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

		// Get the owner key value (supports custom primary keys like numero_pratica)
		$currentEntry = CRUD::getCurrentEntry();
		if (!$currentEntry) {
			return;
		}

		// For HasMany, getLocalKeyName() returns the owner key column name
		// Use reflection to access protected method if needed, or use the relation's local key
		$ownerKeyName = $result->getLocalKeyName();
		$ownerKeyValue = $currentEntry->$ownerKeyName ?? $currentEntry->getKey();

		// Get count first to check if we should show counter or individual items
		$relatedCount = $relatedModelClass::where($foreignKey, $ownerKeyValue)->count();

		// Some relations always use a filtered-list link (never individual badges — layout/performance)
		$alwaysLinkOnly = self::shouldAlwaysShowRelationLinkOnly($relatedModelClass);

		// Determine base path for URLs
		$isEditMode = true; // We're always in edit mode here since we check for $id
		$basePath = "../../../admin/";

		// Build compact related items list using buttons with preview
		$relationList = "";

		if ($relatedCount === 0) {
			$relationList .=
				'<span style="color: #9ca3af; font-size: 0.75rem; font-style: italic; margin-top: 0.5rem; display: block;">' .
				trans("backpack::crud.infoEmpty") .
				"</span>";
		} elseif ($alwaysLinkOnly || $relatedCount > self::MAX_RELATION_LINKS_EDIT) {
			// Too many records - show a link to filtered list instead of individual badges
			$filterUrl = self::buildRelatedListFilterUrl(
				$relatedModelUrlSegment,
				$methodName,
				$foreignKey,
				$ownerKeyValue,
				null,
				$currentEntry->$methodName()
			);
			$relationList .=
				'
                <div style="margin-top: 0.5rem;">
                    <a href="' .
				$filterUrl .
				'" class="btn btn-sm btn-outline-primary" style="font-size: 0.8rem;">
                        <i class="la la-list me-1"></i>' .
				trans("backpack::crud.view_all_records", ["count" => $relatedCount]) .
				'</a>
                </div>';
		} else {
			// Load and display individual badges (use relation query so default ordering applies, e.g. storici by date)
			$relatedEntries = $currentEntry->$methodName()->get();

			// Sort items by date columns if available
			if ($relatedEntries->isNotEmpty()) {
				$firstItem = $relatedEntries->first();
				$attrs = $firstItem->getAttributes();

				if (array_key_exists("data_contabile", $attrs)) {
					$relatedEntries = $relatedEntries->sortBy("data_contabile")->values();
				} elseif (array_key_exists("data_richiesta", $attrs)) {
					$relatedEntries = $relatedEntries->sortBy("data_richiesta")->values();
				} elseif (array_key_exists("data_delibera", $attrs)) {
					$relatedEntries = $relatedEntries->sortBy("data_delibera")->values();
				} elseif (array_key_exists("anno", $attrs) && array_key_exists("mese", $attrs)) {
					$relatedEntries = $relatedEntries
						->sortBy(function ($item) {
							return sprintf("%04d-%02d", $item->anno, $item->mese);
						})
						->values();
				}
			}

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
				$entryLabel = BackpackHelper::renderDisplayValue(
					is_string($entryLabel) ? $entryLabel : (string) $entryLabel,
					true
				);

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
				$relatedCount .
				')</span></h6>
                        <a href="' .
				url("admin/{$relatedModelUrlSegment}/create?{$foreignKey}=" . $ownerKeyValue) .
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

		// Preview modal removed.
	}

	/**
	 * Creates a field for HasOne relations (single related entry)
	 *
	 * Similar to HasMany but displays a single related entry with preview button
	 *
	 * @param  string  $methodName  Method name representing the relation
	 * @param  HasOne  $result  Result of relation query
	 * @param  string  $table  Table name
	 */
	public static function createHasOneRelationField($methodName, $result, $table)
	{
		// Ensure relation is of type HasOne
		if (!($result instanceof HasOne)) {
			return;
		}

		// Get related model and its class
		$relatedModel = $result->getRelated();
		$relatedModelClass = get_class($relatedModel);

		// Get current entry ID (if in edit mode)
		$id = CRUD::getCurrentEntryId();

		// If no ID exists, we're in creation mode, so don't show the relation
		if (!$id) {
			return;
		}

		$relatedModelBaseName = class_basename($relatedModelClass);

		// Convert to kebab-case (for URL consistency)
		$relatedModelUrlSegment = Str::kebab($relatedModelBaseName);

		// Get foreign key
		$foreignKey = $result->getForeignKeyName();

		// Get the owner key value (supports custom primary keys)
		$currentEntry = CRUD::getCurrentEntry();
		if (!$currentEntry) {
			return;
		}

		// For HasOne, getLocalKeyName() returns the owner key column name
		$ownerKeyName = $result->getLocalKeyName();
		$ownerKeyValue = $currentEntry->$ownerKeyName ?? $currentEntry->getKey();

		// Get the single related entry using the correct owner key value
		$relatedEntry = $relatedModelClass::where($foreignKey, $ownerKeyValue)->first();

		// Determine base path for URLs
		$basePath = "../../../admin/";

		// Build relation display
		$relationContent = "";
		if ($relatedEntry) {
			// Use getRouteKey() to respect custom route key names
			$entryRouteKey = method_exists($relatedEntry, "getRouteKeyName")
				? $relatedEntry->getRouteKey()
				: $relatedEntry->getKey();

			$entryId = $entryRouteKey;

			// Check if model has custom relation display method
			$entryLabel = method_exists($relatedEntry, "getRelationDisplayAttribute")
				? $relatedEntry->getRelationDisplayAttribute()
				: $relatedEntry->name ?? ($relatedEntry->title ?? ($relatedEntry->numero_pratica ?? "ID: $entryId"));
			$entryLabel = BackpackHelper::renderDisplayValue(
				is_string($entryLabel) ? $entryLabel : (string) $entryLabel,
				true
			);

			$relationContent .=
				'<div class="relation-buttons-container" style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem;">';
			$relationContent .= self::generateRelationButtonHtml(
				$methodName,
				$entryId,
				$entryLabel,
				$relatedModelUrlSegment,
				$basePath,
				$relatedModelClass
			);
			$relationContent .= "</div>";
		} else {
			$relationContent .=
				'<span style="color: #9ca3af; font-size: 0.75rem; font-style: italic; margin-top: 0.5rem; display: block;">' .
				trans("backpack::crud.infoEmpty") .
				"</span>";
		}

		// Add separator before first HasOne relation (share with HasMany)
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

		// Create custom HTML field in "Relazioni" tab
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
				' <span style="color: #9ca3af; font-weight: 400; font-size: 0.7rem;">(1:1)</span></h6>
                        ' .
				($relatedEntry
					? ""
					: '<a href="' .
						url("admin/{$relatedModelUrlSegment}/create?{$foreignKey}=" . $ownerKeyValue) .
						'" class="btn-add-relation" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; padding: 0; background: #16108a; color: white; border: none; border-radius: 50%; text-decoration: none; transition: opacity 0.2s ease; opacity: 1; position: relative;">
                            <i class="las la-plus" style="font-size: 0.85rem; font-weight: 700; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); line-height: 1; margin: 0; padding: 0;"></i>
                        </a>') .
				'
                    </div>
                    ' .
				$relationContent .
				'
                </div>',
			"wrapper" => ["class" => "form-group col-md-12"],
			"tab" => "Relazioni",
		]);

		// Preview modal removed.
	}

	/**
	 * Maximum number of related items to display as links in list view before showing a counter
	 */
	private const MAX_RELATION_LINKS_LIST = 25;

	/**
	 * Maximum number of related items to display as badges in edit view before showing a counter
	 */
	private const MAX_RELATION_LINKS_EDIT = 50;

	/**
	 * Related models that must never render individual badges in list/edit (link to filtered list only).
	 *
	 * @var list<class-string<Model>>
	 */
	private const RELATION_MODELS_ALWAYS_LINK_ONLY = [User::class, Storico::class, Previsionale::class];

	/**
	 * Whether a HasMany relation should always show a single filtered-list link (no badge grid).
	 *
	 * @param  class-string<Model>  $relatedModelClass
	 */
	private static function shouldAlwaysShowRelationLinkOnly(string $relatedModelClass): bool
	{
		return in_array($relatedModelClass, self::RELATION_MODELS_ALWAYS_LINK_ONLY, true);
	}

	/**
	 * Creates columns for displaying BelongsToMany, HasMany, or HasOne relations in CRUD List operation
	 *
	 * Generates custom HTML columns showing related entries with links
	 * For performance, shows a counter badge when there are more than MAX_RELATION_LINKS items
	 *
	 * @param  string  $methodName  Method name representing the relation
	 * @param  mixed  $result  Result of relation query
	 * @param  string  $backendUrl  Backend CRUD URL
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

		// Some HasMany relations always show count + filtered-list link (never badge grids in list)
		$alwaysCountAndLinkOnly = $result instanceof HasMany && self::shouldAlwaysShowRelationLinkOnly($relatedModelClass);

		// When entity is set, Backpack overwrites type with inferFieldTypeFromRelationType(HasMany) and can load the relation.
		$setEntity = $alwaysCountAndLinkOnly ? false : $methodName;

		// Counted by the list query, not once per row.
		CRUD::addClause("withCount", $methodName);

		// Create column for related model name with links
		CRUD::column($methodName)
			->type("custom_html")
			->entity($setEntity)
			->label($readableLabel)
			->model($relatedModelClass)
			->value(function ($entry) use (
				$formattedRelatedModelName,
				$methodName,
				$backendUrl,
				$readableLabel,
				$alwaysCountAndLinkOnly
			) {
				// Check if relation method exists
				if (!method_exists($entry, $methodName)) {
					return "-";
				}

				try {
					// Get count first to check if we should show counter or links
					$count = $entry->getAttribute(Str::snake($methodName) . "_count") ?? $entry->$methodName()->count();

					// Handle empty relations
					if ($count === 0) {
						return "-";
					}

					// If count exceeds threshold (or always for User relation), show count + link to filtered list
					if ($alwaysCountAndLinkOnly || $count > self::MAX_RELATION_LINKS_LIST) {
						// Get the foreign key to build filter URL
						$relation = $entry->$methodName();
						$foreignKey = null;
						$ownerKeyValue = null;

						if ($relation instanceof HasMany) {
							$foreignKey = $relation->getForeignKeyName();
							$ownerKeyName = $relation->getLocalKeyName();
							$ownerKeyValue = $entry->$ownerKeyName ?? $entry->getKey();
						} elseif ($relation instanceof BelongsToMany) {
							// For BelongsToMany, we can't easily filter, just show count
							$foreignKey = null;
						}

						// Build URL to filtered list if possible
						$filterUrl = self::buildRelatedListFilterUrl(
							$formattedRelatedModelName,
							$methodName,
							$foreignKey,
							$ownerKeyValue,
							$backendUrl,
							$relation
						);

						return '<a href="' .
							$filterUrl .
							'" class="crud-link-pill" style="display: inline-flex; align-items: center; gap: 0.25rem; position: relative;" title="' .
							trans("backpack::crud.view_all_related", ["count" => $count, "relation" => $readableLabel]) .
							'">' .
							'<i class="la la-external-link-alt" style="font-size: 0.75rem;"></i>' .
							"<span>" .
							$count .
							"</span>" .
							'<span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza in lista</span>' .
							"</a>";
					}

					// For small counts, load and display links
					if (!$entry->relationLoaded($methodName)) {
						$entry->load($methodName);
					}

					$relationData = $entry->$methodName;

					// Handle null relations
					if (is_null($relationData)) {
						return "-";
					}

					// Handle single model (HasOne) vs collection (HasMany, BelongsToMany)
					$items = $relationData instanceof Model ? collect([$relationData]) : $relationData;

					// Sort items by date columns if available
					if ($items instanceof Collection && $items->isNotEmpty()) {
						$firstItem = $items->first();
						$attrs = $firstItem->getAttributes();

						if (array_key_exists("data_contabile", $attrs)) {
							$items = $items->sortBy("data_contabile")->values();
						} elseif (array_key_exists("data_richiesta", $attrs)) {
							$items = $items->sortBy("data_richiesta")->values();
						} elseif (array_key_exists("data_delibera", $attrs)) {
							$items = $items->sortBy("data_delibera")->values();
						} elseif (array_key_exists("anno", $attrs) && array_key_exists("mese", $attrs)) {
							$items = $items
								->sortBy(function ($item) {
									return sprintf("%04d-%02d", $item->anno, $item->mese);
								})
								->values();
						}
					}

					$links = [];
					foreach ($items as $elem) {
						if ($elem) {
							// Use getRouteKey() to respect custom route key names (e.g., numero_pratica, codice)
							$elemRouteKey = method_exists($elem, "getRouteKeyName") ? $elem->getRouteKey() : $elem->getKey();

							if (!$elemRouteKey) {
								continue;
							}

							// For lists and show pages, use getDisplayAttribute (not getRelationDisplayAttribute)
							$displayText = method_exists($elem, "getDisplayAttribute")
								? $elem->getDisplayAttribute()
								: $elem->name ??
									($elem->title ?? ($elem->user_id ?? ($elem->numero_pratica ?? $elemRouteKey)));

							$links[] =
								'<a data-relation-link="true" class="crud-link-pill" style="position:relative;" href="' .
								$backendUrl .
								"/" .
								$formattedRelatedModelName .
								"/" .
								$elemRouteKey .
								'/edit">' .
								BackpackHelper::renderDisplayValue($displayText, true) .
								'<span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza dettaglio</span>' .
								"</a>";
						}
					}

					// Return links (no commas, pills already separated by spacing)
					return count($links) > 0
						? '<span class="crud-pill-stack hb-relation-list-cell">' . implode(" ", $links) . "</span>"
						: "-";
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
							: $elem->name ?? ($elem->title ?? ($elem->user_id ?? ($elem->numero_pratica ?? $elemRouteKey)));

						$links[] =
							'<a data-relation-link="true" class="crud-link-pill" style="position:relative;" href="' .
							$backendUrl .
							"/" .
							$pivotTableName .
							"/" .
							$pivotId .
							'/edit">' .
							BackpackHelper::renderDisplayValue($displayText, true) .
							'<span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza dettaglio</span>' .
							"</a>";
					}

					return count($links) > 0
						? '<span class="crud-pill-stack hb-relation-list-cell">' . implode(" ", $links) . "</span>"
						: "-";
				});
		}
	}

	/**
	 * Configures columns for foreign key relations in list view
	 *
	 * Creates links to related entries with appropriate display text
	 *
	 * @param  string  $column  Column name
	 * @param  string  $relationName  Relation name
	 * @param  string  $projectBaseUrl  Project base URL
	 */
	public static function configureRelationColumnView($column, $relationName, $projectBaseUrl)
	{
		// Deal with camelCase relation names (e.g. backpackRole -> backpack_role)
		$camelCaseRelationName = Str::camel($relationName);

		// Eager-load it: the value closure below reads it once per row.
		$model = CRUD::getModel();
		foreach ([$relationName, $camelCaseRelationName] as $name) {
			if (method_exists($model, $name) && $model->$name() instanceof Relation) {
				CRUD::with($name);
				break;
			}
		}

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
						if ($relationMethod instanceof Relation) {
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
							($relation->title ?? ($relation->user_id ?? ($relation->numero_pratica ?? $routeKey)));

					$modelName = strtolower(class_basename($relation));

					return '<a data-relation-link="true" class="crud-link-pill" style="position:relative;" href="' .
						$projectBaseUrl .
						"/" .
						Str::kebab($modelName) .
						"/" .
						$routeKey .
						'/edit">' .
						BackpackHelper::renderDisplayValue($displayText, true) .
						'<span class="relation-preview-tooltip relation-preview-tooltip-badge">Visualizza dettaglio</span>' .
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
	 * @param  string  $methodName  Method name representing the relation
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
	 * @param  string  $methodName  Method name representing the relation
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
	 * @param  string  $tableName  Table name
	 * @param  string  $columnName  Foreign key column name
	 * @return string|null Referenced column name or null if not found
	 */
	private static function getReferencedColumnForForeignKey(string $tableName, string $columnName): ?string
	{
		try {
			$result = DB::select(
				'
				SELECT REFERENCED_COLUMN_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = ?
				AND COLUMN_NAME = ?
				AND REFERENCED_TABLE_NAME IS NOT NULL
				LIMIT 1
			',
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
	 * Build related-list URL with relation-specific filters.
	 */
	private static function buildRelatedListFilterUrl(
		string $relatedModelUrlSegment,
		string $relationMethodName,
		?string $foreignKey,
		$ownerKeyValue,
		?string $baseUrl = null,
		?Relation $relation = null
	): string {
		$base = $baseUrl ? rtrim($baseUrl, "/") : url("admin");
		$url = $base . "/" . $relatedModelUrlSegment;

		$query = [];
		if ($foreignKey && $ownerKeyValue !== null && $ownerKeyValue !== "") {
			$query[$foreignKey] = trim((string) $ownerKeyValue);
		}

		// Auto-append static relation constraints defined as where('field', '=', 'value').
		$query = array_merge($query, self::extractStaticWhereFiltersFromRelation($relation, $foreignKey, $ownerKeyValue));

		if (empty($query)) {
			return $url;
		}

		return $url . "?" . http_build_query($query);
	}

	/**
	 * Extract static where filters from relation query.
	 *
	 * Supports Basic "=" constraints with scalar values, excluding the linking foreign key.
	 */
	private static function extractStaticWhereFiltersFromRelation(
		?Relation $relation,
		?string $foreignKey,
		$ownerKeyValue
	): array {
		if (!$relation) {
			return [];
		}

		$filters = [];
		$wheres = $relation->getQuery()->getQuery()->wheres ?? [];
		$ownerKeyValue = $ownerKeyValue !== null ? trim((string) $ownerKeyValue) : null;

		foreach ($wheres as $where) {
			if (($where["type"] ?? null) !== "Basic") {
				continue;
			}

			if (($where["operator"] ?? null) !== "=") {
				continue;
			}

			$column = $where["column"] ?? null;
			if (!is_string($column) || $column === "") {
				continue;
			}

			$field = str_contains($column, ".") ? substr($column, strrpos($column, ".") + 1) : $column;
			if ($field === "") {
				continue;
			}

			$value = $where["value"] ?? null;
			if ($value === null || is_array($value) || is_object($value)) {
				continue;
			}

			// Skip relation-linking FK condition (already represented by $foreignKey query parameter).
			if (
				$foreignKey &&
				$field === $foreignKey &&
				$ownerKeyValue !== null &&
				trim((string) $value) === $ownerKeyValue
			) {
				continue;
			}

			$filters[$field] = (string) $value;
		}

		return $filters;
	}
}

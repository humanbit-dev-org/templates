<?php

namespace App\Http\Controllers\Admin;

use App\Support\Backpack\BackpackHelper;
use App\Http\Concerns\ChecksBackpackPermissions;
use App\Models\Page;
use App\Models\Translate;
use App\Services\Translate\TranslateVariableService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class TranslateCrudController
 *
 * @property-read CrudPanel $crud
 */
class TranslateCrudController extends CrudController
{
	use ChecksBackpackPermissions;
	use ListOperation;
	use ShowOperation;

	/**
	 * Configure the CrudPanel object. Apply settings to all operations.
	 *
	 * @return void
	 */
	public function setup()
	{
		CRUD::setModel(Translate::class);
		CRUD::setRoute(config("backpack.base.route_prefix") . "/translate");
		CRUD::setEntityNameStrings("translate", "translates");

		$this->setupPermissionChecks();
		$this->crud->denyAccess(["create", "update", "delete"]);
	}

	/**
	 * Define what happens when the List operation is loaded.
	 *
	 * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
	 *
	 * @return void
	 */
	protected function setupListOperation()
	{
		$this->crud->setListView("admin.translates.archive");
		$this->crud->setListContentClass("col-md-12");
		CRUD::setOperationSetting("archiveData", $this->buildArchiveData());
	}

<<<<<<< HEAD
	/**
	 * Build archive data for custom list view.
	 *
	 * @return array<string, mixed>
	 */
	private function buildArchiveData(): array
	{
		$query = Translate::query()->with(["page:id,name", "banca:banca_abi,nome"]);
=======
    /**
     * Build archive data for custom list view.
     *
     * @return array<string, mixed>
     */
    private function buildArchiveData(): array
    {
        $query = Translate::query()->with(['page:id,name']);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$code = trim((string) request()->query("code", ""));
		if ($code !== "") {
			$query->where("code", "like", "%{$code}%");
		}

<<<<<<< HEAD
		$bancaAbi = trim((string) request()->query("banca_abi", ""));
		if ($bancaAbi !== "") {
			if ($bancaAbi === "default") {
				$query->whereNull("banca_abi");
			} else {
				$query->where("banca_abi", "like", "%{$bancaAbi}%");
			}
		}

		$pageId = request()->query("page_id");
		if (is_numeric($pageId)) {
			$query->where("page_id", (int) $pageId);
		}

		/** @var LengthAwarePaginator $translations */
		$translations = $query
			->orderByRaw("CASE WHEN banca_abi IS NULL THEN 0 ELSE 1 END")
			->orderBy("code")
			->paginate(30)
			->withQueryString();

		$pageOptions = Page::query()->orderBy("name")->pluck("name", "id")->toArray();
		$tenantAbis = Translate::query()
			->select("banca_abi")
			->whereNotNull("banca_abi")
			->distinct()
			->orderBy("banca_abi")
			->pluck("banca_abi")
			->map(static fn($value): string => (string) $value)
			->values()
			->all();
		$tenantNamesByAbi = Banca::query()
			->whereIn("banca_abi", $tenantAbis)
			->pluck("nome", "banca_abi")
			->map(static fn($value): string => (string) $value)
			->all();

		$tenantOptions = collect($tenantAbis)
			->map(function (string $abi) use ($tenantNamesByAbi): array {
				$name = $tenantNamesByAbi[$abi] ?? "";

				return [
					"abi" => $abi,
					"label" => $name !== "" ? "{$abi} - {$name}" : $abi,
					"name" => $name,
				];
			})
			->values()
			->all();

		$tenantTabs = array_merge(
			[
				[
					"abi" => "",
					"label" => "tutti",
				],
			],
			[
				[
					"abi" => "default",
					"label" => "default",
				],
			],
			collect($tenantOptions)
				->map(
					static fn(array $item): array => [
						"abi" => $item["abi"],
						"label" => $item["label"],
					]
				)
				->all()
		);

		return [
			"translations" => $translations,
			"filters" => [
				"code" => $code,
				"banca_abi" => $bancaAbi,
				"page_id" => is_numeric($pageId) ? (int) $pageId : "",
			],
			"pageOptions" => $pageOptions,
			"tenantOptions" => $tenantOptions,
			"tenantTabs" => $tenantTabs,
			"activeTenant" => $bancaAbi,
			"placeholderSamples" => $this->buildPlaceholderSamples($bancaAbi),
			"totals" => [
				"records" => $translations->total(),
			],
		];
	}

	/**
	 * Build readable sample values for dynamic placeholders.
	 *
	 * @return array<string, string>
	 */
	private function buildPlaceholderSamples(?string $activeTenantAbi = null): array
	{
		$samples = [];
		$modelCache = [];

		foreach (TranslateVariableService::getRegistry() as $key => $definition) {
			$samples[(string) $key] = $this->sampleValueForPlaceholder(
				(string) $key,
				$definition,
				$activeTenantAbi,
				$modelCache
			);
		}
=======
        $pageId = request()->query('page_id');
        if (is_numeric($pageId)) {
            $query->where('page_id', (int) $pageId);
        }

        /** @var LengthAwarePaginator $translations */
        $translations = $query
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return [
            'translations' => $translations,
            'filters' => [
                'code' => $code,
                'page_id' => is_numeric($pageId) ? (int) $pageId : '',
            ],
            'pageOptions' => Page::query()->orderBy('name')->pluck('name', 'id')->toArray(),
            'placeholderSamples' => $this->buildPlaceholderSamples(),
            'totals' => [
                'records' => $translations->total(),
            ],
        ];
    }

    /**
     * Build readable sample values for dynamic placeholders.
     *
     * @return array<string, string>
     */
    private function buildPlaceholderSamples(): array
    {
        $samples = [];
        $modelCache = [];

        foreach (TranslateVariableService::getRegistry() as $key => $definition) {
            $samples[(string) $key] = $this->sampleValueForPlaceholder((string) $key, $definition, $modelCache);
        }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		return $samples;
	}

<<<<<<< HEAD
	/**
	 * Resolve a sample value for a placeholder.
	 *
	 * @param  array<string, mixed>  $definition
	 * @param  array<string, Model|null>  $modelCache
	 */
	private function sampleValueForPlaceholder(
		string $key,
		array $definition,
		?string $activeTenantAbi,
		array &$modelCache
	): string {
		$modelKey = (string) ($definition["model"] ?? "");
		$field = (string) ($definition["field"] ?? "");
		$modelClass = config("translate_vars.models.{$modelKey}");

		if (
			$modelKey !== "" &&
			$field !== "" &&
			is_string($modelClass) &&
			class_exists($modelClass) &&
			is_subclass_of($modelClass, Model::class)
		) {
			$cacheKey = $modelClass . "|" . ($activeTenantAbi ?? "");
			if (!array_key_exists($cacheKey, $modelCache)) {
				$modelCache[$cacheKey] = $this->resolveModelSampleRecord($modelClass, $activeTenantAbi);
			}

			$record = $modelCache[$cacheKey];
			if ($record && isset($record->{$field}) && $record->{$field} !== null && (string) $record->{$field} !== "") {
				return (string) $record->{$field};
			}
		}

		$fixedSamples = [
			"prodotto.max_mesi_tiraggio" => "18",
			"prodotto.min_mesi_tiraggio" => "3",
			"delibera.max_atp_consigliato" => "25.000,00",
			"tiraggio.importo_richiesto" => "10.000,00",
			"tiraggio.percentuale" => "15",
			"tiraggio.trattenuta_totale" => "4.250,00",
			"frontend.tiraggio_settimane_rimanenti" => "24",
			"frontend.tiraggio_settimane_label" => "settimane",
			"user.ragione_sociale" => "ACME S.r.l.",
			"banca.nome" => "Banca Demo",
		];

		if (isset($fixedSamples[$key])) {
			return $fixedSamples[$key];
		}

		if (Str::contains($key, ["mesi", "month"])) {
			return "12";
		}
		if (Str::contains($key, ["importo", "totale", "max_", "min_", "prezzo", "amount"])) {
			return "1.000,00";
		}
		if (Str::contains($key, ["percentuale", "perc", "ratio"])) {
			return "15";
		}
		if (Str::contains($key, ["nome", "name"])) {
			return "Mario Rossi";
		}
		if (Str::contains($key, ["data", "date"])) {
			return now()->format("d/m/Y");
		}
=======
    /**
     * Resolve a sample value for a placeholder: the value of the first record
     * of the placeholder's model when available, otherwise a plausible value
     * guessed from the key.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, Model|null>  $modelCache
     */
    private function sampleValueForPlaceholder(string $key, array $definition, array &$modelCache): string
    {
        $modelKey = (string) ($definition['model'] ?? '');
        $field = (string) ($definition['field'] ?? '');
        $modelClass = config("translate_vars.models.{$modelKey}");

        if (
            $modelKey !== '' &&
            $field !== '' &&
            is_string($modelClass) &&
            class_exists($modelClass) &&
            is_subclass_of($modelClass, Model::class)
        ) {
            if (! array_key_exists($modelClass, $modelCache)) {
                $modelCache[$modelClass] = (new $modelClass)->newQuery()->first();
            }

            $record = $modelCache[$modelClass];
            if ($record && isset($record->{$field}) && $record->{$field} !== null && (string) $record->{$field} !== '') {
                return (string) $record->{$field};
            }
        }

        if (Str::contains($key, ['mesi', 'month'])) {
            return '12';
        }
        if (Str::contains($key, ['importo', 'totale', 'max_', 'min_', 'prezzo', 'amount'])) {
            return '1.000,00';
        }
        if (Str::contains($key, ['percentuale', 'perc', 'ratio'])) {
            return '15';
        }
        if (Str::contains($key, ['nome', 'name'])) {
            return 'Mario Rossi';
        }
        if (Str::contains($key, ['data', 'date'])) {
            return now()->format('d/m/Y');
        }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		return '{$' . $key . "}";
	}

<<<<<<< HEAD
	/**
	 * Resolve one representative record for a model, tenant-aware if possible.
	 */
	private function resolveModelSampleRecord(string $modelClass, ?string $activeTenantAbi): ?Model
	{
		/** @var Model $model */
		$model = new $modelClass();
		$query = $model->newQuery();
		$table = $model->getTable();

		if (SchemaCache::hasColumn($table, "banca_abi")) {
			if ($activeTenantAbi === "default") {
				$query->whereNull("banca_abi");
			} elseif ($activeTenantAbi !== null && $activeTenantAbi !== "") {
				$query->where("banca_abi", $activeTenantAbi);
			}
		}

		return $query->first();
	}

	protected function setupShowOperation()
	{
		BackpackHelper::setFieldsShow(new Translate());
	}
=======
    protected function setupShowOperation()
    {
        BackpackHelper::setFieldsShow(new Translate);
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}

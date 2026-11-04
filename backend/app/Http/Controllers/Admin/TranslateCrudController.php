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
        CRUD::setRoute(config('backpack.base.route_prefix').'/translate');
        CRUD::setEntityNameStrings('translate', 'translates');

        $this->setupPermissionChecks();
        $this->crud->denyAccess(['create', 'update', 'delete']);
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
        $this->crud->setListView('admin.translates.archive');
        $this->crud->setListContentClass('col-md-12');
        CRUD::setOperationSetting('archiveData', $this->buildArchiveData());
    }

    /**
     * Build archive data for custom list view.
     *
     * @return array<string, mixed>
     */
    private function buildArchiveData(): array
    {
        $query = Translate::query()->with(['page:id,name']);

        $code = trim((string) request()->query('code', ''));
        if ($code !== '') {
            $query->where('code', 'like', "%{$code}%");
        }

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

        return $samples;
    }

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

        return '{$'.$key.'}';
    }

    protected function setupShowOperation()
    {
        BackpackHelper::setFieldsShow(new Translate);
    }
}

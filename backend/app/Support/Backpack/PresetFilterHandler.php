<?php

namespace App\Support\Backpack;

use App\Models\Banca;
use App\Models\Delibera;
use App\Models\Tiraggio;
use App\Models\User;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PresetFilterHandler
{
    /**
     * Preset definitions per model class.
     *
     * Each preset is: label => callback(Model $model) that applies CRUD clauses.
     * The optional 'banca' flag tells the UI to show a bank selector.
     *
     * @return array<string, array{label: string, banche?: bool, apply: callable}>
     */
    public static function presetsForModel(Model $model): array
    {
        return match (get_class($model)) {
            User::class => self::userPresets(),
            Tiraggio::class => self::tiraggioPresets(),
            Delibera::class => self::deliberaPresets(),
            default => [],
        };
    }

    /**
     * All of these describe customers, so all of them filter on the role: bank
     * staff shares the table, and without it they were listed as eligible too.
     */
    public static function userPresets(): array
    {
        $clienti = static function (): void {
            CRUD::addClause('whereHas', 'role', static fn (Builder $q) => $q->where('name', 'User'));
        };

        return [
            'eleggibili' => [
                'label' => 'Eleggibili',
                'banche' => true,
                'apply' => function () use ($clienti): void {
                    $clienti();
                    CRUD::addClause('where', 'sospeso', 0);
                    CRUD::addClause('whereHas', 'banca.prodotto');
                },
            ],
            'non_eleggibili' => [
                'label' => 'Non eleggibili',
                'banche' => true,
                'apply' => function () use ($clienti): void {
                    $clienti();
                    CRUD::addClause('where', function (Builder $q): void {
                        $q->where('sospeso', 1)
                            ->orWhereDoesntHave('banca.prodotto');
                    });
                },
            ],
            'sospesi' => [
                'label' => 'Sospesi',
                'banche' => true,
                'apply' => function () use ($clienti): void {
                    $clienti();
                    CRUD::addClause('where', 'sospeso', 1);
                },
            ],
        ];
    }

    public static function tiraggioPresets(): array
    {
        return [
            'richiesti' => [
                'label' => 'In richiesta',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('where', 'status', 'requested');
                },
            ],
            'accettati' => [
                'label' => 'Accettati',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('where', 'status', 'accepted');
                },
            ],
            'multi_tiraggio' => [
                'label' => 'Multi tiraggio',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereIn', 'status', ['accepted', 'paid']);
                    CRUD::addClause('whereHas', 'user', function (Builder $q): void {
                        $q->whereHas('tiraggi', function (Builder $q2): void {
                            $q2->where('status', 'accepted');
                        }, '>', 1);
                    });
                },
            ],
            'in_linea' => [
                'label' => 'In linea',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereIn', 'status', ['accepted', 'paid']);
                    CRUD::addClause('with', 'proiezioni');
                    self::filterByEsito('in_linea');
                },
            ],
            'lieve_ritardo' => [
                'label' => 'In ritardo',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereIn', 'status', ['accepted', 'paid']);
                    CRUD::addClause('with', 'proiezioni');
                    self::filterByEsito('lieve_ritardo');
                },
            ],
            'grave_ritardo' => [
                'label' => 'Grave ritardo',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereIn', 'status', ['accepted', 'paid']);
                    CRUD::addClause('with', 'proiezioni');
                    self::filterByEsito('grave_ritardo');
                },
            ],
            'conguaglio_plafond' => [
                'label' => 'Conguaglio plafond',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereHas', 'user.banca.prodotto', function (Builder $q): void {
                        $q->where('conguaglio_periodico', true)
                            ->where('metodo_conguaglio', 'plafond');
                    });
                },
            ],
            'conguaglio_previsionale' => [
                'label' => 'Conguaglio previsionale',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereHas', 'user.banca.prodotto', function (Builder $q): void {
                        $q->where('conguaglio_periodico', true)
                            ->where('metodo_conguaglio', 'previsionale');
                    });
                },
            ],
            'durata_parametrica' => [
                'label' => 'Durata parametrica',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereHas', 'user.banca', function (Builder $q): void {
                        $q->where('tiraggio_durata_parametrica', true);
                    });
                },
            ],
            'conguagli_scoperti' => [
                'label' => 'Conguagli scoperti',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereIn', 'status', ['accepted', 'paid']);
                    self::filterByIds(
                        fn (Tiraggio $t): bool => $t->isConguaglio()
                            && $t->getConguagliAttesiCount() > $t->getConguagliVersatiCount()
                    );
                },
            ],
            'in_scadenza' => [
                'label' => 'In scadenza (30gg)',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('where', 'status', 'accepted');
                    CRUD::addClause('whereNotNull', 'data_fine');
                    CRUD::addClause('whereBetween', 'data_fine', [now(), now()->addDays(30)]);
                },
            ],
        ];
    }

    public static function deliberaPresets(): array
    {
        return [
            'aperte' => [
                'label' => 'Aperte',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('where', 'status', 'open');
                },
            ],
            'chiuse' => [
                'label' => 'Chiuse',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('where', 'status', 'close');
                },
            ],
            'multi_tiraggio' => [
                'label' => 'Multi tiraggio',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereHas', 'tiraggi', function (Builder $q): void {
                        $q->where('status', 'accepted');
                    }, '>', 1);
                },
            ],
            'conguaglio_plafond' => [
                'label' => 'Conguaglio plafond',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereHas', 'user.banca.prodotto', function (Builder $q): void {
                        $q->where('conguaglio_periodico', true)
                            ->where('metodo_conguaglio', 'plafond');
                    });
                },
            ],
            'conguaglio_previsionale' => [
                'label' => 'Conguaglio previsionale',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('whereHas', 'user.banca.prodotto', function (Builder $q): void {
                        $q->where('conguaglio_periodico', true)
                            ->where('metodo_conguaglio', 'previsionale');
                    });
                },
            ],
            'senza_tiraggio' => [
                'label' => 'Senza tiraggio',
                'banche' => true,
                'apply' => function (): void {
                    CRUD::addClause('where', 'status', 'open');
                    CRUD::addClause('whereDoesntHave', 'tiraggi');
                },
            ],
        ];
    }

    /**
     * Apply the preset selected via the `preset` request parameter.
     * Also applies the optional `banca_abi` parameter when present.
     */
    public static function applyPreset(Model $model): ?string
    {
        $presetName = request()->get('preset');
        if (! $presetName) {
            return null;
        }

        $presets = self::presetsForModel($model);
        if (! isset($presets[$presetName])) {
            return null;
        }

        $preset = $presets[$presetName];
        ($preset['apply'])();

        // Optional banca filter.
        $bancaAbi = request()->get('banca_abi');
        if ($bancaAbi && $preset['banche'] ?? false) {
            self::applyBancaFilter($model, $bancaAbi);
        }

        return $presetName;
    }

    public static function getActivePreset(): ?string
    {
        $preset = request()->get('preset');

        return $preset !== null && $preset !== '' ? $preset : null;
    }

    public static function getActiveBancaAbi(): ?string
    {
        $banca = request()->get('banca_abi');

        return $banca !== null && $banca !== '' ? $banca : null;
    }

    /**
     * Get list of banks for the dropdown, keyed by ABI.
     *
     * @return array<string, string>
     */
    public static function getBancheOptions(): array
    {
        return Banca::query()->pluck('nome', 'banca_abi')->toArray();
    }

    /**
     * Apply the banca_abi filter depending on the model type.
     */
    protected static function applyBancaFilter(Model $model, string $abi): void
    {
        $modelClass = get_class($model);

        if ($modelClass === User::class) {
            CRUD::addClause('where', 'banca_abi', $abi);

            return;
        }

        // Tiraggio and Delibera both reach banca through user.
        CRUD::addClause('whereHas', 'user', function (Builder $q) use ($abi): void {
            $q->where('banca_abi', $abi);
        });
    }

    /**
     * Narrow the list to the tiraggi a PHP predicate accepts.
     *
     * Same reason as filterByEsito(): these answers come from the model, and
     * re-expressing them in SQL is what let the two drift apart.
     */
    protected static function filterByIds(callable $predicate): void
    {
        $ids = Tiraggio::query()
            ->whereIn('status', ['accepted', 'paid'])
            // Month totals, not the full history: that blew past the memory limit.
            ->with(['user.banca.prodotto'])
            ->withEsitoData()
            ->get()
            ->filter($predicate)
            ->pluck('tiraggio_id')
            ->all();

        CRUD::addClause('whereIn', 'tiraggio_id', $ids ?: [0]);
    }

    /**
     * Filter tiraggi by computed esito.
     *
     * Resolved through Tiraggio::computeEsito() rather than re-expressed in
     * SQL. The SQL version only knew the forecast/baseline comparison, so every
     * conguaglio tiraggio — where the esito is a count of settled vs expected
     * periods — landed in the wrong bucket, and durata parametrica (baseline
     * deliberately zero) could never reach "grave ritardo" at all.
     *
     * ponytail: loads the accepted/paid tiraggi to resolve the ids, which is
     * fine at this size (hundreds). If the table grows past a few thousand,
     * materialise esito on the row instead of widening this.
     */
    protected static function filterByEsito(string $esito): void
    {
        $ids = Tiraggio::query()
            ->whereIn('status', ['accepted', 'paid'])
            // Month totals, not the full history: that blew past the memory limit.
            ->with(['user.banca.prodotto'])
            ->withEsitoData()
            ->get()
            ->filter(fn (Tiraggio $tiraggio): bool => $tiraggio->esito === $esito)
            ->pluck('tiraggio_id')
            ->all();

        CRUD::addClause('whereIn', 'tiraggio_id', $ids ?: [0]);
    }
}

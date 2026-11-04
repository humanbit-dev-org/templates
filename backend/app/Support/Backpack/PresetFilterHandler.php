<?php

namespace App\Support\Backpack;

use App\Models\User;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;

/**
 * Quick filters ("Filtri rapidi") shown as pills above a Backpack list.
 *
 * A preset is a named set of CRUD clauses selected through the `preset`
 * query parameter; when one is active, FilterHandler applies it and skips
 * the advanced filters entirely.
 */
class PresetFilterHandler
{
<<<<<<< HEAD
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
			default => [],
		};
	}

	/**
	 * All of these describe customers, so all of them filter on the role: bank
	 * staff shares the table, and without it they were listed as eligible too.
	 */
	public static function userPresets(): array
	{
		return [];
	}
=======
    /**
     * Preset definitions per model class.
     *
     * Each preset is: key => ['label' => string, 'apply' => callable], where
     * the callable adds the CRUD clauses (CRUD::addClause(...)).
     *
     * @return array<string, array{label: string, apply: callable}>
     */
    public static function presetsForModel(Model $model): array
    {
        return match (get_class($model)) {
            User::class => self::userPresets(),
            default => [],
        };
    }

    /**
     * Quick filters of the users list.
     *
     * @return array<string, array{label: string, apply: callable}>
     */
    public static function userPresets(): array
    {
        return [
            'backpack' => [
                'label' => 'Utenti Backpack',
                'apply' => function (): void {
                    CRUD::addClause('whereNotNull', 'backpack_role_id');
                },
            ],
            'unverified' => [
                'label' => 'Email non verificata',
                'apply' => function (): void {
                    CRUD::addClause('whereNull', 'email_verified_at');
                },
            ],
        ];
    }

    /**
     * Apply the preset selected via the `preset` request parameter.
     *
     * @return string|null The applied preset key, or null when none matched
     */
    public static function applyPreset(Model $model): ?string
    {
        $presetName = self::getActivePreset();
        if ($presetName === null) {
            return null;
        }

        $presets = self::presetsForModel($model);
        if (! isset($presets[$presetName])) {
            return null;
        }

        ($presets[$presetName]['apply'])();

        return $presetName;
    }

    /**
     * Key of the preset requested through the `preset` query parameter.
     */
    public static function getActivePreset(): ?string
    {
        $preset = request()->get('preset');

        return $preset !== null && $preset !== '' ? $preset : null;
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}

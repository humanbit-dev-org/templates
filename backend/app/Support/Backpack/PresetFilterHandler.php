<?php

namespace App\Support\Backpack;

use App\Models\Banca;
use App\Models\User;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
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
}

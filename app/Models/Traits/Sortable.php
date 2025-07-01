<?php

namespace App\Models\Traits;

trait Sortable
{
	protected static function bootSortable()
	{
		static::creating(function ($model) {
			static::adjustOrdersOnCreate($model);
		});

		static::updating(function ($model) {
			static::adjustOrdersOnUpdate($model);
		});

		static::deleting(function ($model) {
			static::adjustOrdersOnDelete($model);
		});
	}

	private static function adjustOrdersOnCreate($model)
	{
		static::where("order", ">=", $model->order)->increment("order");
	}

	private static function adjustOrdersOnUpdate($model)
	{
		$originalOrder = $model->getOriginal("order");
		$newOrder = $model->order;

		if ($originalOrder !== $newOrder) {
			if ($newOrder < $originalOrder) {
				static::whereBetween("order", [$newOrder, $originalOrder - 1])->increment("order");
			} elseif ($newOrder > $originalOrder) {
				static::whereBetween("order", [$originalOrder + 1, $newOrder])->decrement("order");
			}
		}
	}

	private static function adjustOrdersOnDelete($model)
	{
		static::where("order", ">", $model->order)->decrement("order");
	}
}

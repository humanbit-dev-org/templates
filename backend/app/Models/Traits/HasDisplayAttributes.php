<?php

namespace App\Models\Traits;

trait HasDisplayAttributes
{
    /**
     * Returns a human-readable label for the model instance.
     * Used automatically by Backpack CRUD and other components via $model->display
     * (magic __get → getDisplayAttribute).
     *
     * Override in the model returning the desired string.
     * Default fallback: primary key.
     */
    public function getDisplayAttribute(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Returns a richer label used when this model appears inside
     * relationship selects, autocompletes, or pivot tables.
     *
     * Override in the model returning the desired string.
     * Default fallback: getDisplayAttribute().
     */
    public function getRelationDisplayAttribute(): string
    {
        return $this->display;
    }
}

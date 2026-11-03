<?php

namespace App\Observers;

use App\Models\Metadata;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates SEO metadata cache when Metadata rows change via Eloquent (Backpack admin).
 */
class MetadataObserver
{
    public function created(Metadata $metadata): void
    {
        $this->invalidate($metadata);
    }

    public function updated(Metadata $metadata): void
    {
        $this->invalidate($metadata);
    }

    public function deleted(Metadata $metadata): void
    {
        $this->invalidate($metadata);
    }

    private function invalidate(Metadata $metadata): void
    {
        $pageName = $metadata->relationLoaded('page')
            ? $metadata->page?->name
            : Page::query()->whereKey($metadata->page_id)->value('name');

        if ($pageName === null || $pageName === '') {
            return;
        }

        foreach (['en', 'it'] as $lang) {
            Cache::forget("seo:{$lang}:{$pageName}");
        }

        Log::debug('MetadataObserver: invalidated SEO cache', [
            'page' => $pageName,
        ]);
    }
}

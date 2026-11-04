<?php

namespace App\Services\Translate;

use App\Models\Page;
use App\Models\Translate;
use App\Support\Url\FrontendLang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TranslateImportService
{
    /**
     * Import translations from JSON files
     */
    public static function importTranslations(): bool
    {
        if (! self::shouldRunImport()) {
            Log::warning('Import skipped - translation files not available');

            return false;
        }

        $itPath = FrontendLang::jsonPath('it');
        $enPath = FrontendLang::jsonPath('en');

        try {
            $italianData = json_decode(File::get($itPath), true);
            $englishData = json_decode(File::get($enPath), true);

            if (! is_array($italianData) || ! is_array($englishData)) {
                Log::error('Failed to decode translation JSON files');

                return false;
            }

            DB::transaction(function () use ($italianData, $englishData): void {
                Translate::withoutEvents(function () use ($italianData, $englishData): void {
                    self::processTranslations($italianData, $englishData);
                });
            });

            Log::info('Translations imported successfully from JSON files with full overwrite');

            return true;
        } catch (\Exception $e) {
            Log::error('Error importing translations: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Process and save translations to database
     */
    private static function processTranslations(array $italianData, array $englishData): void
    {
        $allPageNames = array_unique(array_merge(array_keys($italianData), array_keys($englishData)));
        $seenKeys = [];

        foreach ($allPageNames as $pageName) {
            $pageId = self::getPageId($pageName);
            $italianTranslations = $italianData[$pageName] ?? [];
            $englishTranslations = $englishData[$pageName] ?? [];

            $allCodes = array_unique(array_merge(array_keys($italianTranslations), array_keys($englishTranslations)));

            foreach ($allCodes as $code) {
                $itNode = $italianTranslations[$code] ?? null;
                $enNode = $englishTranslations[$code] ?? null;

                // Leaf shape: $node is an object with keys it/en/text_*. Files written
                // with per-variant nodes ({"default": {...}, ...}) keep working: only the
                // "default" variant is imported, the template has no other dimension.
                $looksLikeLeaf =
                    is_array($itNode) && (array_key_exists('it', $itNode) || array_key_exists('text_it', $itNode)) ||
                    is_array($enNode) && (array_key_exists('en', $enNode) || array_key_exists('text_en', $enNode));

                if (! $looksLikeLeaf) {
                    $itNode = is_array($itNode) ? ($itNode['default'] ?? null) : null;
                    $enNode = is_array($enNode) ? ($enNode['default'] ?? null) : null;

                    if ($itNode === null && $enNode === null) {
                        continue;
                    }
                }

                self::upsertTranslation($pageId, $code, $itNode, $enNode);
                $seenKeys[] = self::canonicalKey($pageId, $code);
            }
        }

        self::deleteTranslationsNotInImport($seenKeys);
    }

    /**
     * Get page ID for a page name, handling special "all" case
     */
    private static function getPageId(string $pageName): ?int
    {
        if ($pageName === 'all') {
            return null;
        }

        $page = self::ensurePageExists($pageName);

        if (! $page) {
            Log::warning("Could not create/find page: {$pageName}");

            return null;
        }

        return $page->id;
    }

    /**
     * Ensure page exists in database, create if needed
     */
    private static function ensurePageExists(string $pageName): ?Page
    {
        $page = Page::where('name', $pageName)->first();

        if (! $page) {
            $page = Page::create([
                'name' => $pageName,
                'title' => ucfirst(str_replace('-', ' ', $pageName)),
                'description' => '',
                'order' => Page::max('order') + 1,
                'visible' => true,
            ]);

            Log::info("Created page: {$pageName}");
        }

        return $page;
    }

    /**
     * Create or update translation record
     */
    private static function upsertTranslation(?int $pageId, string $code, mixed $italianData, mixed $englishData): void
    {
        $query = Translate::where('code', $code);

        if ($pageId === null) {
            $query->whereNull('page_id');
        } else {
            $query->where('page_id', $pageId);
        }

        $translation = $query->first();

        $leaf = self::normalizeLeafData(
            is_array($italianData) ? $italianData : null,
            is_array($englishData) ? $englishData : null
        );

        $data = [
            'page_id' => $pageId,
            'code' => $code,
            'it' => $leaf['it'],
            'text_it' => $leaf['text_it'],
            'en' => $leaf['en'],
            'text_en' => $leaf['text_en'],
        ];

        if ($translation) {
            $translation->update($data);
            Log::debug("Updated translation: {$code} for page ID ".($pageId ?? 'global'));
        } else {
            Translate::create($data);
            Log::debug("Created translation: {$code} for page ID ".($pageId ?? 'global'));
        }
    }

    /**
     * Map JSON leaf nodes to Translate columns (handles missing IT/EN side or non-array nodes).
     *
     * @param  array<string, mixed>|null  $italianLeaf
     * @param  array<string, mixed>|null  $englishLeaf
     * @return array{it: string, text_it: string, en: string, text_en: string}
     */
    private static function normalizeLeafData(?array $italianLeaf, ?array $englishLeaf): array
    {
        $italianLeaf = $italianLeaf ?? [];
        $englishLeaf = $englishLeaf ?? [];

        return [
            'it' => isset($italianLeaf['it']) ? (string) $italianLeaf['it'] : '',
            'text_it' => isset($italianLeaf['text_it']) ? (string) $italianLeaf['text_it'] : '',
            'en' => isset($englishLeaf['en']) ? (string) $englishLeaf['en'] : '',
            'text_en' => isset($englishLeaf['text_en']) ? (string) $englishLeaf['text_en'] : '',
        ];
    }

    /**
     * Check if import can run.
     */
    private static function shouldRunImport()
    {
        $itPath = FrontendLang::jsonPath('it');
        $enPath = FrontendLang::jsonPath('en');

        if (! File::exists($itPath) || ! File::exists($enPath)) {
            return false;
        }

        return true;
    }

    /**
     * Delete records not present in the imported JSON snapshot.
     *
     * @param  array<int, string>  $seenKeys
     */
    private static function deleteTranslationsNotInImport(array $seenKeys): void
    {
        $seenLookup = array_fill_keys($seenKeys, true);

        Translate::query()->select(['id', 'page_id', 'code'])
            ->chunkById(500, function ($rows) use ($seenLookup): void {
                $idsToDelete = [];

                foreach ($rows as $row) {
                    $key = self::canonicalKey($row->page_id, (string) $row->code);
                    if (! isset($seenLookup[$key])) {
                        $idsToDelete[] = $row->id;
                    }
                }

                if ($idsToDelete !== []) {
                    Translate::whereIn('id', $idsToDelete)->delete();
                }
            });
    }

    /**
     * Build a canonical key to compare imported and persisted rows.
     */
    private static function canonicalKey(?int $pageId, string $code): string
    {
        return implode('|', [
            $pageId === null ? 'null' : (string) $pageId,
            $code,
        ]);
    }
}

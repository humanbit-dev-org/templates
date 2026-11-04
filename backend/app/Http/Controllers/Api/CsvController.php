<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\WritesCsvExport;
use App\Http\Controllers\Controller;

/**
 * CSV exports of frontend lists, served to the authenticated frontend.
 *
 * Each export validates the list filters, builds headers and rows, and lets
 * WritesCsvExport write the file and answer with its download URL. The
 * template exposes no list yet; a typical action looks like:
 *
 *     public function exportArticles(Request $request): JsonResponse
 *     {
 *         $rows = Article::query()
 *             ->where('published', true)
 *             ->get(['id', 'title_italian', 'created_at'])
 *             ->map(fn (Article $a) => [$a->id, $a->title_italian, $a->created_at?->toDateString()])
 *             ->all();
 *
 *         return $this->exportCsvResponse('articles', ['ID', 'Titolo', 'Creato il'], $rows);
 *     }
 *
 * and is routed in routes/api.php behind auth:sanctum.
 */
class CsvController extends Controller
{
    use WritesCsvExport;
}

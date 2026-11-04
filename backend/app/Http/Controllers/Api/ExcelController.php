<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\WritesXlsxExport;
use App\Http\Controllers\Controller;

/**
 * XLSX exports of frontend lists, served to the authenticated frontend.
 *
 * Same shape as CsvController, plus one ExportColumn per header describing
 * the cell format, locked values and validation (see WritesXlsxExport):
 *
 *     public function exportArticles(Request $request): JsonResponse
 *     {
 *         $rows = Article::query()->get(['id', 'title_italian', 'created_at'])
 *             ->map(fn (Article $a) => [$a->id, $a->title_italian, $a->created_at?->toDateString()])
 *             ->all();
 *
 *         return $this->exportXlsxResponse(
 *             'articles',
 *             ['ID', 'Titolo', 'Creato il'],
 *             $rows,
 *             [ExportColumn::lockedText(), ExportColumn::text(), ExportColumn::date()],
 *         );
 *     }
 *
 * and is routed in routes/api.php behind auth:sanctum.
 */
class ExcelController extends Controller
{
    use WritesXlsxExport;
}

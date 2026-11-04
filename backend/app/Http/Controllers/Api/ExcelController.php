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
<<<<<<< HEAD
	use BancaListQueries, DashboardListQueries, PreparesListExportData, WritesXlsxExport;

	public function __construct()
	{
		$this->raiseExportLimits();
	}

	public function exportStoricoErogazioni(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareStoricoErogazioniExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportRichiesteAtp(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareRichiesteAtpExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportBancaEleggibili(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		abort_if(Auth::user()->banca === null, 403);

		$sheet = $this->prepareBancaEleggibiliExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportClientiEleggibili(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareDashboardEleggibiliExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportCreditiAttivi(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareDashboardCreditiAttiviExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportBancaTiraggi(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		abort_if(Auth::user()->banca === null, 403);

		$sheet = $this->prepareBancaTiraggiExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportBancaFiliali(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareBancaFilialiExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}

	public function exportBancaAccessi(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		abort_if(Auth::user()->banca === null, 403);

		$sheet = $this->prepareBancaAccessiExport($values);

		return $this->exportXlsxResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"], $sheet["columns"]);
	}
=======
    use WritesXlsxExport;
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}

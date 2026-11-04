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
<<<<<<< HEAD
	use BancaListQueries, DashboardListQueries, PreparesListExportData, WritesCsvExport;

	public function __construct()
	{
		$this->raiseExportLimits();
	}

	public function exportStoricoErogazioni(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareStoricoErogazioniExport($values);

		return $this->exportCsvResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"]);
	}

	public function exportRichiesteAtp(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareRichiesteAtpExport($values);

		return $this->exportCsvResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"]);
	}

	public function exportBancaEleggibili(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		abort_if(Auth::user()->banca === null, 403);

		$sheet = $this->prepareBancaEleggibiliExport($values);

		return $this->exportCsvResponse(
			$sheet["filePrefix"],
			$sheet["headers"],
			$this->applyCsvRateColumns($sheet["rows"], [6, 7])
		);
	}

	public function exportClientiEleggibili(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareDashboardEleggibiliExport($values);

		return $this->exportCsvResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"]);
	}

	public function exportCreditiAttivi(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareDashboardCreditiAttiviExport($values);

		return $this->exportCsvResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"]);
	}

	public function exportBancaTiraggi(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		abort_if(Auth::user()->banca === null, 403);

		$sheet = $this->prepareBancaTiraggiExport($values);
		$rateColumnIndexes = $values["status"] === "accepted" ? [5, 6] : [6, 7];

		return $this->exportCsvResponse(
			$sheet["filePrefix"],
			$sheet["headers"],
			$this->applyCsvRateColumns($sheet["rows"], $rateColumnIndexes)
		);
	}

	public function exportBancaFiliali(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		$sheet = $this->prepareBancaFilialiExport($values);

		return $this->exportCsvResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"]);
	}

	public function exportBancaAccessi(Request $request): JsonResponse
	{
		$values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

		abort_if(Auth::user()->banca === null, 403);

		$sheet = $this->prepareBancaAccessiExport($values);

		return $this->exportCsvResponse($sheet["filePrefix"], $sheet["headers"], $sheet["rows"]);
	}
=======
    use WritesCsvExport;
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\BancaListQueries;
use App\Http\Concerns\DashboardListQueries;
use App\Http\Concerns\PreparesListExportData;
use App\Http\Concerns\WritesCsvExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CsvController extends Controller
{
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
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\BancaListQueries;
use App\Http\Concerns\DashboardListQueries;
use App\Http\Concerns\PreparesListExportData;
use App\Http\Concerns\WritesXlsxExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExcelController extends Controller
{
    use BancaListQueries, DashboardListQueries, PreparesListExportData, WritesXlsxExport;

    public function __construct()
    {
        $this->raiseExportLimits();
    }

    public function exportStoricoErogazioni(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        $sheet = $this->prepareStoricoErogazioniExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportRichiesteAtp(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        $sheet = $this->prepareRichiesteAtpExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportBancaEleggibili(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        abort_if(Auth::user()->banca === null, 403);

        $sheet = $this->prepareBancaEleggibiliExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportClientiEleggibili(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        $sheet = $this->prepareDashboardEleggibiliExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportCreditiAttivi(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        $sheet = $this->prepareDashboardCreditiAttiviExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportBancaTiraggi(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        abort_if(Auth::user()->banca === null, 403);

        $sheet = $this->prepareBancaTiraggiExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportBancaFiliali(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        $sheet = $this->prepareBancaFilialiExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }

    public function exportBancaAccessi(Request $request): JsonResponse
    {
        $values = Validator::make($this->exportRequestPayload($request), $this->exportRules(__FUNCTION__))->validate();

        abort_if(Auth::user()->banca === null, 403);

        $sheet = $this->prepareBancaAccessiExport($values);

        return $this->exportXlsxResponse(
            $sheet['filePrefix'],
            $sheet['headers'],
            $sheet['rows'],
            $sheet['columns'],
        );
    }
}

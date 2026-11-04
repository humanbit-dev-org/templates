<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Controllers\Controller;
use App\Support\Backpack\Handler\FilterHandler;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportXlsxAction extends Controller
{
	public function exportCrudToXlsx($crud)
	{
		set_time_limit(0);
		ini_set("memory_limit", "512M");

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			throw new NotFoundHttpException("Model not found");
		}

		$model = new $modelClass();

		$query = $modelClass::query();

		FilterHandler::applyFiltersToQuery($query, $model);

		if ($query->count() === 0) {
			throw new NotFoundHttpException("No data to export");
		}

		$xlsxFileName = strtolower(class_basename($modelClass)) . "_export_" . now()->format("Ymd_His") . ".xlsx";
		$xlsxPath = storage_path("app/" . $xlsxFileName);

		$modelClass::unsetEventDispatcher();

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$isFirstChunk = true;
		$headers = [];
		$rowIndex = 2;

		$query->chunk(5000, function ($entries) use ($sheet, &$isFirstChunk, &$headers, &$rowIndex) {
			foreach ($entries as $entry) {
				$baseData = $entry->toArray();

				if ($isFirstChunk) {
					$headers = array_keys($baseData);
					foreach ($headers as $colIdx => $header) {
						$columnLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
						$sheet->setCellValue($columnLetter . "1", $header);
					}

					$headerRange = "A1:" . Coordinate::stringFromColumnIndex(count($headers)) . "1";
					$sheet->getStyle($headerRange)->getFont()->setBold(true);
					$sheet
						->getStyle($headerRange)
						->getFill()
						->setFillType(Fill::FILL_SOLID)
						->getStartColor()
						->setARGB("FFE8EEF7");

					$isFirstChunk = false;
				}

				$colNum = 0;
				foreach ($baseData as $value) {
					if (is_array($value) || is_object($value)) {
						$value = json_encode($value);
					}
					$columnLetter = Coordinate::stringFromColumnIndex($colNum + 1);
					$sheet->setCellValue($columnLetter . $rowIndex, $value);
					$colNum++;
				}

				$rowIndex++;
			}
		});

		$sheet->freezePane("A2");

		for ($colIndex = 0; $colIndex < count($headers); $colIndex++) {
			$columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
			$sheet->getColumnDimension($columnLetter)->setWidth(16);
		}

		$writer = new Xlsx($spreadsheet);
		$writer->save($xlsxPath);
		$spreadsheet->disconnectWorksheets();
		unset($spreadsheet);

		$downloadToken = request()->get("downloadToken");

		if ($downloadToken) {
			setcookie("downloadToken", $downloadToken, time() + 60, "/");
		}

		return response()->download($xlsxPath)->deleteFileAfterSend(true);
	}
}

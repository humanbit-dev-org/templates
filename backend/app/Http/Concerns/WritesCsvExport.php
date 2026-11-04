<?php

namespace App\Http\Concerns;

use App\Support\Backpack\CsvHelper;
use Illuminate\Http\JsonResponse;

trait WritesCsvExport
{
	/**
	 * @param  list<string>  $headers
	 * @param  list<list<scalar|null>>  $rows
	 */
	protected function exportCsvResponse(string $filePrefix, array $headers, array $rows): JsonResponse
	{
		$exportDir = public_path("storage/export-csv");
		if (!file_exists($exportDir)) {
			mkdir($exportDir, 0755, true);
		}

		$files = glob($exportDir . "/" . $filePrefix . "_*.csv");
		if (count($files) > 0) {
			usort($files, static fn($a, $b) => filemtime($b) - filemtime($a));

			foreach (array_slice($files, 1) as $file) {
				if (file_exists($file)) {
					unlink($file);
				}
			}
		}

		$filename = $filePrefix . "_" . date("Y-m-d_H-i-s") . ".csv";
		$filepath = $exportDir . "/" . $filename;

		$file = fopen($filepath, "w");
		fwrite($file, "\xEF\xBB\xBF");
		$this->fputcsvExportRow($file, $headers);

		foreach ($rows as $row) {
			$this->fputcsvExportRow($file, $row);
		}

		fclose($file);

		return response()->json(
			[
				"success" => true,
				"file_path" => "/storage/export-csv/" . $filename,
				"filename" => $filename,
				"total_records" => count($rows),
			],
			201
		);
	}

	/**
	 * @param  resource  $file
	 * @param  list<mixed>  $row
	 */
	protected function fputcsvExportRow($file, array $row): void
	{
		fputcsv($file, $row, escape: "\\");
	}

	/**
	 * Format TAN/TAEG for CSV: dot as decimal separator (e.g. 6.338).
	 * CSV has no native column types; interpretation depends on the tool that opens the file.
	 */
	protected function formatCsvRateForExport(mixed $value): ?string
	{
		if ($value === null || $value === "") {
			return null;
		}

		$stringValue = trim((string) $value);
		if ($stringValue === "") {
			return null;
		}

		$normalized = CsvHelper::simpleDecimal($stringValue);
		if ($normalized === null || !is_numeric($normalized)) {
			return $stringValue;
		}

		return rtrim(rtrim(number_format((float) $normalized, 3, ".", ""), "0"), ".");
	}
}

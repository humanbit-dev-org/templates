<?php

namespace App\Http\Concerns;

use App\Support\Export\ExportColumn;
use App\Support\Export\ExportColumnFormat;
use App\Support\Backpack\CsvHelper;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait WritesXlsxExport
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     * @param  list<ExportColumn>  $columns
     */
    protected function exportXlsxResponse(
        string $filePrefix,
        array $headers,
        array $rows,
        array $columns,
    ): JsonResponse {
        if (count($columns) !== count($headers)) {
            throw new \InvalidArgumentException('Column format count must match header count.');
        }

        $exportDir = public_path('storage/export-xlsx');
        if (! file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $files = glob($exportDir.'/'.$filePrefix.'_*.xlsx');
        if (count($files) > 0) {
            usort($files, static fn ($a, $b) => filemtime($b) - filemtime($a));

            foreach (array_slice($files, 1) as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        $filename = $filePrefix.'_'.date('Y-m-d_H-i-s').'.xlsx';
        $filepath = $exportDir.'/'.$filename;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $index => $header) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($columnLetter.'1', $header);
        }

        $headerRange = 'A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8EEF7');

        $lastRow = max(1, count($rows) + 1);

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;

            foreach ($row as $colIndex => $value) {
                $columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
                $cellAddress = $columnLetter.$excelRow;
                $column = $columns[$colIndex] ?? ExportColumn::text();

                $this->writeXlsxCell($sheet, $cellAddress, $value, $column);
            }
        }

        foreach ($columns as $colIndex => $column) {
            $columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $dataRange = $columnLetter.'2:'.$columnLetter.$lastRow;
            $sheet->getStyle($dataRange)->getNumberFormat()->setFormatCode($column->excelFormatCode());
            $sheet->getColumnDimension($columnLetter)->setWidth(16);
            $this->applyXlsxHeaderHint($sheet, $columnLetter.'1', $column);

            if ($column->informational) {
                $informationalStyle = $sheet->getStyle($columnLetter.'1:'.$columnLetter.$lastRow);
                $informationalStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDEDED');
                $informationalStyle->getFont()->setItalic(true)->getColor()->setARGB('FF7F7F7F');
            }

            if ($lastRow < 2) {
                continue;
            }

            if ($column->allowedValues !== null) {
                $this->applyXlsxColumnSelectValidation(
                    $sheet,
                    $columnLetter,
                    2,
                    $lastRow,
                    $column->allowedValues,
                );

                continue;
            }

            match ($column->format) {
                ExportColumnFormat::Integer => $this->applyXlsxColumnNumericValidation(
                    $sheet,
                    $columnLetter,
                    2,
                    $lastRow,
                    wholeNumber: true,
                ),
                ExportColumnFormat::Decimal, ExportColumnFormat::Percentage => $this->applyXlsxColumnNumericValidation(
                    $sheet,
                    $columnLetter,
                    2,
                    $lastRow,
                    wholeNumber: false,
                ),
                ExportColumnFormat::Ateco => $this->applyXlsxColumnAtecoValidation($sheet, $columnLetter, 2, $lastRow),
                ExportColumnFormat::Provincia => $this->applyXlsxColumnProvinciaValidation($sheet, $columnLetter, 2, $lastRow),
                ExportColumnFormat::Cap => $this->applyXlsxColumnCapValidation($sheet, $columnLetter, 2, $lastRow),
                default => null,
            };
        }

        $sheet->freezePane('A2');
        $this->lockXlsxWorksheet($sheet, $lastRow, count($headers), $columns);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->json([
            'success' => true,
            'file_path' => '/storage/export-xlsx/'.$filename,
            'filename' => $filename,
            'total_records' => count($rows),
        ], 201);
    }

    protected function writeXlsxCell(Worksheet $sheet, string $cellAddress, mixed $value, ExportColumn $column): void
    {
        if ($value === null || $value === '') {
            return;
        }

        match ($column->format) {
            ExportColumnFormat::Text,
            ExportColumnFormat::Email,
            ExportColumnFormat::Ateco,
            ExportColumnFormat::Provincia,
            ExportColumnFormat::Cap => $sheet->setCellValueExplicit(
                $cellAddress,
                (string) $value,
                DataType::TYPE_STRING,
            ),
            ExportColumnFormat::Integer => $sheet->setCellValue($cellAddress, (int) $value),
            ExportColumnFormat::Decimal, ExportColumnFormat::Percentage => $sheet->setCellValue(
                $cellAddress,
                $this->normalizeXlsxNumericValue($value),
            ),
            ExportColumnFormat::Date => $sheet->setCellValue(
                $cellAddress,
                is_string($value) ? $value : (string) $value,
            ),
            ExportColumnFormat::Pattern => $this->writeXlsxPatternCell($sheet, $cellAddress, $value, $column),
        };
    }

    protected function writeXlsxPatternCell(Worksheet $sheet, string $cellAddress, mixed $value, ExportColumn $column): void
    {
        if ($column->pattern === '@' || $column->pattern === null) {
            $sheet->setCellValueExplicit($cellAddress, (string) $value, DataType::TYPE_STRING);

            return;
        }

        if (is_numeric($value) || (is_string($value) && is_numeric(CsvHelper::simpleDecimal((string) $value) ?? ''))) {
            $sheet->setCellValue($cellAddress, $this->normalizeXlsxNumericValue($value));

            return;
        }

        $sheet->setCellValueExplicit($cellAddress, (string) $value, DataType::TYPE_STRING);
    }

    protected function normalizeXlsxNumericValue(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $normalized = CsvHelper::simpleDecimal(trim((string) $value));

        return $normalized !== null && is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    /**
     * @param  list<ExportColumn>  $columns
     */
    protected function lockXlsxWorksheet(Worksheet $sheet, int $lastRow, int $columnCount, array $columns): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $headerRange = 'A1:'.$lastColumn.'1';
        $dataRange = 'A2:'.$lastColumn.$lastRow;

        $sheet->getStyle($dataRange)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle($headerRange)->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);

        foreach ($columns as $colIndex => $column) {
            if (! $column->lockValues) {
                continue;
            }

            $columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->getStyle($columnLetter.'2:'.$columnLetter.$lastRow)
                ->getProtection()
                ->setLocked(Protection::PROTECTION_PROTECTED);
        }

        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('');
        $sheet->getProtection()->setSort(false);
        $sheet->getProtection()->setInsertRows(false);
        // PhpSpreadsheet: true = operation locked. false = user may resize columns/rows.
        $sheet->getProtection()->setFormatCells(true);
        $sheet->getProtection()->setFormatColumns(false);
        $sheet->getProtection()->setFormatRows(false);
        $sheet->getProtection()->setInsertColumns(true);
        $sheet->getProtection()->setDeleteColumns(true);
        $sheet->getProtection()->setDeleteRows(true);
        $sheet->getProtection()->setSelectLockedCells(false);
        $sheet->getProtection()->setSelectUnlockedCells(false);
    }

    protected function applyXlsxHeaderHint(Worksheet $sheet, string $headerCell, ExportColumn $column): void
    {
        if ($column->headerHint === null || $column->headerHint === '') {
            return;
        }

        $comment = $sheet->getComment($headerCell);
        $comment->getText()->createTextRun($column->headerHint);
        $comment->setWidth('220pt');
        $comment->setHeight('80pt');
        $comment->setMarginLeft('6pt');
        $comment->setMarginTop('6pt');
    }

    /**
     * @param  list<string>  $allowedValues
     */
    protected function applyXlsxColumnAtecoValidation(
        Worksheet $sheet,
        string $columnLetter,
        int $firstRow,
        int $lastRow,
    ): void {
        $this->applyXlsxColumnCustomValidation(
            $sheet,
            $columnLetter,
            $firstRow,
            $lastRow,
            'Inserire solo cifre e punti (senza lettere), es. 11.11.11.',
            static fn (string $cellAddress): string => sprintf(
                'OR(LEN(%1$s)=0,ISNUMBER(--SUBSTITUTE(%1$s,".","")))',
                $cellAddress,
            ),
        );
    }

    protected function applyXlsxColumnProvinciaValidation(
        Worksheet $sheet,
        string $columnLetter,
        int $firstRow,
        int $lastRow,
    ): void {
        $this->applyXlsxColumnCustomValidation(
            $sheet,
            $columnLetter,
            $firstRow,
            $lastRow,
            'Inserire esattamente 2 lettere (sigla provincia), es. RM.',
            static fn (string $cellAddress): string => sprintf(
                'OR(LEN(%1$s)=0,AND(LEN(%1$s)=2,ISERROR(FIND(MID(%1$s,1,1),"0123456789")),ISERROR(FIND(MID(%1$s,2,1),"0123456789"))))',
                $cellAddress,
            ),
        );
    }

    protected function applyXlsxColumnCapValidation(
        Worksheet $sheet,
        string $columnLetter,
        int $firstRow,
        int $lastRow,
    ): void {
        $this->applyXlsxColumnCustomValidation(
            $sheet,
            $columnLetter,
            $firstRow,
            $lastRow,
            'Inserire esattamente 5 cifre, senza lettere, es. 00100.',
            // Digit by digit: ISNUMBER(--cell) alone would accept "1E234" or "1.234".
            static fn (string $cellAddress): string => sprintf(
                'OR(LEN(%1$s)=0,AND(LEN(%1$s)=5,ISNUMBER(--MID(%1$s,1,1)),ISNUMBER(--MID(%1$s,2,1)),ISNUMBER(--MID(%1$s,3,1)),ISNUMBER(--MID(%1$s,4,1)),ISNUMBER(--MID(%1$s,5,1))))',
                $cellAddress,
            ),
        );
    }

    protected function applyXlsxColumnCustomValidation(
        Worksheet $sheet,
        string $columnLetter,
        int $firstRow,
        int $lastRow,
        string $errorMessage,
        callable $formulaForCell,
    ): void {
        // One validation for the whole column range: Excel shifts the relative
        // reference of the first cell for every other row of the sqref.
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_CUSTOM);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valore non valido');
        $validation->setError($errorMessage);
        $validation->setFormula1($formulaForCell($columnLetter.$firstRow));
        $sheet->setDataValidation($this->xlsxColumnRange($columnLetter, $firstRow, $lastRow), $validation);
    }

    protected function applyXlsxColumnNumericValidation(
        Worksheet $sheet,
        string $columnLetter,
        int $firstRow,
        int $lastRow,
        bool $wholeNumber,
    ): void {
        $validationType = $wholeNumber ? DataValidation::TYPE_WHOLE : DataValidation::TYPE_DECIMAL;
        $errorMessage = $wholeNumber
            ? 'Inserire solo numeri interi (senza lettere).'
            : 'Inserire solo numeri decimali (senza lettere).';

        $validation = new DataValidation;
        $validation->setType($validationType);
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setFormula1('-999999999999999');
        $validation->setFormula2('999999999999999');
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valore non valido');
        $validation->setError($errorMessage);
        $sheet->setDataValidation($this->xlsxColumnRange($columnLetter, $firstRow, $lastRow), $validation);
    }

    /**
     * @param  list<string>  $allowedValues
     */
    protected function applyXlsxColumnSelectValidation(
        Worksheet $sheet,
        string $columnLetter,
        int $firstRow,
        int $lastRow,
        array $allowedValues,
    ): void {
        if ($allowedValues === []) {
            return;
        }

        $listFormula = '"'.implode(',', array_map(
            static fn (string $value): string => str_replace('"', '""', $value),
            $allowedValues,
        )).'"';

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valore non valido');
        $validation->setError('Seleziona un valore dall\'elenco.');
        $validation->setFormula1($listFormula);
        $sheet->setDataValidation($this->xlsxColumnRange($columnLetter, $firstRow, $lastRow), $validation);
    }

    protected function xlsxColumnRange(string $columnLetter, int $firstRow, int $lastRow): string
    {
        return $columnLetter.$firstRow.':'.$columnLetter.$lastRow;
    }
}

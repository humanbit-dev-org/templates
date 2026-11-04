<?php

namespace App\Support\Export;

/**
 * Cell format of an export column (see ExportColumn and WritesXlsxExport):
 * drives the Excel number format, the cell type written and the data
 * validation applied to the column. Ateco, Provincia and Cap are Italian
 * code formats (business activity code, province abbreviation, postal code).
 */
enum ExportColumnFormat: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case Email = 'email';
    case Percentage = 'percentage';
    case Pattern = 'pattern';
    case Ateco = 'ateco';
    case Provincia = 'provincia';
    case Cap = 'cap';
}

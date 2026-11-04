<?php

namespace App\Support\Export;

/**
 * Description of one column of an XLSX export (WritesXlsxExport): its format,
 * whether its values are locked, the hint shown on the header cell and the
 * allowed values of a select column. Build columns with the named
 * constructors (text(), integer(), date(), select(), ...).
 */
final readonly class ExportColumn
{
    /**
     * @param  list<string>|null  $allowedValues
     */
    public function __construct(
        public ExportColumnFormat $format,
        public ?int $decimals = null,
        public ?string $pattern = null,
        public bool $lockValues = false,
        public ?string $headerHint = null,
        public ?array $allowedValues = null,
        public bool $informational = false,
    ) {}

    /**
     * @param  list<string>  $allowedValues
     */
    public static function select(array $allowedValues, ?string $headerHint = null): self
    {
        return new self(
            ExportColumnFormat::Text,
            headerHint: $headerHint,
            allowedValues: array_values($allowedValues),
        );
    }

    public static function text(bool $lockValues = false, ?string $headerHint = null): self
    {
        return new self(ExportColumnFormat::Text, lockValues: $lockValues, headerHint: $headerHint);
    }

    /** Text column whose values cannot be edited in Excel export. */
    public static function lockedText(?string $headerHint = null): self
    {
        return new self(ExportColumnFormat::Text, lockValues: true, headerHint: $headerHint);
    }

    public static function integer(?string $headerHint = null): self
    {
        return new self(ExportColumnFormat::Integer, headerHint: $headerHint);
    }

    public static function decimal(int $decimals = 2, ?string $headerHint = null): self
    {
        return new self(ExportColumnFormat::Decimal, $decimals, headerHint: $headerHint);
    }

    public static function date(?string $headerHint = null): self
    {
        return new self(ExportColumnFormat::Date, headerHint: $headerHint);
    }

    public static function email(?string $headerHint = null): self
    {
        return new self(ExportColumnFormat::Email, headerHint: $headerHint);
    }

    public static function percentage(int $decimals = 2): self
    {
        return new self(ExportColumnFormat::Percentage, $decimals);
    }

    /**
     * @param  non-empty-string  $pattern  Excel number format code (e.g. "0.000", "@", "#,##0.00")
     */
    public static function pattern(string $pattern): self
    {
        return new self(ExportColumnFormat::Pattern, pattern: $pattern);
    }

    public static function ateco(?string $headerHint = null): self
    {
        return new self(
            ExportColumnFormat::Ateco,
            headerHint: $headerHint ?? 'Codice ATECO con punti, es. 11.11.11. Solo cifre e punti, senza lettere.',
        );
    }

    public static function cap(?string $headerHint = null): self
    {
        return new self(
            ExportColumnFormat::Cap,
            headerHint: $headerHint ?? 'CAP italiano di 5 cifre, es. 00100.',
        );
    }

    /**
     * Read-only column the import ignores: shown greyed out so nobody edits it
     * expecting an effect.
     */
    public static function info(string $headerHint): self
    {
        return new self(ExportColumnFormat::Text, lockValues: true, headerHint: $headerHint, informational: true);
    }

    public static function provincia(?string $headerHint = null): self
    {
        return new self(
            ExportColumnFormat::Provincia,
            headerHint: $headerHint ?? 'Sigla provincia di 2 lettere, es. RM.',
        );
    }

    /**
     * Excel number format code applied to the column cells.
     */
    public function excelFormatCode(): string
    {
        return match ($this->format) {
            ExportColumnFormat::Text,
            ExportColumnFormat::Email,
            ExportColumnFormat::Ateco,
            ExportColumnFormat::Provincia,
            ExportColumnFormat::Cap => '@',
            // Italian Excel displays dot thousands; import strips dots/commas from integers.
            ExportColumnFormat::Integer => '#,##0',
            // Italian Excel displays comma decimals; import maps comma to dot for parsing.
            ExportColumnFormat::Decimal => '#,##0.'.str_repeat('0', max(0, $this->decimals ?? 2)),
            ExportColumnFormat::Date => 'yyyy-mm-dd',
            ExportColumnFormat::Percentage => '0.'.str_repeat('0', max(0, $this->decimals ?? 2)).'%',
            ExportColumnFormat::Pattern => $this->pattern ?? '@',
        };
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Translate\TranslateImportService;
use Illuminate\Console\Command;

/**
 * Imports the frontend translations (frontend/lang/*.json) into the
 * translates table, overwriting the database with the files' content.
 */
class TranslationsImportCommand extends Command
{
    protected $signature = 'translations:import';

    protected $description = 'Import translations from frontend/lang/*.json into DB';

    public function handle(): int
    {
        $ok = TranslateImportService::importTranslations();

        if ($this->output->isVerbose()) {
            $this->components->info($ok
                ? 'Import completed (JSON -> DB overwrite).'
                : 'Import failed (see laravel.log).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

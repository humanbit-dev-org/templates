<?php

namespace App\Console\Commands;

use App\Services\Translate\TranslateImportService;
use App\Services\Translate\TranslateRuntimeTenantMetaService;
use Illuminate\Console\Command;

class TranslationsImportCommand extends Command
{
    protected $signature = 'translations:import';

    protected $description = 'Import translations from frontend/lang/*.json into DB, then update tenant metadata structure';

    public function handle(): int
    {
        $ok = TranslateImportService::importTranslations();

        if ($this->output->isVerbose()) {
            $this->components->info($ok
                ? 'Import completed (JSON -> DB overwrite). Running tenant structure sync...'
                : 'Import failed (see laravel.log).');
        }

        if (! $ok) {
            return self::FAILURE;
        }

        $sync = TranslateRuntimeTenantMetaService::syncRuntimeFiles();

        if ($this->output->isVerbose()) {
            $this->components->info("Tenant metadata sync completed ({$sync['annotations']} annotations on {$sync['files']} files).");
        }

        return self::SUCCESS;
    }
}

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
	protected $signature = "translations:import";

<<<<<<< HEAD
	protected $description = "Import translations from frontend/lang/*.json into DB, then update tenant metadata structure";
=======
    protected $description = 'Import translations from frontend/lang/*.json into DB';
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	public function handle(): int
	{
		$ok = TranslateImportService::importTranslations();

<<<<<<< HEAD
		if ($this->output->isVerbose()) {
			$this->components->info(
				$ok
					? "Import completed (JSON -> DB overwrite). Running tenant structure sync..."
					: "Import failed (see laravel.log)."
			);
		}

		if (!$ok) {
			return self::FAILURE;
		}

		$sync = TranslateRuntimeTenantMetaService::syncRuntimeFiles();

		if ($this->output->isVerbose()) {
			$this->components->info(
				"Tenant metadata sync completed ({$sync["annotations"]} annotations on {$sync["files"]} files)."
			);
		}

		return self::SUCCESS;
	}
=======
        if ($this->output->isVerbose()) {
            $this->components->info($ok
                ? 'Import completed (JSON -> DB overwrite).'
                : 'Import failed (see laravel.log).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}

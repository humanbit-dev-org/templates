<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\Database\MigrateFresh;
use App\Console\Commands\Database\MigrateSafeBackup;
use App\Console\Commands\Database\MigrateOverride;

Artisan::command("inspire", function () {
	$this->comment(Inspiring::quote());
})
	->purpose("Display an inspiring quote")
	->hourly();

// In produzione, blocchiamo completamente i comandi potenzialmente pericolosi
if (app()->environment('production')) {
    // Blocca migrate:fresh con un avviso
    Artisan::command('migrate:fresh', function() {
        $this->error('ERROR: For safety reasons, migrate:fresh is blocked in production.');
        $this->info('Use "php artisan migrate:fresh:safe --p=\"project_name\"" instead, which includes safety checks.');
        return 1;
    })->purpose('Blocked in production for safety. Use migrate:fresh:safe instead.');
    
    // Blocca anche il comando migrate standard
    Artisan::command('migrate', function() {
        $this->error('ERROR: For safety reasons, standard migrate is blocked in production.');
        $this->info('Use "php artisan migrate:safe" instead, which creates a backup before migration.');
        return 1;
    })->purpose('Blocked in production for safety. Use migrate:safe instead.');
}

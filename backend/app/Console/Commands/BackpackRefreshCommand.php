<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackpackRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backpack:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh assets files from backend/public/static/* to backend/storage/app/public/basset/public/static/*';

    /**
     * Source and destination paths for asset files (CSS and JS)
     */
    protected array $assetsMappings = [
        // CSS files
        'public/static/css/humanbit_colors.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/humanbit_color_adjustments.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/humanbit_datatables.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/humanbit_csv.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/dashboard_custom.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/humanbit_custom.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/logs_index.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/logs_show.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/csv_rollback.css' => 'storage/app/public/basset/public/static/css/',
        'public/static/css/humanbit_banking_ui.css' => 'storage/app/public/basset/public/static/css/',
        // JS files
        'public/static/js/humanbit_custom.js' => 'storage/app/public/basset/public/static/js/',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🎨 Backpack assets refresh tool');
        $this->line('');
        $this->info('Starting assets refresh process...');
        $this->line('');

        $successCount = 0;
        $errorCount = 0;

        foreach ($this->assetsMappings as $sourcePath => $destinationDir) {
            $result = $this->copyAssetsFiles($sourcePath, $destinationDir);

            if ($result['success']) {
                $successCount++;
                $this->line("✅ {$result['message']}");
            } else {
                $errorCount++;
                $this->error("❌ {$result['message']}");
            }
        }

        $this->line('');

        if ($errorCount === 0) {
            $this->info("🎉 All assets files refreshed successfully! ({$successCount} files)");
        } else {
            $this->warn("⚠️  Refresh completed with {$errorCount} errors. ({$successCount} files successful)");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Copy a CSS file from source to destination
     */
    private function copyAssetsFiles(string $sourcePath, string $destinationDir): array
    {
        $fullSourcePath = base_path($sourcePath);
        $fullDestinationDir = base_path($destinationDir);

        // Check if source file exists
        if (! File::exists($fullSourcePath)) {
            return [
                'success' => false,
                'message' => "Source file not found: {$sourcePath}",
            ];
        }

        // Ensure destination directory exists
        if (! File::exists($fullDestinationDir)) {
            File::makeDirectory($fullDestinationDir, 0755, true);
        }

        // Get filename from source path
        $filename = basename($fullSourcePath);
        $fullDestinationPath = $fullDestinationDir.$filename;

        try {
            // Remove existing file if it exists
            if (File::exists($fullDestinationPath)) {
                File::delete($fullDestinationPath);
            }

            // Copy the file
            File::copy($fullSourcePath, $fullDestinationPath);

            return [
                'success' => true,
                'message' => "Copied {$filename} to {$destinationDir}",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to copy {$filename}: ".$e->getMessage(),
            ];
        }
    }
}

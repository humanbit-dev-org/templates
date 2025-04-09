<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MigrateSafeBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a MySQL database backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating database backup...');
        
        // Get database connection details from config
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        // Check if we are using MySQL/MariaDB
        if (!in_array($driver, ['mysql', 'mariadb'])) {
            $this->error("This backup command only supports MySQL/MariaDB databases. Current driver: {$driver}");
            return Command::FAILURE;
        }
        
        // Check if backup directory exists, create if not
        $backupDir = storage_path('app/db-backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }
        
        // Generate backup filename with timestamp using the correct timezone
        // Force Europe/Rome timezone for Italian time
        $timezone = 'Europe/Rome';
        
        // Log the timezone being used
        $this->info("Using timezone: {$timezone}");
        
        // Create Carbon instance with explicit timezone
        $now = Carbon::now($timezone);
        $this->info("Current time: " . $now->format('Y-m-d H:i:s'));
        
        $timestamp = $now->format('Y-m-d_H-i-s');
        
        $projectName = env('APP_NAME', 'laravel');
        $safeProjectName = Str::slug($projectName);
        $filename = "{$safeProjectName}_{$timestamp}.sql";
        $backupPath = "{$backupDir}/{$filename}";
        
        try {
            // Create the backup
            $this->mysqlBackup($backupPath);
            
            $this->info("Database backup created successfully: {$backupPath}");
            
            // Create a compressed version if the file is large
            $fileSize = round(File::size($backupPath) / 1024 / 1024, 2);
            if ($fileSize > 5) { // If larger than 5MB
                $this->compressBackup($backupPath);
            }
            
            // Cleanup old backups (keep last 5)
            $this->cleanupOldBackups($backupDir);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Perform a MySQL/MariaDB database backup
     */
    protected function mysqlBackup($backupPath)
    {
        $connection = config('database.default');
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");
        
        // Check if mysqldump is available
        exec('which mysqldump', $output, $returnVar);
        
        if ($returnVar !== 0) {
            // mysqldump is not available, try to install it automatically
            $this->info("mysqldump not found. Attempting to install it automatically...");
            
            // Check if we can detect the OS and install the appropriate package
            if ($this->isDebianBased()) {
                $this->info("Detected Debian/Ubuntu based system. Installing mysql-client...");
                system('apt-get update && apt-get install -y default-mysql-client', $installResult);
                
                if ($installResult === 0) {
                    $this->info("Successfully installed mysqldump. Proceeding with backup.");
                } else {
                    return $this->handleMysqlDumpNotAvailable($backupPath);
                }
            } elseif ($this->isRedHatBased()) {
                $this->info("Detected RedHat/CentOS based system. Installing mysql-client...");
                system('yum install -y mysql', $installResult);
                
                if ($installResult === 0) {
                    $this->info("Successfully installed mysqldump. Proceeding with backup.");
                } else {
                    return $this->handleMysqlDumpNotAvailable($backupPath);
                }
            } else {
                return $this->handleMysqlDumpNotAvailable($backupPath);
            }
        }
        
        // Continue with mysqldump which should now be available
        $command = sprintf(
            'mysqldump -h %s -P %s -u %s %s --no-tablespaces',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database)
        );
        
        // Add password if it exists
        if (!empty($password)) {
            $command .= ' -p' . escapeshellarg($password);
        }
        
        // Redirect output to file
        $command .= ' > ' . escapeshellarg($backupPath);
        
        // Execute the command
        $returnVar = null;
        system($command, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \Exception("mysqldump command failed with code {$returnVar}");
        }
    }
    
    /**
     * Handle case when mysqldump is not available
     */
    protected function handleMysqlDumpNotAvailable($backupPath)
    {
        $this->warn("Could not install or find mysqldump.");
        
        if ($this->confirm('Do you want to proceed with a slower PHP-based backup instead?', true)) {
            $this->info("Using PHP alternative for database backup.");
            return $this->mysqlBackupWithPHP($backupPath);
        } else {
            throw new \Exception("Backup canceled: mysqldump not available and PHP alternative rejected.");
        }
    }
    
    /**
     * Check if we're on a Debian/Ubuntu based system
     */
    protected function isDebianBased()
    {
        if (file_exists('/etc/debian_version')) {
            return true;
        }
        
        exec('command -v apt-get', $output, $returnVar);
        return $returnVar === 0;
    }
    
    /**
     * Check if we're on a RedHat/CentOS based system
     */
    protected function isRedHatBased()
    {
        if (file_exists('/etc/redhat-release')) {
            return true;
        }
        
        exec('command -v yum', $output, $returnVar);
        return $returnVar === 0;
    }
    
    /**
     * Create a MySQL backup using PHP when mysqldump is not available
     */
    protected function mysqlBackupWithPHP($backupPath)
    {
        $connection = config('database.default');
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");
        
        try {
            // Connect to database
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", 
                $username, 
                $password, 
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Get all tables
            $tables = [];
            $result = $pdo->query("SHOW TABLES");
            while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            if (empty($tables)) {
                $this->info("No tables found in database. Creating empty backup file.");
                file_put_contents($backupPath, "-- No tables found in database {$database}\n");
                return;
            }
            
            $output = "-- Backup of database {$database} created via PHP PDO\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            // Process each table
            foreach ($tables as $table) {
                $this->info("Backing up table: {$table}");
                
                // Get create statement
                $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $row = $stmt->fetch(\PDO::FETCH_NUM);
                $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $output .= $row[1] . ";\n\n";
                
                // Get data
                $result = $pdo->query("SELECT * FROM `{$table}`");
                $numFields = $result->columnCount();
                
                while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                    $output .= "INSERT INTO `{$table}` VALUES (";
                    
                    for ($i = 0; $i < $numFields; $i++) {
                        if (isset($row[$i])) {
                            $row[$i] = addslashes($row[$i]);
                            $row[$i] = str_replace("\n", "\\n", $row[$i]);
                            $output .= '"' . $row[$i] . '"';
                        } else {
                            $output .= 'NULL';
                        }
                        
                        if ($i < ($numFields - 1)) {
                            $output .= ',';
                        }
                    }
                    
                    $output .= ");\n";
                }
                
                $output .= "\n\n";
                
                // Write to file in batches to avoid memory issues
                file_put_contents($backupPath, $output, FILE_APPEND);
                $output = '';
            }
            
            return true;
            
        } catch (\Exception $e) {
            throw new \Exception("PHP backup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Compress the backup file using gzip
     */
    protected function compressBackup($backupPath)
    {
        $this->info("Compressing backup file...");
        
        $command = "gzip -9 -f " . escapeshellarg($backupPath);
        $returnVar = null;
        system($command, $returnVar);
        
        if ($returnVar === 0) {
            $this->info("Backup compressed: {$backupPath}.gz");
        } else {
            $this->warn("Could not compress backup file (code {$returnVar})");
        }
    }
    
    /**
     * Delete old backups, keeping only the most recent ones
     */
    protected function cleanupOldBackups($backupDir, $keep = 5)
    {
        $backupFiles = collect(File::files($backupDir))
            ->sortByDesc(function ($file) {
                return $file->getMTime();
            });
        
        if ($backupFiles->count() > $keep) {
            $this->info("Cleaning up old backups...");
            
            $backupFiles->slice($keep)->each(function ($file) {
                File::delete($file->getPathname());
                $this->line("Deleted old backup: " . $file->getFilename());
            });
        }
    }
}

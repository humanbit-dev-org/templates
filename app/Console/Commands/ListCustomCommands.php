<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ListCustomCommands extends Command
{
    protected $signature = 'custom:list';
    protected $description = 'List all custom commands';

    /**
     * Comando che puoi chiamare con php artisan custom:list
     * 
     * Questo comando è molto utile quando hai molti comandi personalizzati
     * e vuoi vedere solo quelli che hai creato tu, non tutti quelli di Laravel
     */
    public function handle()
    {
        // Usage section
        $this->line("\033[33mUsage:\033[0m");
        $this->line('  command [options] [arguments]');
        $this->newLine();

        // Options section
        $this->line("\033[33mOptions:\033[0m");
        $this->line('  ' . "\033[32m" . '-h' . "\033[0m" . ', ' . "\033[32m" . '--help' . "\033[0m" . '            Display help for the given command. When no command is given display help for the list command');
        $this->line('  ' . "\033[32m" . '-q' . "\033[0m" . ', ' . "\033[32m" . '--quiet' . "\033[0m" . '           Do not output any message');
        $this->line('  ' . "\033[32m" . '-V' . "\033[0m" . ', ' . "\033[32m" . '--version' . "\033[0m" . '         Display this application version');
        $this->line('      ' . "\033[32m" . '--ansi' . "\033[0m" . '|' . "\033[32m" . '--no-ansi' . "\033[0m" . '  Force (or disable --no-ansi) ANSI output');
        $this->line('  ' . "\033[32m" . '-n' . "\033[0m" . ', ' . "\033[32m" . '--no-interaction' . "\033[0m" . '  Do not ask any interactive question');
        $this->line('      ' . "\033[32m" . '--env' . "\033[0m" . '[=ENV]       The environment the command should run under');
        $this->line('  ' . "\033[32m" . '-v' . "\033[0m" . '|' . "\033[32m" . 'vv' . "\033[0m" . '|' . "\033[32m" . 'vvv' . "\033[0m" . ', ' . "\033[32m" . '--verbose' . "\033[0m" . '  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug');
        $this->newLine();

        // Available commands section
        $this->line("\033[33mAvailable commands:\033[0m");
        
        // Find all custom commands
        $categories = $this->findCustomCommands();
        
        // Sort categories alphabetically
        ksort($categories);

        // Display commands grouped by category
        $isFirst = true;
        foreach ($categories as $category => $commands) {
            // Display the category name in yellow, lowercase with one space
            $this->line(" \033[33m" . strtolower($category) . "\033[0m");
            
            // Sort commands alphabetically
            usort($commands, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            foreach ($commands as $command) {
                // Display the command name in green with proper padding and two spaces
                $name = str_pad($command['name'], 30);
                $this->line("  \033[32m" . $name . "\033[0m " . $command['description']);
            }
        }
    }
    
    /**
     * Find all custom commands in the application
     *
     * @return array
     */
    protected function findCustomCommands()
    {
        $categories = [];
        $processedCommands = [];
        
        // Get namespace mappings from composer.json
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);
        $psr4 = $composerJson['autoload']['psr-4'] ?? [];
        
        // Main path for our custom commands (standard Laravel structure)
        $commandsNamespace = 'App\\Console\\Commands';
        $commandsPath = app_path('Console/Commands');
        
        // Find all command files
        $finder = new Finder();
        $finder->files()->in($commandsPath)->name('*.php');
        
        foreach ($finder as $file) {
            $relativePath = trim(str_replace($commandsPath, '', $file->getPathname()), '/\\');
            $relativeNamespace = str_replace('/', '\\', dirname($relativePath));
            
            // Skip the base folder where current command is located
            if (empty($relativeNamespace)) {
                // Only process this file if it's not our current command
                $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                if ($className === 'ListCustomCommands') {
                    continue;
                }
                
                $fullClassName = "{$commandsNamespace}\\{$className}";
            } else {
                $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $fullClassName = "{$commandsNamespace}\\{$relativeNamespace}\\{$className}";
            }
            
            // Check if the class exists and is a command
            if (class_exists($fullClassName)) {
                try {
                    $reflectionClass = new ReflectionClass($fullClassName);
                    
                    // Skip if not a command
                    if (!$reflectionClass->isSubclassOf(Command::class)) {
                        continue;
                    }
                    
                    // Skip abstract classes
                    if ($reflectionClass->isAbstract()) {
                        continue;
                    }
                    
                    // Get command signature
                    $signature = null;
                    
                    if ($reflectionClass->hasProperty('signature')) {
                        $property = $reflectionClass->getProperty('signature');
                        $property->setAccessible(true);
                        
                        // Create a new instance without calling the constructor
                        $instance = $reflectionClass->newInstanceWithoutConstructor();
                        $signature = $property->getValue($instance);
                    }
                    
                    if (!$signature) {
                        continue;
                    }
                    
                    // Extract command name from signature
                    $commandName = explode(' ', $signature)[0];
                    
                    // Skip if we've already processed this command
                    if (in_array($commandName, $processedCommands)) {
                        continue;
                    }
                    
                    $processedCommands[] = $commandName;
                    
                    // Get command description
                    $description = '';
                    if ($reflectionClass->hasProperty('description')) {
                        $property = $reflectionClass->getProperty('description');
                        $property->setAccessible(true);
                        
                        // Use the same instance
                        $description = $property->getValue($instance);
                    }
                    
                    // Extract category from command name
                    $parts = explode(':', $commandName);
                    $category = $parts[0];
                    
                    if (!isset($categories[$category])) {
                        $categories[$category] = [];
                    }
                    
                    $categories[$category][] = [
                        'name' => $commandName,
                        'description' => $description
                    ];
                } catch (\Throwable $e) {
                    // Silently skip problematic commands
                    continue;
                }
            }
        }
        
        return $categories;
    }
} 
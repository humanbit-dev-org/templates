<?php

namespace App\Console\Commands\Database;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;

/**
 * Class implementing a custom confirmation prompt with Laravel Prompts
 */
class ConfirmStyle extends SymfonyStyle
{
    /**
     * Constructor
     */
    public function __construct(InputInterface $input, OutputInterface $output)
    {
        parent::__construct($input, $output);
    }
    
    /**
     * Ask the user for confirmation
     * 
     * @param string $question Question to display
     * @param bool $default Default response (true = yes, false = no)
     * @return bool User's response
     */
    public function askConfirmation($question, bool $default = true)
    {
        // Add an empty line for spacing
        $this->newLine();
        
        // Use Laravel Prompts for the confirmation
        $result = confirm($question, $default);
        
        return $result;
    }
} 
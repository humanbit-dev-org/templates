<?php

namespace App\Console\Commands\Database;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\select;

/**
 * Class implementing a custom interactive menu for selection via arrow keys
 * Now using Laravel Prompts for modern, accessible terminal UIs
 */
class MenuStyle extends SymfonyStyle
{
	/**
	 * Maximum number of items to display at once (used only as a fallback)
	 */
	private $maxVisibleItems = 10;

	/**
	 * Constructor.
	 */
	public function __construct(InputInterface $input, OutputInterface $output)
	{
		parent::__construct($input, $output);
	}

	/**
	 * Display an interactive selection menu with arrow keys using Laravel Prompts
	 *
	 * @param string $question Question to display
	 * @param array $choices Available options (removes duplicates)
	 * @param int $default Default option index
	 * @return int Selected option index
	 */
	public function select($question, array $choices, int $default = 0)
	{
		// Remove duplicates from the choices array
		$choices = array_values(array_unique($choices, SORT_STRING));

		if (empty($choices)) {
			throw new \InvalidArgumentException("The options array cannot be empty.");
		}

		// Convert indexed array to associative with indexes as keys for Laravel Prompts
		$options = [];
		foreach ($choices as $index => $choice) {
			$options[$index] = $choice;
		}

		// Get default value from index
		$defaultValue = isset($choices[$default]) ? $default : array_key_first($options);

		// Use Laravel Prompts select function with original question
		$result = select(
			label: $question,
			options: $options,
			default: $defaultValue,
			scroll: min(count($options), $this->maxVisibleItems)
		);

		// Return the selected index
		return (int) $result;
	}
}

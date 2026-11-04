<?php

namespace App\Services\ScheduledJobs;

use App\Models\ScheduledJobRun;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Collection;

/**
 * The Scheduled Jobs page: what routes/console.php schedules, read from the
 * scheduler itself so there is no second list to keep in step, and which of
 * those jobs may be launched by hand.
 *
 * Only what is declared in runnable() can be launched, with the parameters
 * declared there: the page never takes a command line. A job without dry_run
 * has no side-effect-free mode, and the page offers only the real run; a
 * rolled-back transaction would not undo files moved on SFTP, queued jobs or
 * uploads, so dry runs are each command's own --dry-run, or the dryRun()
 * method of a scheduled job class.
 */
class ScheduledJobRegistry
{
<<<<<<< HEAD
	/**
	 * Jobs that can be launched from the page, keyed like the schedule rows
	 * (Artisan command name, or job class for Schedule::job).
	 *
	 * @return array<string, array{title: string, description: string, icon: string, dry_run: bool, job?: class-string, arguments?: array<string, mixed>, parameters?: array<string, array{label: string, type: string, rule: string, default?: mixed}>}>
	 */
	public function runnable(): array
	{
		return [
			"sftp:processa" => [
				"title" => "SFTP - Elaborazione file banche",
				"description" =>
					"Sposta i file delle banche da inbox/ a processing/ e accoda il job registrato per banca e operazione.",
				"icon" => "la la-folder-open",
				"dry_run" => true,
				"parameters" => [
					"percorso" => [
						"label" => "File in inbox (vuoto = tutte le inbox)",
						"type" => "text",
						"rule" => "nullable|string|max:500",
					],
				],
			],
			"export:selection-file" => [
				"title" => "Export - Selection file mensile",
				"description" =>
					"Genera il CSV dei clienti IN TARGET, lo cifra e lo carica su S3 per il trasferimento SFTP.",
				"icon" => "la la-file-export",
				"dry_run" => true,
				"parameters" => [
					"--banca" => ["label" => "ABI banca (vuoto = tutte)", "type" => "text", "rule" => "nullable|digits:5"],
				],
			],
			"auth:rehash-passwords" => [
				"title" => "Auth - Rehash password vuote",
				"description" => "Deriva e salva la password degli utenti che non ne hanno una, con un batch su Horizon.",
				"icon" => "la la-key",
				"dry_run" => true,
				"arguments" => ["--only-empty" => true, "--no-wait" => true],
			],
			"auth:cancel-stuck-rehash-batches" => [
				"title" => "Auth - Annulla batch di rehash bloccati",
				"description" => "Annulla i batch di auth:rehash-passwords non terminati più vecchi della soglia.",
				"icon" => "la la-ban",
				"dry_run" => true,
				"parameters" => [
					"--hours" => [
						"label" => "Età minima (ore)",
						"type" => "number",
						"rule" => "required|integer|min:1",
						"default" => 24,
					],
				],
			],
			CheckNewProiezioni::class => [
				"title" => "Proiezioni - Controllo nuovi tiraggi",
				"description" => "Se ci sono tiraggi accettati senza proiezioni, accoda il loro inserimento.",
				"icon" => "la la-chart-line",
				// No --dry-run on a job: its dryRun() method instead.
				"dry_run" => true,
				"job" => CheckNewProiezioni::class,
			],
		];
	}
=======
    /**
     * Jobs that can be launched from the page, keyed like the schedule rows
     * (Artisan command name, or job class for Schedule::job).
     *
     * @return array<string, array{title: string, description: string, icon: string, dry_run: bool, job?: class-string, arguments?: array<string, mixed>, parameters?: array<string, array{label: string, type: string, rule: string, default?: mixed}>}>
     */
    public function runnable(): array
    {
        return [
            'auth:rehash-passwords' => [
                'title' => 'Auth - Rehash password vuote',
                'description' => 'Deriva e salva la password degli utenti che non ne hanno una, con un batch su Horizon.',
                'icon' => 'la la-key',
                'dry_run' => true,
                'arguments' => ['--only-empty' => true, '--no-wait' => true],
            ],
            'auth:cancel-stuck-rehash-batches' => [
                'title' => 'Auth - Annulla batch di rehash bloccati',
                'description' => 'Annulla i batch di auth:rehash-passwords non terminati più vecchi della soglia.',
                'icon' => 'la la-ban',
                'dry_run' => true,
                'parameters' => [
                    '--hours' => ['label' => 'Età minima (ore)', 'type' => 'number', 'rule' => 'required|integer|min:1', 'default' => 24],
                ],
            ],
        ];
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	/**
	 * @return array{title: string, description: string, icon: string, dry_run: bool, job?: class-string, arguments?: array<string, mixed>, parameters?: array<string, array{label: string, type: string, rule: string, default?: mixed}>}|null
	 */
	public function definition(string $name): ?array
	{
		return $this->runnable()[$name] ?? null;
	}

	/**
	 * Every scheduled event, with its runnable definition (if any) and last run.
	 *
	 * @return Collection<int, array{name: string, expression: string, next_run: CarbonInterface, without_overlapping: bool, on_one_server: bool, definition: array|null, last_run: ScheduledJobRun|null}>
	 */
	public function scheduled(): Collection
	{
		$rows = collect($this->events())->map(
			fn(Event $event) => [
				"name" => self::nameOf($event),
				"expression" => $event->expression,
				"next_run" => $event->nextRunDate()->setTimezone(config("app.timezone")),
				"without_overlapping" => (bool) $event->withoutOverlapping,
				"on_one_server" => (bool) $event->onOneServer,
			]
		);

		$lastRuns = ScheduledJobRun::query()
			->whereIn(
				"id",
				ScheduledJobRun::query()->selectRaw("MAX(id)")->whereIn("name", $rows->pluck("name"))->groupBy("name")
			)
			->get()
			->keyBy("name");

		return $rows
			->map(
				fn(array $row) => $row + [
					"definition" => $this->definition($row["name"]),
					"last_run" => $lastRuns->get($row["name"]),
				]
			)
			->values();
	}

	public function event(string $name): ?Event
	{
		foreach ($this->events() as $event) {
			if (self::nameOf($event) === $name) {
				return $event;
			}
		}

		return null;
	}

	/**
	 * The command line a run stands for, as shown on the page.
	 *
	 * @param  array<string, mixed>  $parameters
	 */
	public function commandLine(string $name, array $parameters, bool $dryRun): string
	{
		$definition = $this->definition($name);

		if (isset($definition["job"])) {
			return ($dryRun ? "dryRun " : "dispatch ") . class_basename($definition["job"]);
		}

		$parts = [$name];

		foreach ($this->arguments($name, $parameters, $dryRun) as $key => $value) {
			$parts[] = match (true) {
				!str_starts_with($key, "--") => (string) $value,
				$value === true => $key,
				default => "{$key}={$value}",
			};
		}

		return implode(" ", $parts);
	}

	/**
	 * Artisan::call arguments: the fixed ones of the definition, the declared
	 * parameters that were filled in, and --dry-run.
	 *
	 * @param  array<string, mixed>  $parameters
	 * @return array<string, mixed>
	 */
	public function arguments(string $name, array $parameters, bool $dryRun): array
	{
		$definition = $this->definition($name) ?? [];
		$declared = array_keys($definition["parameters"] ?? []);

		$filled = array_filter(
			array_intersect_key($parameters, array_flip($declared)),
			fn($value) => $value !== null && $value !== ""
		);

		return array_merge($definition["arguments"] ?? [], $filled, $dryRun ? ["--dry-run" => true] : []);
	}

	/**
	 * The cron expression in words, for the shapes routes/console.php uses;
	 * anything else is shown as it is.
	 */
	public static function describe(string $expression): string
	{
		$days = ["domenica", "lunedì", "martedì", "mercoledì", "giovedì", "venerdì", "sabato"];
		$at = fn(string $hour, string $minute) => sprintf("%02d:%02d", (int) $hour, (int) $minute);

		return match (true) {
			preg_match('#^\*/(\d+) \* \* \* \*$#', $expression, $m) === 1 => "Ogni {$m[1]} minuti",
			preg_match('#^(\d+) \* \* \* \*$#', $expression, $m) === 1 => (int) $m[1] === 0
				? "Ogni ora"
				: "Ogni ora al minuto {$m[1]}",
			preg_match('#^(\d+) (\d+) \* \* \*$#', $expression, $m) === 1 => "Ogni giorno alle " . $at($m[2], $m[1]),
			preg_match('#^(\d+) (\d+) \* \* ([0-6])$#', $expression, $m) === 1 => "Ogni " .
				$days[(int) $m[3]] .
				" alle " .
				$at($m[2], $m[1]),
			preg_match('#^(\d+) (\d+) (\d+) \* \*$#', $expression, $m) === 1 => "Il giorno {$m[3]} del mese alle " .
				$at($m[2], $m[1]),
			default => $expression,
		};
	}

	/**
	 * The key a scheduled event is known by: the Artisan command name, or the
	 * job class Schedule::job puts in the description.
	 */
	public static function nameOf(Event $event): string
	{
		if ($event instanceof CallbackEvent) {
			return (string) $event->description;
		}

<<<<<<< HEAD
		// '/usr/local/bin/php' 'artisan' sftp:processa --flag → sftp:processa
		if (preg_match("/'?artisan'?\s+([^\s]+)/", (string) $event->command, $match) === 1) {
			return $match[1];
		}
=======
        // '/usr/local/bin/php' 'artisan' auth:rehash-passwords --flag → auth:rehash-passwords
        if (preg_match("/'?artisan'?\s+([^\s]+)/", (string) $event->command, $match) === 1) {
            return $match[1];
        }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		return (string) $event->command;
	}

	/**
	 * routes/console.php is loaded by the console kernel: on a web request
	 * the schedule stays empty until that kernel has been bootstrapped.
	 *
	 * @return list<Event>
	 */
	private function events(): array
	{
		app(ConsoleKernel::class)->bootstrap();

		return app(Schedule::class)->events();
	}
}

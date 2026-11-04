<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Concerns\ChecksBackpackPermissions;
use App\Http\Controllers\Controller;
use App\Jobs\RunScheduledCommand;
use App\Models\ScheduledJobRun;
use App\Services\ScheduledJobs\ScheduledJobRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Tools → Scheduled Jobs: the schedule, its run history, and manual runs (dry
 * or real) of the jobs ScheduledJobRegistry declares runnable. Developers only,
 * like the rest of the Tools menu, and only while config/scheduler_monitor.php
 * has it enabled.
 */
class ScheduledJobsController extends Controller
{
	use ChecksBackpackPermissions;

	public function __construct(private ScheduledJobRegistry $registry) {}

	public function index(): View
	{
		$this->authorizeDeveloper();

		$scheduled = $this->registry->scheduled();

		// System jobs keep only their last run on the page: horizon:snapshot
		// alone runs every five minutes and would bury every other row.
		$runs = ScheduledJobRun::query()
			->whereIn("name", array_keys($this->registry->runnable()))
			->latest("id")
			->limit(50)
			->get();

		return view("admin.scheduled-jobs.index", [
			"runnable" => $scheduled->whereNotNull("definition")->values(),
			"system" => $scheduled->whereNull("definition")->values(),
			"runs" => $runs,
			"registry" => $this->registry,
			"pending" => $runs->contains(fn(ScheduledJobRun $run) => $run->isPending()),
		]);
	}

	public function show(ScheduledJobRun $run): View
	{
		$this->authorizeDeveloper();

		return view("admin.scheduled-jobs.show", [
			"run" => $run,
			"definition" => $this->registry->definition($run->name),
			"commandLine" =>
				$run->trigger === ScheduledJobRun::TRIGGER_MANUAL
					? $this->registry->commandLine($run->name, $run->parameters ?? [], $run->dry_run)
					: null,
		]);
	}

	public function run(Request $request): RedirectResponse
	{
		$this->authorizeDeveloper();

		$name = (string) $request->input("name");
		$definition = $this->registry->definition($name);
		$dryRun = $request->boolean("dry_run");

		if ($definition === null) {
			return $this->back("Job non lanciabile da questa pagina.");
		}

		if ($dryRun && !$definition["dry_run"]) {
			return $this->back("{$definition["title"]}: dry run non disponibile.");
		}

		$rules = collect($definition["parameters"] ?? [])
			->mapWithKeys(fn(array $parameter, string $key) => ["params.{$key}" => $parameter["rule"]])
			->all();

		$validator = Validator::make($request->only("params"), $rules);

		if ($validator->fails()) {
			return $this->back($definition["title"] . ": " . $validator->errors()->first());
		}

		$alreadyPending = ScheduledJobRun::query()
			->where("name", $name)
			->whereIn("status", [ScheduledJobRun::STATUS_QUEUED, ScheduledJobRun::STATUS_RUNNING])
			->exists();

		if ($alreadyPending) {
			return $this->back("{$definition["title"]}: c'è già un'esecuzione in corso.");
		}

		// Only the declared parameters survive validated(); blanks mean "not given".
		$parameters = array_filter(
			(array) data_get($validator->validated(), "params", []),
			fn($value) => $value !== null && $value !== ""
		);

		$user = backpack_user();

		$run = ScheduledJobRun::create([
			"name" => $name,
			"trigger" => ScheduledJobRun::TRIGGER_MANUAL,
			"dry_run" => $dryRun,
			"status" => ScheduledJobRun::STATUS_QUEUED,
			"parameters" => $parameters ?: null,
			"triggered_by" => $user?->email ?? $user?->name,
		]);

		RunScheduledCommand::dispatch($run->id);

		return redirect()
			->route("scheduled-jobs.show", $run)
			->with("success", ($dryRun ? "Dry run" : "Esecuzione") . " accodata.");
	}

	private function authorizeDeveloper(): void
	{
		// Checked per request rather than by leaving the routes out: switching
		// the monitor needs no route cache rebuild.
		abort_unless(config("scheduler_monitor.enabled"), 404);
		abort_unless(self::isDeveloper(), 403);
	}

	private function back(string $error): RedirectResponse
	{
		return redirect()->route("scheduled-jobs.index")->with("error", $error);
	}
}

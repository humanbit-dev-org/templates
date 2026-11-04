<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Concerns\ChecksBackpackPermissions;
use App\Http\Controllers\Controller;
use App\Services\Audit\DependencyAudit;
use Illuminate\View\View;

/**
 * Tools → Dependency Audit: the report CI bakes into the image, plus the
 * versions read live. Read-only. Developers only, like the rest of the Tools
 * menu, and only while config/dependency_audit.php has it enabled.
 */
class DependencyAuditController extends Controller
{
	use ChecksBackpackPermissions;

	public function __construct(private DependencyAudit $audit) {}

	public function index(): View
	{
		abort_unless(config("dependency_audit.enabled"), 404);
		abort_unless(self::isDeveloper(), 403);

		$report = $this->audit->report();

		return view("admin.dependency-audit.index", [
			"report" => $report,
			"counts" => $report ? $this->audit->severityCounts($report) : null,
			"vulnerabilities" => $report ? $this->audit->vulnerabilities($report) : [],
			"outdated" => $report ? $this->audit->outdated($report) : [],
			// Not "errors": Blade reserves it for the validation error bag.
			"reportErrors" => $report ? $this->audit->errors($report) : [],
			"runtimes" => $this->audit->runtimes($report),
		]);
	}
}

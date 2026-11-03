<?php

namespace App\Services\Audit;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Reads the dependency audit report CI bakes into the image
 * (resources/audit/report.json, see .github/scripts/audit-report.sh) and
 * shapes it for the Dependency Audit page.
 *
 * Versions the backend can see are read live (PHP, Laravel, MySQL or Aurora, Valkey):
 * they may differ per environment. Node, Next.js and React live in the
 * frontend image, so they come from the report. Every version is matched
 * against the end-of-life tables the report carries; nothing here calls out.
 */
class DependencyAudit
{
    /** Most severe first: the order of the page and of the summary. */
    public const SEVERITIES = ['critical', 'high', 'medium', 'low', 'info', 'unknown'];

    /** An end of life closer than this is flagged as ending. */
    private const ENDING_MONTHS = 6;

    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? resource_path('audit/report.json');
    }

    /**
     * The report, or null when the image carries none (built before the audit
     * existed, or generated locally without running the script).
     *
     * @return array<string, mixed>|null
     */
    public function report(): ?array
    {
        if (! is_file($this->path())) {
            return null;
        }

        $report = json_decode((string) file_get_contents($this->path()), true);

        return is_array($report) ? $report : null;
    }

    /**
     * Both ecosystems in one list, most severe first.
     *
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    public function vulnerabilities(array $report): array
    {
        $items = array_map(
            fn (array $item) => $item + ['command' => self::fixCommand($item)],
            $this->merge($report['vulnerabilities'] ?? []),
        );

        usort($items, fn (array $a, array $b) => $this->severityRank($a['severity'] ?? null) <=> $this->severityRank($b['severity'] ?? null)
            ?: strcmp((string) $a['package'], (string) $b['package']));

        return $items;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, int> severity => count, every severity present
     */
    public function severityCounts(array $report): array
    {
        $counts = array_fill_keys(self::SEVERITIES, 0);

        foreach ($this->vulnerabilities($report) as $item) {
            $counts[in_array($item['severity'] ?? null, self::SEVERITIES, true) ? $item['severity'] : 'unknown']++;
        }

        return $counts;
    }

    /**
     * Direct dependencies with a newer major, both ecosystems.
     *
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    public function outdated(array $report): array
    {
        $items = array_map(
            fn (array $item) => $item + ['command' => self::updateCommand($item)],
            $this->merge($report['outdated'] ?? []),
        );

        usort($items, fn (array $a, array $b) => strcmp($a['ecosystem'], $b['ecosystem']) ?: strcmp((string) $a['package'], (string) $b['package']));

        return $items;
    }

    /**
     * What could not be checked: sections whose tool failed, or the whole
     * report when the script could not assemble it.
     *
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    public function errors(array $report): array
    {
        $errors = [];

        if (is_string($report['error'] ?? null)) {
            $errors[] = $report['error'];
        }

        $labels = ['vulnerabilities' => 'Vulnerabilità', 'outdated' => 'Aggiornamenti major'];

        foreach ($labels as $section => $label) {
            foreach (['php' => 'PHP', 'js' => 'frontend'] as $ecosystem => $name) {
                if (is_string($report[$section][$ecosystem]['error'] ?? null)) {
                    $errors[] = "{$label} {$name}: {$report[$section][$ecosystem]['error']}";
                }
            }
        }

        if (is_string($report['eol']['error'] ?? null)) {
            $errors[] = 'Fine supporto: '.$report['eol']['error'];
        }

        return $errors;
    }

    /**
     * One row per runtime: its version, where it was read, the matching
     * end-of-life cycle and the resulting status.
     *
     * @param  array<string, mixed>|null  $report
     * @return list<array{name: string, version: ?string, note: ?string, source: string, cycle: ?array, status: string}>
     */
    public function runtimes(?array $report, ?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $products = $report['eol']['products'] ?? [];

        // Each row: name, version, source, end-of-life product, note shown next to the version.
        $rows = [
            ['PHP', PHP_VERSION, 'live', 'php', null],
            ['Laravel', app()->version(), 'live', 'laravel', null],
            $this->databaseRow(),
            // Valkey / Redis: AWS-managed, no end-of-life table to match.
            ['Valkey / Redis', $this->redisVersion(), 'live', null, null],
            ['Node.js', $report['versions']['node'] ?? null, 'build', 'nodejs', null],
            ['Next.js', $report['versions']['next'] ?? null, 'build', 'nextjs', null],
            ['React', $report['versions']['react'] ?? null, 'build', 'react', null],
        ];

        return array_map(function (array $row) use ($products, $today) {
            [$name, $version, $source, $product, $note] = $row;
            $cycle = ($product !== null && $version !== null) ? $this->matchCycle($products[$product] ?? [], $version) : null;

            return [
                'name' => $name,
                'version' => $version,
                'note' => $note,
                'source' => $source,
                'cycle' => $cycle,
                'status' => self::supportStatus($cycle, $today),
            ];
        }, $rows);
    }

    /**
     * ok | security (active support over, still patched) | ending (end of life
     * within six months) | eol | unknown. endoflife.date gives a date or a
     * boolean for both fields: eol false means no end announced.
     *
     * @param  array<string, mixed>|null  $cycle
     */
    public static function supportStatus(?array $cycle, CarbonInterface $today): string
    {
        if ($cycle === null) {
            return 'unknown';
        }

        // Immutable copy: addMonths() below must not move the caller's date.
        $today = CarbonImmutable::instance($today);
        $eol = $cycle['eol'] ?? null;
        $support = $cycle['support'] ?? null;

        if ($eol === true || (is_string($eol) && $today->gte(CarbonImmutable::parse($eol)))) {
            return 'eol';
        }

        if (is_string($eol) && $today->addMonths(self::ENDING_MONTHS)->gte(CarbonImmutable::parse($eol))) {
            return 'ending';
        }

        if ($support === false || (is_string($support) && $today->gte(CarbonImmutable::parse($support)))) {
            return 'security';
        }

        return 'ok';
    }

    /**
     * The command that fixes a vulnerability, run from backend/ (Composer) or
     * frontend/ (pnpm); null when no fix is published.
     *
     * - fix in the installed major: update it in place (a direct dependency
     *   or one pulled in by another package alike);
     * - fix only in another major: require it if it is a direct dependency,
     *   otherwise "why" shows which package has to move first;
     * - fix not worked out: update in place, or "why" for a frontend package
     *   that is not a direct dependency.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fixCommand(array $item): ?string
    {
        $package = (string) ($item['package'] ?? '');
        $php = ($item['ecosystem'] ?? 'PHP') === 'PHP';
        $direct = (bool) ($item['direct'] ?? false);
        $dev = (bool) ($item['dev'] ?? false);
        $status = $item['fixed_status'] ?? (! empty($item['fixed']) ? 'available' : 'unknown');

        if ($status === 'none' || $package === '') {
            return null;
        }

        if ($php) {
            $fixed = (string) ($item['fixed'] ?? '');

            if ($status === 'unknown' || self::major($fixed) === self::major((string) ($item['installed'] ?? ''))) {
                return "composer update {$package} --with-all-dependencies";
            }

            return $direct
                ? 'composer require '.($dev ? '--dev ' : '')."{$package}:".self::caret($fixed)
                : "composer why {$package}";
        }

        if (! $direct) {
            return "pnpm why {$package}";
        }

        // npm states the fix as a range (">=4.3.2"): its lower bound is the version to add.
        $fixed = preg_match('/>=\s*v?(\d+(?:\.\d+){0,2})/', (string) ($item['fixed'] ?? ''), $match) === 1 ? $match[1] : null;

        return $fixed !== null
            ? 'pnpm add '.($dev ? '-D ' : '')."{$package}@".self::caret($fixed)
            : "pnpm update {$package}";
    }

    /**
     * The command that moves a direct dependency to its latest major.
     *
     * @param  array<string, mixed>  $item
     */
    public static function updateCommand(array $item): ?string
    {
        $package = (string) ($item['package'] ?? '');
        $latest = (string) ($item['latest'] ?? '');

        if ($package === '' || $latest === '') {
            return null;
        }

        $dev = (bool) ($item['dev'] ?? false);

        return ($item['ecosystem'] ?? 'PHP') === 'PHP'
            ? 'composer require '.($dev ? '--dev ' : '')."{$package}:".self::caret($latest)
            : 'pnpm add '.($dev ? '-D ' : '')."{$package}@".self::caret($latest);
    }

    /** "v3.0.2" → "^3.0", "13.4.5" → "^13.4": the constraint the command writes. */
    private static function caret(string $version): string
    {
        $parts = explode('.', ltrim($version, 'v'));

        return '^'.$parts[0].'.'.($parts[1] ?? '0');
    }

    private static function major(string $version): ?int
    {
        return preg_match('/^v?(\d+)/', $version, $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * The cycle a version belongs to: "8.4.27" → "8.4", "26.13.0" → "26".
     * The longest match wins, so "8.4" is preferred over a "8" cycle.
     *
     * @param  list<array<string, mixed>>  $cycles
     * @return array<string, mixed>|null
     */
    private function matchCycle(array $cycles, string $version): ?array
    {
        $match = null;

        foreach ($cycles as $cycle) {
            $name = (string) ($cycle['cycle'] ?? '');

            if ($name !== '' && ($version === $name || str_starts_with($version, $name.'.'))
                && ($match === null || strlen($name) > strlen((string) $match['cycle']))) {
                $match = $cycle;
            }
        }

        return $match;
    }

    /**
     * @param  array<string, array{error?: ?string, items?: list<array<string, mixed>>}>  $sections
     * @return list<array<string, mixed>>
     */
    private function merge(array $sections): array
    {
        $items = [];

        foreach (['php' => 'PHP', 'js' => 'Frontend'] as $key => $ecosystem) {
            foreach ($sections[$key]['items'] ?? [] as $item) {
                $items[] = $item + ['ecosystem' => $ecosystem];
            }
        }

        return $items;
    }

    private function severityRank(?string $severity): int
    {
        $rank = array_search($severity, self::SEVERITIES, true);

        return $rank === false ? count(self::SEVERITIES) : $rank;
    }

    /**
     * The database row. On Aurora, version() gives the MySQL version it is
     * compatible with ("8.0.42" on Aurora MySQL 3), whose community end of life
     * does not apply: AWS supports Aurora on its own schedule. So the row shows
     * the Aurora version, matched against Aurora's cycles, and keeps the MySQL
     * one as a note. Any other server is MySQL, as read.
     *
     * @return array{0: string, 1: ?string, 2: string, 3: string, 4: ?string}
     */
    private function databaseRow(): array
    {
        $versions = $this->databaseVersions();
        // The leading x.y.z only: "8.4.6" locally, "8.0.42" on Aurora MySQL 3.
        $mysql = preg_match('/^\d+\.\d+(\.\d+)?/', (string) $versions['version'], $match) === 1 ? $match[0] : null;

        if ($versions['aurora'] !== null) {
            return ['Aurora MySQL', $versions['aurora'], 'live', 'amazon-aurora-mysql', $mysql !== null ? "compatibile MySQL {$mysql}" : null];
        }

        return ['MySQL', $mysql, 'live', 'mysql', null];
    }

    /**
     * What the database says of itself: version(), and @@aurora_version, which
     * only Aurora knows (MySQL community refuses it). Null when not readable.
     *
     * @return array{version: ?string, aurora: ?string}
     */
    protected function databaseVersions(): array
    {
        try {
            $version = DB::selectOne('select version() as version')->version ?? null;
        } catch (Throwable) {
            return ['version' => null, 'aurora' => null];
        }

        try {
            $aurora = DB::selectOne('select @@aurora_version as aurora')->aurora ?? null;
        } catch (Throwable) {
            $aurora = null;
        }

        return [
            'version' => is_string($version) ? $version : null,
            'aurora' => is_string($aurora) && $aurora !== '' ? $aurora : null,
        ];
    }

    /**
     * Best effort: INFO may be refused, or need a node on a cluster
     * connection (Valkey Serverless). Then the page shows it as unknown.
     */
    private function redisVersion(): ?string
    {
        try {
            $info = Redis::connection()->info('server');
        } catch (Throwable) {
            return null;
        }

        $server = $info['Server'] ?? $info;

        return $server['valkey_version'] ?? $server['redis_version'] ?? null;
    }
}

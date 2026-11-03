<?php

/**
 * Assembles the Dependency Audit report (schema 1) from the tool outputs that
 * audit-report.sh leaves in the working directory, and prints it as JSON.
 *
 * Plain PHP, no framework: it runs inside the composer image. A section whose
 * tool failed carries an "error" instead of items, so the page tells "not
 * checked" apart from "nothing found".
 */

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

/** endoflife.date products the page matches versions against. */
const EOL_PRODUCTS = ['php', 'nodejs', 'laravel', 'nextjs', 'react', 'mysql', 'amazon-aurora-mysql'];

function readJson(string $file): ?array
{
    if (! is_file($file)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($file), true);

    return is_array($data) ? $data : null;
}

/** Why a tool left no usable output: its stderr, or a plain note. */
function failure(string $file): string
{
    $stderr = is_file("{$file}.err") ? trim((string) file_get_contents("{$file}.err")) : '';

    return $stderr !== '' ? mb_substr($stderr, 0, 500) : "{$file}: no output";
}

/** One scale for both ecosystems: npm says "moderate" where Composer says "medium". */
function severity(?string $severity): string
{
    $severity = strtolower((string) $severity);

    return match ($severity) {
        'moderate', 'medium' => 'medium',
        'info', 'low', 'high', 'critical' => $severity,
        default => 'unknown',
    };
}

function major(string $version): ?int
{
    return preg_match('/^v?(\d+)/', $version, $match) === 1 ? (int) $match[1] : null;
}

/** Version a direct frontend dependency is locked at, from pnpm-lock importers. */
function lockedVersion(string $lockfile, string $package): ?string
{
    $name = preg_quote($package, '/');

    return preg_match("/^      {$name}:\\n        specifier: [^\\n]+\\n        version: ([0-9][^\\s(]*)/m", $lockfile, $match) === 1
        ? $match[1]
        : null;
}

/**
 * composer/semver out of the Composer phar of the composer image: the same
 * constraint logic composer audit applies, with nothing installed.
 */
function loadSemver(): bool
{
    try {
        Phar::loadPhar('/usr/bin/composer', 'composer.phar');
        require_once 'phar://composer.phar/vendor/autoload.php';
    } catch (Throwable) {
        return false;
    }

    return class_exists(Semver::class);
}

/**
 * Stable versions packagist publishes for a package, or null when it did not answer.
 *
 * @return list<string>|null
 */
function packagistVersions(string $package, $context): ?array
{
    $data = json_decode((string) @file_get_contents("https://repo.packagist.org/p2/{$package}.json", false, $context), true);
    $entries = $data['packages'][$package] ?? null;

    if (! is_array($entries)) {
        return null;
    }

    $versions = [];

    foreach ($entries as $entry) {
        $version = $entry['version'] ?? null;

        if (is_string($version) && VersionParser::parseStability($version) === 'stable') {
            $versions[] = $version;
        }
    }

    return $versions;
}

/**
 * The version that fixes an advisory: the lowest published stable version
 * above the installed one that falls in no affected range. Same major first
 * (an update, not a migration), any major when that line has no fix.
 *
 * @param  list<string>|null  $published
 * @return array{0: ?string, 1: string} [version, available | none | unknown]
 */
function fixedVersion(?string $installed, ?string $affected, ?array $published, bool $semver): array
{
    if (! $semver || $installed === null || $affected === null || $published === null) {
        return [null, 'unknown'];
    }

    try {
        $candidates = array_values(array_filter(
            $published,
            fn (string $version) => Comparator::greaterThan($version, $installed) && ! Semver::satisfies($version, $affected),
        ));
        $candidates = Semver::sort($candidates);
    } catch (Throwable) {
        return [null, 'unknown'];
    }

    if ($candidates === []) {
        return [null, 'none'];
    }

    $sameMajor = array_values(array_filter($candidates, fn (string $version) => major($version) === major($installed)));

    return [$sameMajor[0] ?? $candidates[0], 'available'];
}

$context = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'rays-dependency-audit']]);
$semver = loadSemver();

// ---- PHP dependencies ----

$composerLock = readJson('composer.lock') ?? [];
$composerJson = readJson('composer.json') ?? [];
$installed = [];
$devPackages = [];

foreach ($composerLock['packages'] ?? [] as $package) {
    $installed[$package['name']] = $package['version'];
}
foreach ($composerLock['packages-dev'] ?? [] as $package) {
    $installed[$package['name']] = $package['version'];
    $devPackages[$package['name']] = true;
}

// Direct dependencies: the page tells "update it" from "update what pulls it in".
$phpDirect = array_keys(($composerJson['require'] ?? []) + ($composerJson['require-dev'] ?? []));
$packageJson = readJson('package.json') ?? [];
$jsDirect = array_keys(($packageJson['dependencies'] ?? []) + ($packageJson['devDependencies'] ?? []));

$composerAudit = readJson('composer-audit.json');
$phpVulnerabilities = ['error' => $composerAudit === null ? failure('composer-audit.json') : null, 'items' => []];

$published = [];

foreach ($composerAudit['advisories'] ?? [] as $package => $advisories) {
    // One packagist lookup per package, however many advisories it has.
    $published[$package] ??= packagistVersions($package, $context);

    foreach ($advisories as $advisory) {
        [$fixed, $fixedStatus] = fixedVersion($installed[$package] ?? null, $advisory['affectedVersions'] ?? null, $published[$package], $semver);

        $phpVulnerabilities['items'][] = [
            'package' => $package,
            'installed' => $installed[$package] ?? null,
            'severity' => severity($advisory['severity'] ?? null),
            'title' => $advisory['title'] ?? '',
            'cve' => $advisory['cve'] ?? null,
            'url' => $advisory['link'] ?? null,
            'affected' => $advisory['affectedVersions'] ?? null,
            // Composer advisories list the affected ranges only: the fix is
            // worked out from the versions packagist publishes.
            'fixed' => $fixed,
            'fixed_status' => $fixedStatus,
            'dev' => isset($devPackages[$package]),
            'direct' => in_array($package, $phpDirect, true),
        ];
    }
}

$composerOutdated = readJson('composer-outdated.json');
$phpOutdated = ['error' => $composerOutdated === null ? failure('composer-outdated.json') : null, 'items' => []];

foreach ($composerOutdated['locked'] ?? [] as $package) {
    $phpOutdated['items'][] = [
        'package' => $package['name'],
        'current' => $package['version'],
        'latest' => $package['latest'] ?? null,
        'dev' => array_key_exists($package['name'], $composerJson['require-dev'] ?? []),
        // Composer gives the replacement's name, or true when there is none.
        'abandoned' => is_string($package['abandoned'] ?? null) ? $package['abandoned'] : (($package['abandoned'] ?? false) === true ? '' : null),
    ];
}

// ---- Frontend dependencies ----

$pnpmAudit = readJson('pnpm-audit.json');
$pnpmAuditFailed = $pnpmAudit === null || isset($pnpmAudit['error']);
$jsVulnerabilities = [
    'error' => $pnpmAuditFailed ? (is_string($pnpmAudit['error']['message'] ?? null) ? $pnpmAudit['error']['message'] : failure('pnpm-audit.json')) : null,
    'items' => [],
];

foreach ($pnpmAuditFailed ? [] : ($pnpmAudit['advisories'] ?? []) as $advisory) {
    $findings = $advisory['findings'] ?? [];
    // npm states the fix itself; "<0.0.0", or none with patched_versions_unpublished, means none exists.
    $patched = $advisory['patched_versions'] ?? null;
    $fixedStatus = match (true) {
        is_string($patched) && $patched !== '<0.0.0' => 'available',
        $patched === '<0.0.0' || ($advisory['patched_versions_unpublished'] ?? false) === true => 'none',
        default => 'unknown',
    };

    $jsVulnerabilities['items'][] = [
        'package' => $advisory['module_name'] ?? '',
        'installed' => implode(', ', array_unique(array_column($findings, 'version'))) ?: null,
        'severity' => severity($advisory['severity'] ?? null),
        'title' => $advisory['title'] ?? '',
        'cve' => $advisory['cves'][0] ?? $advisory['github_advisory_id'] ?? null,
        'url' => $advisory['url'] ?? null,
        'affected' => $advisory['vulnerable_versions'] ?? null,
        'fixed' => $fixedStatus === 'available' ? $patched : null,
        'fixed_status' => $fixedStatus,
        'direct' => in_array($advisory['module_name'] ?? '', $jsDirect, true),
        // Dev only when every path that pulls it in is a dev one.
        'dev' => $findings !== [] && array_filter($findings, fn (array $finding) => ! ($finding['dev'] ?? false)) === [],
    ];
}

$pnpmOutdated = readJson('pnpm-outdated.json');
$jsOutdated = ['error' => $pnpmOutdated === null ? failure('pnpm-outdated.json') : null, 'items' => []];

foreach ($pnpmOutdated ?? [] as $package => $versions) {
    $current = (string) ($versions['current'] ?? '');
    $latest = (string) ($versions['latest'] ?? '');

    // Majors only, like composer outdated --major-only.
    if (major($current) === null || major($latest) === null || major($latest) <= major($current)) {
        continue;
    }

    $jsOutdated['items'][] = [
        'package' => $package,
        'current' => $current,
        'latest' => $latest,
        'dev' => ($versions['dependencyType'] ?? '') === 'devDependencies',
        'abandoned' => ($versions['isDeprecated'] ?? false) ? '' : null,
    ];
}

// ---- Versions the frontend ships ----

$pnpmLock = is_file('pnpm-lock.yaml') ? (string) file_get_contents('pnpm-lock.yaml') : '';
$nodeVersion = is_file('node-version.txt') ? trim((string) file_get_contents('node-version.txt')) : '';

$versions = [
    'node' => $nodeVersion !== '' ? $nodeVersion : null,
    'next' => lockedVersion($pnpmLock, 'next'),
    'react' => lockedVersion($pnpmLock, 'react'),
];

// ---- End-of-life tables ----

$eol = ['error' => null, 'products' => []];

foreach (EOL_PRODUCTS as $product) {
    $cycles = json_decode((string) @file_get_contents("https://endoflife.date/api/{$product}.json", false, $context), true);

    if (! is_array($cycles)) {
        $eol['error'] = trim(($eol['error'] ?? '')." {$product}: endoflife.date did not answer.");

        continue;
    }

    // support / eol: a date, or a boolean (eol false = no end announced).
    $eol['products'][$product] = array_map(fn (array $cycle) => [
        'cycle' => (string) $cycle['cycle'],
        'support' => $cycle['support'] ?? null,
        'eol' => $cycle['eol'] ?? null,
        'latest' => $cycle['latest'] ?? null,
    ], $cycles);
}

$report = [
    'schema' => 1,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'ref' => getenv('AUDIT_REF') ?: null,
    'commit' => getenv('AUDIT_COMMIT') ?: null,
    // Generated on a developer machine: the commit may not be on GitHub yet.
    'local' => getenv('AUDIT_LOCAL') === '1',
    'vulnerabilities' => ['php' => $phpVulnerabilities, 'js' => $jsVulnerabilities],
    'outdated' => ['php' => $phpOutdated, 'js' => $jsOutdated],
    'versions' => $versions,
    'eol' => $eol,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

fwrite(STDERR, sprintf(
    "Dependency audit: %d PHP and %d frontend vulnerabilities, %d PHP and %d frontend major updates.\n",
    count($phpVulnerabilities['items']),
    count($jsVulnerabilities['items']),
    count($phpOutdated['items']),
    count($jsOutdated['items']),
));

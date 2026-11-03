<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Extracts relevant package versions from composer.lock and pnpm-lock.yaml
 * and writes a curated deps-versions.md for Cursor and Windsurf rules context.
 */
class GenerateDepsVersionsCommand extends Command
{
	protected $signature = 'deps:versions';

	protected $description = 'Generate .cursor/deps-versions.md and .devin/deps-versions.md from composer.lock and pnpm-lock.yaml';

	/** @var string Project root (parent of backend) */
	private string $root;

	public function __construct()
	{
		parent::__construct();
		$this->root = dirname(base_path());
	}

	public function handle(): int
	{
		$lines = [
			'# Dependency versions (auto-generated)',
			'',
			'> Run `php artisan deps:versions` after composer/pnpm updates.',
			'',
			'## Backend (composer.lock)',
			'',
			...$this->extractComposerVersions(),
			'',
			'## Frontend (pnpm-lock.yaml)',
			'',
			...$this->extractPnpmVersions(),
		];

		$outputPaths = [
			$this->root . '/.cursor/deps-versions.md',
			$this->root . '/.devin/deps-versions.md',
		];
		$content = implode("\n", $lines);

		foreach ($outputPaths as $outputPath) {
			$dir = dirname($outputPath);

			if (! File::isDirectory($dir)) {
				File::makeDirectory($dir, 0755, true);
			}

			File::put($outputPath, $content);

			$this->info("Written to {$outputPath}");
		}

		return 0;
	}

	private function extractComposerVersions(): array
	{
		$lockPath = base_path('composer.lock');
		if (! File::exists($lockPath)) {
			return ['(composer.lock not found)'];
		}

		$lock = json_decode(File::get($lockPath), true);
		if (! isset($lock['packages'])) {
			return ['(invalid composer.lock)'];
		}

		$composerJson = json_decode(File::get(base_path('composer.json')), true);
		$wanted = array_merge(
			array_keys($composerJson['require'] ?? []),
			array_keys($composerJson['require-dev'] ?? [])
		);

		$versions = [];
		foreach ($lock['packages'] as $pkg) {
			$name = $pkg['name'] ?? null;
			if ($name && in_array($name, $wanted, true)) {
				$versions[] = '- ' . $name . ' - ' . ($pkg['version'] ?? '?');
			}
		}

		// Add php version from platform if present
		$platform = $lock['platform'] ?? $lock['platform-overrides'] ?? [];
		$phpVer = $platform['php'] ?? null;
		if ($phpVer) {
			array_unshift($versions, '- php - ' . $phpVer);
		}

		return $versions ?: ['(no matching packages)'];
	}

	private function extractPnpmVersions(): array
	{
		$lockPath = $this->root . '/frontend/pnpm-lock.yaml';
		$pkgPath = $this->root . '/frontend/package.json';
		if (! File::exists($lockPath)) {
			return ['(pnpm-lock.yaml not found)'];
		}

		$wanted = [];
		if (File::exists($pkgPath)) {
			$pkg = json_decode(File::get($pkgPath), true);
			$wanted = array_merge(
				array_keys($pkg['dependencies'] ?? []),
				array_keys($pkg['devDependencies'] ?? [])
			);
		}

		$content = File::get($lockPath);
		$versions = [];
		$currentPkg = null;
		$pastPackages = false;

		foreach (explode("\n", $content) as $line) {
			if (preg_match('#^packages:\s*$#', $line)) {
				$pastPackages = true;
				break;
			}
			if ($pastPackages) {
				break;
			}

			// Package key at 6 spaces: "      packagename:"
			if (preg_match('#^\s{6}([^:]+):\s*$#', $line, $m)) {
				$currentPkg = trim($m[1]);
				continue;
			}
			// version: X.Y.Z (strip parenthetical suffix)
			if ($currentPkg !== null && preg_match('#^\s+version:\s+([\d.]+)#', $line, $m)) {
				$ver = $m[1];
				if (empty($wanted) || in_array($currentPkg, $wanted, true)) {
					$versions[] = '- ' . $currentPkg . ' - v' . $ver;
				}
				$currentPkg = null;
			}
		}

		return $versions ?: ['(no matching packages)'];
	}
}

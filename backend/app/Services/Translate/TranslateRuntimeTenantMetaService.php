<?php

namespace App\Services\Translate;

use App\Models\Banca;
use App\Models\Translate;
use App\Support\Url\FrontendLang;
use Illuminate\Support\Facades\File;

class TranslateRuntimeTenantMetaService
{
	/**
	 * Annotate runtime lang JSON files with tenant metadata comments.
	 *
	 * @return array{files: int, annotations: int}
	 */
	public static function syncRuntimeFiles(): array
	{
		$langFiles = array_values(
			array_filter(
				[FrontendLang::jsonPath("it"), FrontendLang::jsonPath("en")],
				static fn(string $path): bool => File::exists($path)
			)
		);

		if ($langFiles === []) {
			return ["files" => 0, "annotations" => 0];
		}

		$tenantKeys = self::tenantKeys();
		$tenantNames = Banca::query()->pluck("nome", "banca_abi")->map(static fn($name): string => (string) $name)->all();

		$totalAnnotations = 0;

		foreach ($langFiles as $path) {
			$existing = self::readJson($path);
			[$updated, $annotations] = self::annotateTenantComments($existing, $tenantKeys, $tenantNames);
			File::put($path, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$totalAnnotations += $annotations;
		}

		return ["files" => count($langFiles), "annotations" => $totalAnnotations];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function readJson(string $path): array
	{
		if (!File::exists($path)) {
			return [];
		}

		$decoded = json_decode(File::get($path), true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @return array<int, string>
	 */
	private static function tenantKeys(): array
	{
		$fromBanche = Banca::query()
			->orderBy("banca_abi")
			->pluck("banca_abi")
			->map(static fn($abi): string => (string) $abi)
			->values()
			->all();

		$fromTranslates = Translate::query()
			->whereNotNull("banca_abi")
			->distinct()
			->orderBy("banca_abi")
			->pluck("banca_abi")
			->map(static fn($abi): string => (string) $abi)
			->values()
			->all();

		return array_values(array_unique(array_merge(["default"], $fromBanche, $fromTranslates)));
	}

	/**
	 * @param  array<string, mixed>  $data
	 * @param  array<int, string>  $tenantKeys
	 * @param  array<string, string>  $tenantNames
	 * @return array{0: array<string, mixed>, 1: int}
	 */
	private static function annotateTenantComments(array $data, array $tenantKeys, array $tenantNames): array
	{
		$annotations = 0;

		foreach ($data as $page => $codes) {
			if (!is_array($codes)) {
				continue;
			}

			foreach ($codes as $code => $node) {
				if (!is_array($node)) {
					continue;
				}

				$hasVariantObjects = collect($node)->contains(static fn($value): bool => is_array($value));
				if (!$hasVariantObjects) {
					continue;
				}

				$variants = $node;
				$existingTenantLabels = [];
				$missingTenantLabels = [];

				foreach ($tenantKeys as $tenantKey) {
					$label =
						$tenantKey === "default"
							? "default - Default fallback"
							: "{$tenantKey} - " . ($tenantNames[$tenantKey] ?? "Tenant");

					if (array_key_exists($tenantKey, $variants)) {
						$existingTenantLabels[] = $label;
					} else {
						$missingTenantLabels[] = $label;
					}
				}

				$variants["__tenant_comment"] = "existing: " . implode(", ", $existingTenantLabels);
				$variants["__missing_tenant_nodes_comment"] =
					$missingTenantLabels === [] ? "none" : "missing: " . implode(", ", $missingTenantLabels);

				$data[$page][$code] = $variants;
				$annotations++;
			}
		}

		return [$data, $annotations];
	}
}

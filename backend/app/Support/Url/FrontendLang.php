<?php

namespace App\Support\Url;

/**
 * Absolute paths to Next.js locale JSON files (repo root: frontend/lang).
 */
class FrontendLang
{
	public static function jsonPath(string $locale): string
	{
		return dirname(base_path()) . "/frontend/lang/" . $locale . ".json";
	}
}

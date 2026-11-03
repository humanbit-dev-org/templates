<?php

namespace App\Support\Locale;

/**
 * Locales accepted from client-supplied headers (X-Locale, locale) and
 * referer paths. Anything else falls back to Italian, the app default.
 */
class Locales
{
    public const SUPPORTED = ['it', 'en', 'de'];

    public static function normalize(?string $locale, string $default = 'it'): string
    {
        return in_array($locale, self::SUPPORTED, true) ? $locale : $default;
    }
}

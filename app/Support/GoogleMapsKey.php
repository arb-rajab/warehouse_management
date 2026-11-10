<?php

namespace App\Support;

/**
 * The Google Maps JavaScript API key the storefront and seller pages load the
 * map with. Staff set it on the Google map settings page (the `google_map_api_key`
 * business setting); while none is saved there the `MAP_API_KEY` environment
 * value (`services.google_maps.key`) is used. The saved setting wins.
 *
 * The key is a browser-side key, so it is printed into pages, but it is never
 * logged and the settings page only ever receives `mask()` of it.
 */
final class GoogleMapsKey
{
    public const string SETTING = 'google_map_api_key';

    private const string BULLETS = '••••••••';

    /** The key to load the map with: the saved setting, else MAP_API_KEY, else null. */
    public static function get(): ?string
    {
        return self::resolve(get_setting(self::SETTING));
    }

    /** The same precedence on an already-read `google_map_api_key` value. */
    public static function resolve(mixed $stored): ?string
    {
        return self::clean($stored) ?? self::fromEnvironment();
    }

    /** The key saved on the Google map settings page, if any. */
    public static function stored(): ?string
    {
        return self::clean(get_setting(self::SETTING));
    }

    /** The MAP_API_KEY value, if any. */
    public static function fromEnvironment(): ?string
    {
        return self::clean(config('services.google_maps.key'));
    }

    /**
     * What the settings page may show of a saved key: bullets plus its last
     * four characters (bullets only for a key too short to hide most of), or
     * an empty string when none is saved.
     */
    public static function mask(?string $stored): string
    {
        $stored = self::clean($stored);

        if ($stored === null) {
            return '';
        }

        return mb_strlen($stored) < 12 ? self::BULLETS : self::BULLETS.mb_substr($stored, -4);
    }

    private static function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

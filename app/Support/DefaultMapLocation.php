<?php

namespace App\Support;

/**
 * Where a Google Maps pin starts when the record it edits has no coordinates
 * yet: the default location set on the admin's Google Map settings page
 * (`google_map_latitude`, `google_map_longtitude`, spelled as the legacy store
 * stored it), or Amsterdam while that is unset or not a point on the map.
 */
final class DefaultMapLocation
{
    /** @var array{latitude: float, longitude: float} */
    public const AMSTERDAM = ['latitude' => 52.3676, 'longitude' => 4.9041];

    /**
     * @return array{latitude: float, longitude: float}
     */
    public static function coordinates(): array
    {
        $latitude = get_setting('google_map_latitude');
        $longitude = get_setting('google_map_longtitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return self::AMSTERDAM;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if (abs($latitude) > 90 || abs($longitude) > 180) {
            return self::AMSTERDAM;
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }
}

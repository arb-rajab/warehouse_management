<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The public IDs the storefront prints into its pages for Google Analytics, the
 * Facebook pixel, the Facebook customer chat and Facebook comments. Staff set
 * them on the Analytics, Facebook chat and Facebook comments settings pages (one
 * `business_settings` row each); while none is saved there the `.env` value
 * (`services.*`) is used. The saved value wins (.ai/rules/admin-env-overrides.md).
 *
 * They are public by nature (every visitor's browser receives them), so they are
 * shown back in full. They land in `<script>` blocks and URLs, so a saved value
 * must match its format (`PATTERNS`); one that does not, for example a row edited
 * in the database, reads as not saved and the `.env` value applies.
 */
final class StoreIntegrationIds
{
    /** Field => `business_settings.type` (the same name). */
    public const array FIELDS = [
        'google_analytics_tracking_id' => 'services.google_analytics.tracking_id',
        'facebook_pixel_id' => 'services.facebook.pixel_id',
        'facebook_page_id' => 'services.facebook.page_id',
        'facebook_app_id' => 'services.facebook.app_id',
    ];

    /**
     * The characters real IDs use and nothing else: no quote, angle bracket,
     * space or line break. `D` stops `$` matching before a trailing newline.
     * The same patterns are the request rules and the browser copy.
     */
    public const array PATTERNS = [
        'google_analytics_tracking_id' => '/^[A-Za-z]{1,3}-[A-Za-z0-9-]{3,30}$/D',
        'facebook_pixel_id' => '/^[0-9]{5,20}$/D',
        'facebook_page_id' => '/^[0-9]{5,20}$/D',
        'facebook_app_id' => '/^[0-9]{5,20}$/D',
    ];

    /** The value to print: the saved ID, else the `.env` one, else null. */
    public static function get(string $field): ?string
    {
        return self::effective($field)['value'];
    }

    /**
     * The ID in use and where it comes from.
     *
     * @return array{value: string|null, source: 'admin'|'env'}
     */
    public static function effective(string $field): array
    {
        $stored = self::stored($field);

        return $stored !== null
            ? ['value' => $stored, 'source' => 'admin']
            : ['value' => self::fromEnvironment($field), 'source' => 'env'];
    }

    /** The ID saved in the admin, if any and well formed. */
    public static function stored(string $field): ?string
    {
        $value = get_setting($field);

        // Not trimmed: a saved value with a stray line break or space is
        // malformed, however the row got there, and reads as not saved.
        return is_string($value) && preg_match(self::PATTERNS[$field], $value) === 1 ? $value : null;
    }

    /** The `.env` value (`TRACKING_ID`, `FACEBOOK_PIXEL_ID`, ...), if any. */
    public static function fromEnvironment(string $field): ?string
    {
        return self::clean(config(self::FIELDS[$field]));
    }

    /**
     * Save the fields given: a filled one is stored, an empty one deletes its
     * row so the `.env` value applies again. A field not given is left alone.
     *
     * @param  array<string, mixed>  $values  validated request input
     */
    public static function save(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (array_keys(self::FIELDS) as $field) {
                if (! array_key_exists($field, $values)) {
                    continue;
                }

                $value = self::clean($values[$field]);

                if ($value === null) {
                    BusinessSetting::query()->where('type', $field)->whereNull('lang')->delete();

                    continue;
                }

                $setting = BusinessSetting::query()->where('type', $field)->whereNull('lang')->orderBy('id')->first(['id', 'type', 'value'])
                    ?? new BusinessSetting;
                $setting->type = $field;
                $setting->value = $value;
                $setting->save();
            }
        });

        Cache::forget('business_settings');
    }

    private static function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

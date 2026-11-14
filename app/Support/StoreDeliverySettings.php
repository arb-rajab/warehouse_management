<?php

namespace App\Support;

use App\Models\BusinessSetting;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The delivery lead time (days) and timezone orders placed through the app API
 * are checked against. Staff set them on the Order configuration page (one
 * `business_settings` row each); while none is saved there the `.env` value
 * (`store.delivery.*`, STORE_DELIVERY_LEAD_DAYS and STORE_DELIVERY_TIMEZONE) is
 * used. The saved value wins (.ai/rules/admin-env-overrides.md). The delivery
 * weekdays stay in config/store.php.
 *
 * Nothing is laid over `config()`: the one consumer
 * (Api\V1\OrderController::deliveryDateErrors) reads through this class.
 *
 * A saved value that is not a whole number from 0 to 30, or not a PHP timezone
 * identifier (a row edited in the database), reads as not saved and `.env`
 * applies. An invalid STORE_DELIVERY_TIMEZONE in `.env` is not guarded: it fails
 * the order API as it always did, so fix the `.env`.
 */
final class StoreDeliverySettings
{
    /** Field => `business_settings.type` (the same name). */
    public const array FIELDS = [
        'delivery_lead_days' => 'store.delivery.lead_days',
        'delivery_timezone' => 'store.delivery.timezone',
    ];

    public const int MIN_LEAD_DAYS = 0;

    public const int MAX_LEAD_DAYS = 30;

    /** The days to skip after today. */
    public static function leadDays(): int
    {
        return (int) self::effective('delivery_lead_days')['value'];
    }

    /** The timezone "today" is read in. */
    public static function timezone(): string
    {
        return (string) self::effective('delivery_timezone')['value'];
    }

    /**
     * The value in use and where it comes from.
     *
     * @return array{value: int|string|null, source: 'admin'|'env'}
     */
    public static function effective(string $field): array
    {
        $stored = self::stored($field);

        return $stored !== null
            ? ['value' => $stored, 'source' => 'admin']
            : ['value' => self::fromEnvironment($field), 'source' => 'env'];
    }

    /** The value saved in the admin, if any and valid. */
    public static function stored(string $field): int|string|null
    {
        $value = self::clean(get_setting($field));

        if ($value === null) {
            return null;
        }

        if ($field === 'delivery_lead_days') {
            return preg_match('/^[0-9]{1,2}$/D', $value) === 1 && (int) $value <= self::MAX_LEAD_DAYS ? (int) $value : null;
        }

        return in_array($value, self::timezones(), true) ? $value : null;
    }

    /** The `.env` value (STORE_DELIVERY_LEAD_DAYS, STORE_DELIVERY_TIMEZONE). */
    public static function fromEnvironment(string $field): int|string|null
    {
        $value = config(self::FIELDS[$field]);

        return $field === 'delivery_lead_days' ? (int) $value : self::clean($value);
    }

    /**
     * Every timezone identifier the page offers and the request accepts.
     *
     * @return list<string>
     */
    public static function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
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

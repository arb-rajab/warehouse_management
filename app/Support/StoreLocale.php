<?php

namespace App\Support;

use App\Enums\Locale;
use App\Models\Language;
use Illuminate\Support\Facades\Lang;

class StoreLocale
{
    /** The legacy Arabic content language keeps its store code, sa. */
    public static function uiLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'sa' ? 'ar' : $locale;
    }

    public static function status(?string $value): string
    {
        $key = 'storefront.status.'.$value;

        return Lang::has($key) ? __($key) : __('storefront.status.unknown');
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && (Locale::tryFrom($locale) !== null
            || Language::query()->where('code', $locale)->exists());
    }

    public static function isRtl(): bool
    {
        return match (self::uiLocale()) {
            'ar' => true,
            'en' => false,
            default => Language::query()->where('code', app()->getLocale())->value('rtl') == 1,
        };
    }

    public static function number(float $value, int $decimals): string
    {
        $locale = str_replace('_', '-', self::uiLocale());
        $formatter = new \NumberFormatter($locale.'-u-nu-latn', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        return (string) $formatter->format($value);
    }

    public static function date(int $timestamp, bool $withTime = false): string
    {
        $locale = str_replace('_', '-', self::uiLocale());
        $formatter = new \IntlDateFormatter(
            $locale.'-u-nu-latn',
            \IntlDateFormatter::MEDIUM,
            $withTime ? \IntlDateFormatter::SHORT : \IntlDateFormatter::NONE,
            date_default_timezone_get(),
            \IntlDateFormatter::GREGORIAN,
        );

        return (string) $formatter->format($timestamp);
    }
}

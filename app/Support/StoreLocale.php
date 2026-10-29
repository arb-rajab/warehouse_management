<?php

namespace App\Support;

use App\Enums\Locale;
use App\Models\Language;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Lang;

class StoreLocale
{
    /**
     * Keep content and UI locales separate and restore both after a request.
     *
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withLocale(string $contentLocale, string $uiLocale, \Closure $callback): mixed
    {
        $applicationLocale = app()->getLocale();
        $translator = app('translator');
        $translatorLocale = $translator->getLocale();
        $fallback = $translator->getFallback();

        try {
            app()->setLocale($contentLocale);
            $translator->setLocale(self::uiLocale($uiLocale));
            $translator->setFallback(self::uiLocale(config('app.fallback_locale')));

            return $callback();
        } finally {
            app()->setLocale($applicationLocale);
            $translator->setLocale($translatorLocale);
            $translator->setFallback($fallback);
        }
    }

    /** The legacy Arabic content language keeps its store code, sa. */
    public static function uiLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'sa' ? 'ar' : $locale;
    }

    /** Deliveries never inherit the request session or a queue worker's locale. */
    public static function deliveryLocale(?object $recipient = null, ?string $locale = null): string
    {
        $preference = $locale ?? ($recipient instanceof HasLocalePreference ? $recipient->preferredLocale() : null);
        $preference = is_string($preference) ? self::uiLocale($preference) : null;

        return in_array($preference, ['en', 'ar'], true)
            ? $preference
            : (self::uiLocale(config('app.ui_default_locale')) === 'ar' ? 'ar' : 'en');
    }

    /** Downloads follow the validated UI choice, independently of content language. */
    public static function documentLocale(): string
    {
        $locale = request()->hasSession() ? request()->session()->get('locale') : null;

        return self::deliveryLocale(locale: is_string($locale) ? $locale : null);
    }

    public static function status(?string $value, ?string $locale = null): string
    {
        $key = 'storefront.status.'.$value;

        return Lang::has($key, $locale) ? __($key, [], $locale) : __('storefront.status.unknown', [], $locale);
    }

    /** Known payment methods are UI vocabulary; custom names remain content. */
    public static function paymentMethod(?string $value): string
    {
        $key = 'storefront.status.'.$value;

        return Lang::has($key) ? __($key) : translate(ucfirst(str_replace('_', ' ', (string) $value)));
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

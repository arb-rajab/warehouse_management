<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The default content language: the language code the store keeps its
 * default-language rows under (role, brand, page and product translations, the
 * language switcher, the translate() fallback). Staff choose it on the
 * Languages page (one `business_settings` row, `default_language`); while none
 * is saved there the `.env` value (`app.default_language`, DEFAULT_LANGUAGE) is
 * used. The saved value wins (.ai/rules/admin-env-overrides.md).
 *
 * `config('app.default_language')` is read at about 80 sites, so `apply()` lays
 * the effective code over it. It is called on web requests and before every
 * queued job (AppServiceProvider), never for other console commands:
 * `config:cache` boots the providers, and the saved value would otherwise be
 * written into bootstrap/cache/config.php. Code that needs the effective value
 * without relying on that (the Languages page, the setup checklist, the
 * `default_language()` helper) reads `get()` / `effective()`.
 *
 * Only the code is stored here; whether the language exists and is active is
 * checked when it is chosen (UpdateDefaultLanguageRequest) and by the setup
 * checklist. A stored value that is not a language code (`PATTERN`) reads as
 * not saved.
 */
final class StoreDefaultLanguage
{
    /** The `business_settings.type` of the saved code. */
    public const string KEY = 'default_language';

    /** The cache entry holding the saved code. */
    public const string CACHE_KEY = 'business_settings:default_language';

    /** What a language code looks like (`languages.code` is validated to 10 characters). */
    public const string PATTERN = '/^[A-Za-z0-9_-]{1,10}$/D';

    private const int CACHE_SECONDS = 600;

    private bool $captured = false;

    private ?string $environment = null;

    /** The code in use, the saved one else the `.env` one, or null. */
    public function get(): ?string
    {
        return $this->effective()['value'];
    }

    /**
     * The code in use and where it comes from.
     *
     * @return array{value: string|null, source: 'admin'|'env'}
     */
    public function effective(): array
    {
        $stored = $this->stored();

        return $stored !== null
            ? ['value' => $stored, 'source' => 'admin']
            : ['value' => $this->fromEnvironment(), 'source' => 'env'];
    }

    /**
     * The code saved in the admin, if any and well formed. A missing table or
     * cache table (migrations, a fresh install) reads as nothing saved and is
     * not cached.
     */
    public function stored(): ?string
    {
        try {
            $value = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): string => (string) $this->read());
        } catch (Throwable) {
            return null;
        }

        return $this->valid($value);
    }

    /** The `.env` code (DEFAULT_LANGUAGE), as it was before the first apply(). */
    public function fromEnvironment(): ?string
    {
        return $this->captured ? $this->environment : $this->clean(config('app.default_language'));
    }

    /** Lay the effective code over `config('app.default_language')`. */
    public function apply(): void
    {
        if (! $this->captured) {
            $this->environment = $this->clean(config('app.default_language'));
            $this->captured = true;
        }

        config(['app.default_language' => $this->get()]);
    }

    /**
     * Save the code: a filled one is stored, an empty one deletes the row so
     * the `.env` value applies again. The caller has checked the language.
     */
    public function save(?string $code): void
    {
        $code = $this->clean($code);

        DB::transaction(function () use ($code): void {
            if ($code === null) {
                BusinessSetting::query()->where('type', self::KEY)->whereNull('lang')->delete();

                return;
            }

            $setting = BusinessSetting::query()->where('type', self::KEY)->whereNull('lang')->orderBy('id')->first(['id', 'type', 'value'])
                ?? new BusinessSetting;
            $setting->type = self::KEY;
            $setting->value = $code;
            $setting->save();
        });

        $this->forget();
        $this->apply();
    }

    /** Settings writers forget `business_settings`; this one also its own entry. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('business_settings');
    }

    private function read(): ?string
    {
        return $this->clean(BusinessSetting::query()->where('type', self::KEY)->whereNull('lang')->orderBy('id')->value('value'));
    }

    private function valid(string $value): ?string
    {
        return preg_match(self::PATTERN, $value) === 1 ? $value : null;
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

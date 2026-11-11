<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The reCAPTCHA site key and secret staff save on the reCAPTCHA settings page,
 * used over the server's `.env` (CAPTCHA_KEY, CAPTCHA_SECRET) values
 * (.ai/rules/admin-env-overrides.md).
 *
 * The two are one pair and are saved and read as one: with a site key saved, the
 * site key and the secret both come from the admin and CAPTCHA_SECRET is not
 * used (with no readable saved secret `secret()` is null and the Recaptcha rule
 * refuses, as it does for a missing secret); without one, both come from
 * `.env`. Mixing them would verify a visitor's token with a secret that does not
 * belong to the key the widget was rendered with.
 *
 * The secret is stored encrypted with the application key
 * (`Crypt::encryptString`) and decrypted only by `secret()`. It is never cached
 * in plain text, logged, or sent to the browser. Nothing is written into the
 * config: consumers call this class, so `config:cache` and other console
 * commands never see the secret.
 */
final class StoreRecaptchaSettings
{
    /** The cache entry holding the saved rows (the secret still encrypted). */
    public const string CACHE_KEY = 'business_settings:recaptcha';

    /** Field => `business_settings.type`. */
    public const array KEYS = [
        'site_key' => 'recaptcha_site_key',
        'secret' => 'recaptcha_secret_key_encrypted',
    ];

    /** The characters a site key uses and nothing else (it is printed into the pages). */
    public const string SITE_KEY_PATTERN = '/^[A-Za-z0-9_-]{20,100}$/D';

    private const int CACHE_SECONDS = 600;

    private bool $warnedUnreadable = false;

    /**
     * The saved values, the secret still encrypted; null for a field with no
     * row. A missing table or cache table (migrations, a fresh install) reads
     * as nothing saved and is not cached.
     *
     * @return array{site_key: string|null, secret: string|null}
     */
    public function stored(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->read());
        } catch (Throwable) {
            return ['site_key' => null, 'secret' => null];
        }
    }

    /**
     * The site key and whether a secret is set, each with its source (`admin`
     * or `env`). The secret itself is not part of this: only `secret()` returns it.
     *
     * @return array{site_key: array{value: string|null, source: 'admin'|'env'}, secret: array{set: bool, source: 'admin'|'env'}}
     */
    public function effective(): array
    {
        $stored = $this->stored();

        if ($stored['site_key'] !== null) {
            return [
                'site_key' => ['value' => $stored['site_key'], 'source' => 'admin'],
                'secret' => ['set' => $this->decrypt($stored['secret']) !== null, 'source' => 'admin'],
            ];
        }

        return [
            'site_key' => ['value' => $this->clean(config('services.recaptcha.site_key')), 'source' => 'env'],
            'secret' => ['set' => $this->clean(config('services.recaptcha.secret_key')) !== null, 'source' => 'env'],
        ];
    }

    /** The site key to render the widget with, if any. */
    public function siteKey(): ?string
    {
        return $this->effective()['site_key']['value'];
    }

    /** The secret to verify a token with, decrypted; null when none is set or it is unreadable. */
    public function secret(): ?string
    {
        $stored = $this->stored();

        if ($stored['site_key'] !== null) {
            return $this->decrypt($stored['secret']);
        }

        return $this->clean(config('services.recaptcha.secret_key'));
    }

    /** Whether a saved secret exists but cannot be decrypted (the application key changed). */
    public function secretUnreadable(): bool
    {
        $stored = $this->stored()['secret'];

        return $stored !== null && $this->decrypt($stored) === null;
    }

    /**
     * Save the reCAPTCHA page. An empty site key removes the pair (the secret
     * too), so `.env` applies again. An empty secret keeps the saved one unless
     * `$clearSecret` removes it.
     *
     * @param  array{site_key?: mixed, secret?: mixed}  $data  validated request input
     */
    public function save(array $data, bool $clearSecret): void
    {
        $siteKey = $this->clean($data['site_key'] ?? null);

        DB::transaction(function () use ($data, $siteKey, $clearSecret): void {
            $this->write(self::KEYS['site_key'], $siteKey);

            if ($siteKey === null || $clearSecret) {
                $this->write(self::KEYS['secret'], null);
            } elseif (filled($data['secret'] ?? null)) {
                $this->write(self::KEYS['secret'], Crypt::encryptString((string) $data['secret']));
            }
        });

        $this->forget();
    }

    /** Settings writers forget `business_settings`; this one also its own entry. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('business_settings');
    }

    /** @return array{site_key: string|null, secret: string|null} */
    private function read(): array
    {
        $rows = BusinessSetting::query()->whereIn('type', array_values(self::KEYS))->whereNull('lang')
            ->orderByDesc('id')->pluck('value', 'type');

        return [
            'site_key' => $this->clean($rows->get(self::KEYS['site_key'])),
            'secret' => $this->clean($rows->get(self::KEYS['secret'])),
        ];
    }

    private function decrypt(?string $encrypted): ?string
    {
        if ($encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            if (! $this->warnedUnreadable) {
                $this->warnedUnreadable = true;
                Log::warning('The saved reCAPTCHA secret cannot be decrypted with the application key; enter it again on the reCAPTCHA settings page.');
            }

            return null;
        }
    }

    private function write(string $type, ?string $value): void
    {
        if ($value === null) {
            BusinessSetting::query()->where('type', $type)->whereNull('lang')->delete();

            return;
        }

        $setting = BusinessSetting::query()->where('type', $type)->whereNull('lang')->orderBy('id')->first(['id', 'type', 'value'])
            ?? new BusinessSetting;
        $setting->type = $type;
        $setting->value = $value;
        $setting->save();
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

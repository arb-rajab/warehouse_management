<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The outgoing mail settings staff save on the SMTP settings page, applied over
 * the server's `.env` (MAIL_*) values (.ai/rules/admin-env-overrides.md).
 *
 * Precedence, saved value first, `.env` as the fallback:
 * - the mailer, the from address and the from name: each on its own;
 * - the mail server (host, port, encryption, username, password): as one group.
 *   With a host saved, all five come from the admin and the `.env` username,
 *   password and MAIL_URL are not used; without one, all five come from `.env`.
 *   Mixing them would send the server's MAIL_PASSWORD to a host typed in the
 *   admin, or a saved password to the server's host.
 *
 * The password is stored encrypted with the application key
 * (`Crypt::encryptString`) and decrypted only here. It is never cached in
 * plain text, logged, or sent to the browser.
 *
 * `apply()` writes the effective values into the runtime config. It is called
 * on web requests and before every queued job (AppServiceProvider), never for
 * other console commands: `config:cache` boots the providers, and the decrypted
 * password would otherwise be written into bootstrap/cache/config.php.
 */
final class StoreMailSettings
{
    /** The cache entry holding the saved rows (the password still encrypted). */
    public const string CACHE_KEY = 'business_settings:mail';

    /** Field => `business_settings.type`. */
    public const array KEYS = [
        'mailer' => 'mail_mailer',
        'host' => 'mail_host',
        'port' => 'mail_port',
        'scheme' => 'mail_scheme',
        'username' => 'mail_username',
        'password' => 'mail_password_encrypted',
        'from_address' => 'mail_from_address',
        'from_name' => 'mail_from_name',
    ];

    /** The mailers the page can choose; any other comes only from MAIL_MAILER. */
    public const array MAILERS = ['smtp', 'log'];

    /** `smtps` is SSL/TLS from the start; `smtp` is plain, upgraded with STARTTLS when offered. */
    public const array SCHEMES = ['smtp', 'smtps'];

    /** The fields saved, and taken from `.env`, as one group. */
    public const array SERVER_FIELDS = ['host', 'port', 'scheme', 'username', 'password'];

    private const int CACHE_SECONDS = 600;

    /** @var array<string, mixed>|null the config before the first apply(): the `.env` values */
    private ?array $environment = null;

    private ?string $applied = null;

    private bool $warnedUnreadable = false;

    /**
     * The saved values, the password still encrypted; null for a field with no
     * row. A missing table or cache table (migrations, a fresh install) reads
     * as nothing saved and is not cached.
     *
     * @return array<string, string|null>
     */
    public function stored(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->read());
        } catch (Throwable) {
            return array_fill_keys(array_keys(self::KEYS), null);
        }
    }

    /**
     * The values mail is sent with and where each comes from (`admin` or
     * `env`). The password is decrypted; only this class and the mailer use it.
     *
     * @return array<string, array{value: mixed, source: 'admin'|'env'}>
     */
    public function effective(): array
    {
        $stored = $this->stored();
        $environment = $this->environment();
        $server = $stored['host'] !== null;
        $effective = [];

        foreach (['mailer', 'from_address', 'from_name'] as $field) {
            $effective[$field] = $stored[$field] !== null
                ? ['value' => $stored[$field], 'source' => 'admin']
                : ['value' => $environment[$field], 'source' => 'env'];
        }

        foreach (self::SERVER_FIELDS as $field) {
            $effective[$field] = $server
                ? ['value' => match ($field) {
                    'port' => (int) $stored['port'],
                    'password' => $this->decrypt($stored['password']),
                    default => $stored[$field],
                }, 'source' => 'admin']
                : ['value' => $environment[$field], 'source' => 'env'];
        }

        $effective['url'] = $server ? ['value' => null, 'source' => 'admin'] : ['value' => $environment['url'], 'source' => 'env'];

        return $effective;
    }

    /** The mailer MAIL_MAILER names, shown as the "use the server setting" choice. */
    public function environmentMailer(): string
    {
        return (string) $this->environment()['mailer'];
    }

    /** Whether a saved password exists but cannot be decrypted (the application key changed). */
    public function passwordUnreadable(): bool
    {
        $stored = $this->stored()['password'];

        return $stored !== null && $this->decrypt($stored) === null;
    }

    /**
     * Write the effective values into the runtime config. A mailer already
     * built keeps its transport, so built mailers are dropped when anything
     * changed since the last call (a queue worker picks up a save this way).
     */
    public function apply(): void
    {
        $this->environment ??= $this->current();
        $effective = $this->effective();
        $values = [
            'mail.default' => $effective['mailer']['value'],
            'mail.mailers.smtp.url' => $effective['url']['value'],
            'mail.mailers.smtp.host' => $effective['host']['value'],
            'mail.mailers.smtp.port' => $effective['port']['value'],
            'mail.mailers.smtp.scheme' => $effective['scheme']['value'],
            'mail.mailers.smtp.username' => $effective['username']['value'],
            'mail.mailers.smtp.password' => $effective['password']['value'],
            'mail.from.address' => $effective['from_address']['value'],
            'mail.from.name' => $effective['from_name']['value'],
        ];
        $fingerprint = hash('sha256', serialize($values));

        if ($fingerprint === $this->applied) {
            return;
        }

        config($values);
        $this->applied = $fingerprint;

        if (app()->resolved('mail.manager')) {
            Mail::forgetMailers();
        }
    }

    /**
     * Save the SMTP settings page. An empty field deletes its row, so the
     * `.env` value applies again; an empty password keeps the saved one. With
     * no host the whole server group is removed, the password included.
     *
     * Returns what changed, field => `set` (nothing was saved before),
     * `changed` or `removed` (the `.env` value applies again): names and kinds
     * only, never a value. The password counts as changed only when the
     * decrypted value differs. `$whenChanged` runs inside the same transaction
     * when something changed (the audit row), so the settings and their record
     * are saved together or not at all.
     *
     * @param  array<string, mixed>  $data  validated UpdateSmtpSettingsRequest input
     * @param  (Closure(array<string, string>): mixed)|null  $whenChanged
     * @return array<string, string>
     */
    public function save(array $data, bool $clearPassword, ?Closure $whenChanged = null): array
    {
        $server = filled($data['host'] ?? null);

        $changes = DB::transaction(function () use ($data, $server, $clearPassword, $whenChanged): array {
            $before = $this->read();

            foreach (self::KEYS as $field => $type) {
                if ($field === 'password') {
                    if (! $server || $clearPassword) {
                        $this->write($type, null);
                    } elseif (filled($data['password'] ?? null)) {
                        $this->write($type, Crypt::encryptString((string) $data['password']));
                    }

                    continue;
                }

                $value = $this->clean($data[$field] ?? null);
                $this->write($type, in_array($field, self::SERVER_FIELDS, true) && ! $server ? null : $value);
            }

            $changes = $this->changes($before, $this->read());
            if ($changes !== [] && $whenChanged !== null) {
                $whenChanged($changes);
            }

            return $changes;
        });

        $this->forget();
        $this->apply();

        return $changes;
    }

    /**
     * Field => `set`, `changed` or `removed` between two readings of the saved
     * rows. A password that cannot be decrypted any more counts as changed
     * when a new one is saved.
     *
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @return array<string, string>
     */
    private function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (array_keys(self::KEYS) as $field) {
            $old = $before[$field];
            $new = $after[$field];

            if ($old === $new) {
                continue;
            }

            if ($old === null || $new === null) {
                $changes[$field] = $old === null ? 'set' : 'removed';

                continue;
            }

            if ($field === 'password') {
                $oldPassword = $this->decrypt($old);
                $newPassword = $this->decrypt($new);
                if ($oldPassword !== null && $newPassword !== null && hash_equals($oldPassword, $newPassword)) {
                    continue;
                }
            }

            $changes[$field] = 'changed';
        }

        return $changes;
    }

    /** Settings writers forget `business_settings`; this one also its own entry. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('business_settings');
    }

    /** @return array<string, string|null> */
    private function read(): array
    {
        // The lowest id wins, as the settings pages write the first row.
        $rows = BusinessSetting::query()->whereIn('type', array_values(self::KEYS))->whereNull('lang')
            ->orderByDesc('id')->pluck('value', 'type');

        return array_map(fn (string $type): ?string => $this->clean($rows->get($type)), self::KEYS);
    }

    /**
     * The `.env` values: the config as it was before the first apply(), or the
     * config itself while nothing has been applied in this process.
     *
     * @return array<string, mixed>
     */
    private function environment(): array
    {
        return $this->environment ?? $this->current();
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        return [
            'mailer' => config('mail.default'),
            'url' => config('mail.mailers.smtp.url'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'scheme' => config('mail.mailers.smtp.scheme'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
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
                Log::warning('The saved SMTP password cannot be decrypted with the application key; enter it again on the SMTP settings page.');
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

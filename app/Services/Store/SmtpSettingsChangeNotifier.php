<?php

namespace App\Services\Store;

use App\Mail\SmtpSettingsChangedMail;
use App\Models\Role;
use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mails every active Super Admin, and the previous from-address when the from
 * address itself changed, that the SMTP settings were changed: by whom, when,
 * from which IP and which fields. Never a value or a secret
 * (.ai/rules/admin-env-overrides.md, "Security").
 *
 * Sent at once, not queued, through the settings as they were **before** the
 * change: someone who points the shop's mail at a server they control must
 * not be able to swallow the alert about it, so it travels over the server
 * the shop used until then. A queued mail would go out with the new settings
 * (the worker applies the saved ones, StoreMailSettings::apply()), and queueing
 * the old ones would put the decrypted password into the job payload. The old
 * SMTP server is a runtime-only mailer (`smtp_before_change`), dropped again
 * afterwards; a previous mailer other than `smtp` (`log`, or a `.env` one such
 * as `ses`) is not touched by the SMTP page and is used as it is. A
 * `failover`/`roundrobin` `.env` mailer's `smtp` leg already has the new values.
 *
 * Runs after the save has been committed: a mail that cannot be sent is logged
 * by exception class only (a server's reply can quote the username) and never
 * undoes or fails the save.
 */
final class SmtpSettingsChangeNotifier
{
    public const string MAILER = 'smtp_before_change';

    /** Seconds the old SMTP server gets to answer, so a dead one cannot hang the save. */
    private const int TIMEOUT = 10;

    /**
     * @param  array<string, string>  $changes  field => set|changed|removed
     * @param  array<string, array{value: mixed, source: string}>  $previous  StoreMailSettings::effective() before the save
     */
    public function notify(StoreUser $actor, array $changes, array $previous, ?string $ip, CarbonInterface $at): void
    {
        try {
            $recipients = $this->recipients($changes, $previous);
            if ($recipients === []) {
                return;
            }

            $mailer = $this->previousMailer($previous);
        } catch (Throwable $exception) {
            Log::warning('The SMTP change notification could not be prepared.', ['exception' => $exception::class]);

            return;
        }

        try {
            foreach ($recipients as $recipient) {
                try {
                    Mail::mailer($mailer)->to($recipient['email'])->send(new SmtpSettingsChangedMail([
                        'changes' => $changes,
                        'actor_name' => (string) $actor->name,
                        'actor_email' => (string) $actor->email,
                        'changed_at' => $at->copy()->utc()->format('Y-m-d H:i').' UTC',
                        'ip' => $ip,
                        'link' => route('smtp_settings.index'),
                        'from_address' => $previous['from_address']['value'] ?? null,
                        'from_name' => $previous['from_name']['value'] ?? null,
                    ], $recipient['user']));
                } catch (Throwable $exception) {
                    Log::warning('The SMTP change notification could not be sent.', ['exception' => $exception::class]);
                }
            }
        } finally {
            if ($mailer === self::MAILER) {
                config(['mail.mailers.'.self::MAILER => null]);
                Mail::purge(self::MAILER);
            }
        }
    }

    /**
     * Every active Super Admin (the role on the store guard, back-office
     * staff, not banned, with an email address), then the previous
     * from-address when the from address changed and it is not one of theirs.
     *
     * @param  array<string, string>  $changes
     * @param  array<string, array{value: mixed, source: string}>  $previous
     * @return list<array{email: string, user: StoreUser|null}>
     */
    public function recipients(array $changes, array $previous): array
    {
        $recipients = [];
        $seen = [];

        $superAdmins = StoreUser::query()
            ->whereHas('roles', fn ($roles) => $roles->where($roles->qualifyColumn('name'), Role::SUPER_ADMIN))
            ->whereIn('user_type', StoreUser::ADMIN_USER_TYPES)
            ->where(fn ($query) => $query->whereNull('banned')->orWhere('banned', 0))
            ->whereNotNull('email')
            ->orderBy('id')
            ->get();

        foreach ($superAdmins as $admin) {
            $email = trim((string) $admin->email);
            if (! $this->validEmail($email) || isset($seen[Str::lower($email)])) {
                continue;
            }

            $seen[Str::lower($email)] = true;
            $recipients[] = ['email' => $email, 'user' => $admin];
        }

        $previousFrom = trim((string) ($previous['from_address']['value'] ?? ''));
        if (array_key_exists('from_address', $changes) && $this->validEmail($previousFrom) && ! isset($seen[Str::lower($previousFrom)])) {
            $recipients[] = ['email' => $previousFrom, 'user' => null];
        }

        return $recipients;
    }

    /**
     * The mailer the shop sent with before the change: for `smtp`, a runtime
     * mailer from the old server's values (the `smtp` config already holds the
     * new ones); otherwise that mailer's own name.
     *
     * @param  array<string, array{value: mixed, source: string}>  $previous
     */
    private function previousMailer(array $previous): string
    {
        $name = (string) ($previous['mailer']['value'] ?? '');

        if ($name !== 'smtp') {
            return $name !== '' ? $name : (string) config('mail.default');
        }

        config(['mail.mailers.'.self::MAILER => [
            ...(array) config('mail.mailers.smtp', []),
            'transport' => 'smtp',
            'url' => $previous['url']['value'] ?? null,
            'host' => $previous['host']['value'] ?? null,
            'port' => $previous['port']['value'] ?? null,
            'scheme' => $previous['scheme']['value'] ?? null,
            'username' => $previous['username']['value'] ?? null,
            'password' => $previous['password']['value'] ?? null,
            'timeout' => self::TIMEOUT,
        ]]);

        return self::MAILER;
    }

    private function validEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! preg_match('/[\r\n]/', $email);
    }
}

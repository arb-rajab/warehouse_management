<?php

namespace App\Http\Requests\Store\Admin\Concerns;

use App\Models\AdminAuditLog;
use App\Models\StoreUser;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A sensitive settings page's save asks for the signed-in staff member's own
 * password (`current_password` => `current_password:store` in the request's
 * rules): the SMTP page and the Payment methods page
 * (.ai/rules/admin-env-overrides.md, "Security").
 *
 * Wrong passwords are counted per account in ONE limiter shared by every page
 * that uses this concern, so two pages do not double the guesses: after
 * MAX_PASSWORD_FAILURES within PASSWORD_FAILURE_DECAY_SECONDS the field refuses
 * before the password is even checked. Each counted failure is logged (user id
 * and IP) and written to the audit log under the page's own action, only while
 * the count is within the limit, so at most MAX_PASSWORD_FAILURES rows per
 * account per window. A missing password, or any other error, is not counted.
 * The password itself is never logged, flashed or saved.
 */
trait ConfirmsCurrentPassword
{
    /** Wrong current passwords an account may enter per window, across the sensitive pages. */
    public const int MAX_PASSWORD_FAILURES = 5;

    public const int PASSWORD_FAILURE_DECAY_SECONDS = 900;

    /** The audit action a counted wrong password writes (AdminAuditLog). */
    abstract protected function passwordFailedAction(): string;

    /** The log line a counted wrong password writes; it names the page, never the password. */
    abstract protected function passwordFailedMessage(): string;

    /** The limiter key of the signed-in staff member's wrong passwords, shared by the sensitive pages. */
    public static function passwordLimiterKey(StoreUser $user): string
    {
        return 'sensitive-settings-password|'.$user->getAuthIdentifier();
    }

    /**
     * A locked-out account is refused before its password is checked, so the
     * lockout cannot be used to keep guessing.
     */
    protected function prepareForValidation(): void
    {
        $key = self::passwordLimiterKey($this->actor());

        if (RateLimiter::tooManyAttempts($key, self::MAX_PASSWORD_FAILURES)) {
            throw ValidationException::withMessages([
                'current_password' => __('store_admin.smtp_current_password_throttled', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
    }

    protected function passedValidation(): void
    {
        RateLimiter::clear(self::passwordLimiterKey($this->actor()));
    }

    /**
     * Counts a wrong current password (not a missing one, nor any other error)
     * and records it while the count is within the limit.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        if (isset($validator->failed()['current_password']['CurrentPassword'])) {
            $actor = $this->actor();
            $failures = RateLimiter::hit(self::passwordLimiterKey($actor), self::PASSWORD_FAILURE_DECAY_SECONDS);

            if ($failures <= self::MAX_PASSWORD_FAILURES) {
                Log::warning($this->passwordFailedMessage(), ['user_id' => $actor->getKey(), 'ip' => $this->ip()]);
                AdminAuditLog::record($actor, $this->passwordFailedAction(), [], $this);
            }
        }

        parent::failedValidation($validator);
    }

    private function actor(): StoreUser
    {
        /** @var StoreUser $user */
        $user = $this->user('store');

        return $user;
    }
}

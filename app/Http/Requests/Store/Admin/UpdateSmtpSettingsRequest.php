<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\AdminAuditLog;
use App\Models\StoreUser;
use App\Support\StoreMailSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * The SMTP settings page (StoreMailSettings). Every field is optional: an empty
 * one falls back to the server's `.env` value. The browser checks a copy of
 * these rules (resources/js/lib/smtpSettingsRules.json, kept equal by
 * SmtpSettingsClientValidationParityTest); `email:rfc` and the `after()`
 * checks stay on the server.
 *
 * No value may hold a line break: the from name and address become mail
 * headers, and the host, username and password go into the SMTP dialogue.
 *
 * Whoever controls these settings can read every email the shop sends, so a
 * save asks for the signed-in staff member's own password (`current_password`
 * on the `store` guard). Wrong passwords are counted per account: after
 * MAX_PASSWORD_FAILURES within PASSWORD_FAILURE_DECAY_SECONDS the field
 * refuses before the password is even checked. Each counted failure is logged
 * (user id and IP) and written to the audit log, so at most
 * MAX_PASSWORD_FAILURES rows per account per window. The password itself is
 * never logged, flashed or saved.
 */
class UpdateSmtpSettingsRequest extends FormRequest
{
    public const array FIELDS = ['mailer', 'host', 'port', 'scheme', 'username', 'password', 'password_clear', 'from_address', 'from_name', 'current_password'];

    /** Wrong current passwords an account may enter per window. */
    public const int MAX_PASSWORD_FAILURES = 5;

    public const int PASSWORD_FAILURE_DECAY_SECONDS = 900;

    /** `D`: `$` matches only at the very end, never before a trailing newline. */
    public const string SINGLE_LINE = 'regex:/^[^\r\n]*$/D';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mailer' => ['nullable', 'string', 'in:'.implode(',', StoreMailSettings::MAILERS)],
            // The server settings are one group: a username, password or port
            // without a host would be silently dropped.
            'host' => ['required_with:port,username,password', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/D'],
            'port' => ['required_with:host', 'nullable', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['nullable', 'string', 'in:'.implode(',', StoreMailSettings::SCHEMES)],
            'username' => ['nullable', 'string', 'max:255', self::SINGLE_LINE],
            'password' => ['nullable', 'string', 'max:255', self::SINGLE_LINE],
            'password_clear' => ['nullable', 'boolean'],
            'from_address' => ['nullable', 'string', 'max:255', 'email:rfc', self::SINGLE_LINE],
            'from_name' => ['nullable', 'string', 'max:255', self::SINGLE_LINE],
            'current_password' => ['required', 'string', 'current_password:store'],
        ];
    }

    /** The limiter key of the signed-in staff member's wrong passwords. */
    public static function passwordLimiterKey(StoreUser $user): string
    {
        return 'smtp-settings-password|'.$user->getAuthIdentifier();
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
                Log::warning('A wrong current password was entered on the SMTP settings page.', ['user_id' => $actor->getKey(), 'ip' => $this->ip()]);
                AdminAuditLog::record($actor, AdminAuditLog::SMTP_SETTINGS_PASSWORD_FAILED, [], $this);
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

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->except(['_token'])), self::FIELDS) as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }

            if ($this->boolean('password_clear') && filled($this->input('password'))) {
                $validator->errors()->add('password', __('validation.prohibited', ['attribute' => 'password']));
            }

            if ($this->keepsPasswordForAnotherServer()) {
                $validator->errors()->add('password', __('store_admin.smtp_password_must_be_entered_again'));
            }
        }];
    }

    /**
     * An empty password keeps the saved one, but only for the host and
     * username it was saved with: otherwise anyone allowed on this page could
     * point the saved password at a server they control.
     */
    private function keepsPasswordForAnotherServer(): bool
    {
        $stored = app(StoreMailSettings::class)->stored();
        $host = $this->input('host');
        $username = $this->input('username');

        if ($stored['password'] === null || $stored['host'] === null || ! is_string($host) || blank($host)
            || filled($this->input('password')) || $this->boolean('password_clear')) {
            return false;
        }

        return mb_strtolower(trim($host)) !== mb_strtolower($stored['host'])
            || (is_string($username) ? trim($username) : '') !== (string) $stored['username'];
    }
}

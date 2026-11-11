<?php

namespace App\Http\Requests\Store\Admin;

use App\Support\StoreMailSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
 */
class UpdateSmtpSettingsRequest extends FormRequest
{
    public const array FIELDS = ['mailer', 'host', 'port', 'scheme', 'username', 'password', 'password_clear', 'from_address', 'from_name'];

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
        ];
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

<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Controllers\Store\Admin\SettingsController;
use App\Support\StoreRecaptchaSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The reCAPTCHA settings page: the on/off switch (`settings[google_recaptcha]`)
 * and the site key and secret (StoreRecaptchaSettings). The key and secret are
 * one optional pair: both empty falls back to the server's `.env` pair. The
 * browser checks a copy of these rules (resources/js/lib/recaptchaSettingsRules.json,
 * kept equal by RecaptchaSettingsClientValidationParityTest); the `after()`
 * checks read the saved settings, so they stay on the server.
 *
 * The secret is never validated against a pattern (only single-line, up to 255):
 * it is sent to Google, not printed. It is never part of an error message.
 */
class UpdateRecaptchaSettingsRequest extends FormRequest
{
    public const array FIELDS = ['recaptcha_site_key', 'recaptcha_secret_key', 'recaptcha_secret_clear'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:'.implode(',', SettingsController::PAGES['recaptcha']['keys']), 'min:1'],
            'settings.*' => ['required', 'boolean'],
            // The key and the secret are a pair: a secret without a key would be silently dropped.
            'recaptcha_site_key' => ['required_with:recaptcha_secret_key', 'nullable', 'string', 'max:100', 'regex:'.StoreRecaptchaSettings::SITE_KEY_PATTERN],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:255', UpdateSmtpSettingsRequest::SINGLE_LINE],
            'recaptcha_secret_clear' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['settings', '_token', '_method', ...self::FIELDS];

            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add('settings', __('validation.prohibited', ['attribute' => 'settings']));
            }

            if (! $this->has('recaptcha_site_key')) {
                return;
            }

            if ($this->boolean('recaptcha_secret_clear') && filled($this->input('recaptcha_secret_key'))) {
                $validator->errors()->add('recaptcha_secret_key', __('validation.prohibited', ['attribute' => 'recaptcha_secret_key']));
            }

            if ($this->needsSecret()) {
                $validator->errors()->add('recaptcha_secret_key', __('store_admin.recaptcha_secret_must_be_entered'));
            }
        }];
    }

    /**
     * A site key needs its secret: entered now when none is saved, the saved
     * one cannot be read, or the key changed (a saved secret belongs to the key
     * it was saved with, so it is never kept for another one). Ticking "remove
     * the saved secret" is a deliberate choice and is left alone: the pair then
     * refuses every form until a secret is entered, and the page says so.
     */
    private function needsSecret(): bool
    {
        $siteKey = $this->input('recaptcha_site_key');

        if (! is_string($siteKey) || blank($siteKey) || filled($this->input('recaptcha_secret_key')) || $this->boolean('recaptcha_secret_clear')) {
            return false;
        }

        $settings = app(StoreRecaptchaSettings::class);
        $stored = $settings->stored();

        return $stored['secret'] === null
            || $stored['site_key'] === null
            || trim($siteKey) !== $stored['site_key']
            || $settings->secretUnreadable();
    }
}

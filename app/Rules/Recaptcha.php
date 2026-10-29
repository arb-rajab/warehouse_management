<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The Google reCAPTCHA v2 token a storefront form posts as
 * `g-recaptcha-response`, verified with Google using
 * services.recaptcha.secret_key (CAPTCHA_SECRET).
 *
 * It fails closed: a missing secret, an error or a non-answer from Google, or
 * no connection refuses the form as a wrong answer does, so an outage or a
 * missing key never turns the check off silently. Neither the secret nor the
 * token is ever logged.
 */
class Recaptcha implements ValidationRule
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * The token's rules: required and verified while the `google_recaptcha`
     * setting is on (the forms show the widget exactly then), none otherwise.
     *
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return get_setting('google_recaptcha') == 1 ? ['bail', 'required', 'string', new self] : [];
    }

    /**
     * The messages for a missing token or one that is not text.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $message = __('validation.recaptcha.confirm_human');

        return ['g-recaptcha-response.required' => $message, 'g-recaptcha-response.string' => $message];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (! is_string($secret) || $secret === '') {
            Log::warning('reCAPTCHA is on but CAPTCHA_SECRET is not set: every form that shows it is refused.');
            $fail(__('validation.recaptcha.robot_check_failed'));

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch (ConnectionException) {
            Log::warning('reCAPTCHA verification could not reach Google.');
            $fail(__('validation.recaptcha.robot_check_failed'));

            return;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            Log::warning('reCAPTCHA verification failed at Google.', ['status' => $response->status()]);
            $fail(__('validation.recaptcha.robot_check_failed'));

            return;
        }

        if ($response->json('success') !== true) {
            $fail(__('validation.recaptcha.confirm_human'));
        }
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Password reset by code (shop.auth.passwords.reset, posting to
 * HomeController::reset_password_with_code): the account's email as the
 * registration forms take it (users.email is varchar(191)), the six-digit code
 * StoreVerificationCodes issued, and the new password, at least six characters
 * and confirmed. The input is checked before the code is tried, so a malformed
 * request never spends one of the code's attempts; the store-auth throttle
 * counts it all the same.
 */
class ResetPasswordWithCodeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:191'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    /**
     * Shows the form again with the errors, as the endpoint's other refusals
     * do: the form is only ever rendered in answer to a POST (the forgot-password
     * request), so "back" would be a URL with no GET route.
     */
    protected function failedValidation(Validator $validator): void
    {
        $kept = fn (string $field): ?string => is_string($this->input($field)) ? $this->input($field) : null;

        throw new HttpResponseException(response(
            view('shop.auth.passwords.reset', ['email' => $kept('email'), 'code' => $kept('code')])->withErrors($validator),
            422,
        ));
    }
}

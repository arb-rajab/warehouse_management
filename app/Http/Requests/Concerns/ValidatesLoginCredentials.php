<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that authenticate with an email and password.
 */
trait ValidatesLoginCredentials
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function loginCredentialRules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}

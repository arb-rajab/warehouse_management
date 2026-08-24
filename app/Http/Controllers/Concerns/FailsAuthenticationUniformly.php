<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Shared login-failure response for the admin (session) and mobile (token)
 * auth entry points, so both reveal neither which credential was wrong nor
 * that the account exists but lacks admin access.
 */
trait FailsAuthenticationUniformly
{
    private function failAuthentication(): never
    {
        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }
}

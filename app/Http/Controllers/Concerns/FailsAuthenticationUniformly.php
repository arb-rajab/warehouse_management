<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared login-failure response for the admin (session) and mobile (token)
 * auth entry points, so both reveal neither which credential was wrong nor
 * that the account exists but lacks admin access.
 */
trait FailsAuthenticationUniformly
{
    /**
     * Look up a user by email and verify the password, always performing a
     * hash comparison (against a freshly hashed random value when no user
     * matches) so the timing is the same whether the account exists or not.
     */
    private function findUserOrFailUniformly(string $email, string $password): User
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'password'])
            ->where('email', $email)
            ->first();

        if (! Hash::check($password, $user->password ?? Hash::make(Str::random(40)))) {
            $this->failAuthentication();
        }

        return $user;
    }

    private function failAuthentication(): never
    {
        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }
}

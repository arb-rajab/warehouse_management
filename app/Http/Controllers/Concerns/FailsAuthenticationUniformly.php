<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Shared login-failure response for the admin (session) and mobile (token)
 * auth entry points, so both reveal neither which credential was wrong nor
 * that the account exists but lacks admin access.
 */
trait FailsAuthenticationUniformly
{
    /**
     * A precomputed hash of an unknown, random value. Comparing against this
     * when no user is found keeps the Hash::check() cost identical to the
     * "user exists, wrong password" path, so a missing account can't be
     * distinguished from a wrong password by response timing.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$513PoLubdXrnlmjpAFyfY.bILqf/ofQdXnsSvojzdLJsr.ujkS7zC';

    /**
     * Look up a user by email and verify the password, always performing a
     * hash comparison (against a dummy hash when no user matches) so the
     * timing is the same whether the account exists or not.
     */
    private function findUserOrFailUniformly(string $email, string $password): User
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'password'])
            ->where('email', $email)
            ->first();

        if (! Hash::check($password, $user->password ?? self::DUMMY_PASSWORD_HASH)) {
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

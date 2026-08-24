<?php

namespace App\Http\Requests;

use App\Http\Controllers\Concerns\FailsAuthenticationUniformly;
use App\Http\Requests\Concerns\ValidatesLoginCredentials;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LoginRequest extends FormRequest
{
    use FailsAuthenticationUniformly;
    use ValidatesLoginCredentials;

    /**
     * Attempt to authenticate the request's credentials against the web guard.
     */
    public function authenticate(): void
    {
        if (! Auth::guard('web')->attempt($this->only('email', 'password'))) {
            $this->failAuthentication();
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->isAdmin()) {
            Auth::guard('web')->logout();

            $this->failAuthentication();
        }
    }
}

<?php

namespace App\Http\Requests;

use App\Http\Controllers\Concerns\FailsAuthenticationUniformly;
use App\Http\Requests\Concerns\ValidatesLoginCredentials;
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
        $user = $this->findUserOrFailUniformly($this->input('email'), $this->input('password'));

        if (! $user->isAdmin()) {
            $this->failAuthentication();
        }

        Auth::guard('web')->login($user);
    }
}

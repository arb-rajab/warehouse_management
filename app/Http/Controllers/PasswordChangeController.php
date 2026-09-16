<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Auth/ChangePassword');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->password = $request->validated('password');
        $user->must_change_password = false;
        $user->save();

        return redirect()->route('admin.dashboard');
    }
}

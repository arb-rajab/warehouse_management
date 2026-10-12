<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $this->revokeOtherLogins($request, $user);

        return redirect()->route('admin.dashboard');
    }

    /**
     * Signs the user out everywhere but here, so a stolen token or session
     * does not survive the password change that was meant to lock it out.
     *
     * Every Sanctum token goes (this request is a session, not a token), and so
     * does every other row of the session table that carries the user's id. That
     * table is also the store's, which writes its own user's id there, so a
     * store customer with this id is signed out too — a rare, harmless cost.
     * Other session drivers hold no user id to match, and are left alone.
     */
    private function revokeOtherLogins(Request $request, User $user): void
    {
        $user->tokens()->delete();

        DB::table((string) config('session.table'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function index(): View
    {
        return view('shop.backend.admin_profile.index');
    }

    /**
     * The signed-in account's own profile only (AdminProfileRequest refuses
     * any other id in the URL).
     */
    public function update(AdminProfileRequest $request): RedirectResponse
    {
        if (config('store.demo_mode')) {
            flash(translate('Sorry! the action is not permitted in demo '))->error();

            return back();
        }

        $user = $request->user('store');
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        if ($request->filled('new_password')) {
            $user->password = $request->validated('new_password');
        }
        $user->avatar_original = $request->validated('avatar');
        $user->save();

        flash(translate('Your Profile has been updated successfully!'))->success();

        return back();
    }
}

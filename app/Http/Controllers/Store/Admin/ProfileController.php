<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateProfileRequest;
use App\Http\Resources\Store\UploadResource;
use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller implements HasMiddleware
{
    /** @return array<int, string> */
    public static function middleware(): array
    {
        return [];
    }

    public function index(): Response
    {
        $user = request()->user('store');
        $avatar = Upload::query()->select(['id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])
            ->where('type', 'image')->find($user->avatar_original);

        return Inertia::render('Store/Admin/Profile/Index', [
            'profile' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'avatar' => $avatar === null ? null : new UploadResource($avatar)],
        ]);
    }

    /**
     * The signed-in account's own profile only (UpdateProfileRequest refuses
     * any other id in the URL).
     */
    public function update(UpdateProfileRequest $request, StoreUser $profile): RedirectResponse
    {
        abort_if(config('store.demo_mode'), 403);

        $user = $request->user('store');
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');
        if ($request->filled('new_password')) {
            $user->password = $request->validated('new_password');
        }
        $user->avatar_original = $request->validated('avatar');
        $user->save();

        return redirect()->route('profile.index');
    }
}

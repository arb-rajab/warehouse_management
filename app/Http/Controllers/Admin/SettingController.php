<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FlashesAdminSuccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Row;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    use FlashesAdminSuccess;

    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'setting' => Setting::current()->only(['qr_code_width', 'qr_code_height', 'allowed_open_levels']),
            'highestLevel' => Row::highestFlatsCount(),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        Setting::current()->update($request->validated());
        $this->flashAdminSuccess('settings_have_been_updated_successfully');

        return redirect()->route('admin.settings.edit');
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Concerns\UpdatesStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateGeneralSettingsRequest;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller implements HasMiddleware
{
    use ProvidesStoreUploadPreviews;
    use UpdatesStoreSettings;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:general_settings')];
    }

    public function edit(Request $request): Response
    {
        $settings = ['site_name' => (string) get_setting('site_name', '')];
        $initialFiles = [];
        foreach (UpdateGeneralSettingsRequest::IMAGES as $key) {
            $id = (string) get_setting($key, '');
            $upload = ctype_digit($id) ? Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find((int) $id) : null;
            $settings[$key] = $id;
            $initialFiles[$key] = $this->uploadPreviews($request, $upload);
        }

        return Inertia::render('Store/Admin/Setup/GeneralSettings/Index', [
            'settings' => [...$settings, 'timezone' => app_timezone()],
            'timezones' => array_values(timezones()),
            'initialFiles' => $initialFiles,
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->updateSettings(Arr::except($request->validated('settings'), 'timezone'));

        return redirect()->route('general_setting.index');
    }
}

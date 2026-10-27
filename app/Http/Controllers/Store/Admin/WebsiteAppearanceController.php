<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateLegacyWebsiteScriptsRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The storefront's appearance settings. The header and footer scripts are
 * shown here as stored and saved through LegacyWebsiteScriptsController,
 * unchanged (.ai/archive/store-backoffice/changes-20261013.md §20).
 */
class WebsiteAppearanceController extends Controller implements HasMiddleware
{
    use ProvidesStoreUploadPreviews;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:website_appearance')];
    }

    public function edit(Request $request, WebsiteSettings $settings): Response
    {
        $keys = UpdateWebsiteAppearanceRequest::enabledKeys();
        $values = $settings->read($keys, [], UpdateWebsiteAppearanceRequest::SWITCHES);
        foreach (UpdateWebsiteAppearanceRequest::RICH_TEXT as $key) {
            $values[$key] = clean_html($values[$key]);
        }

        return Inertia::render('Store/Admin/Website/Appearance', [
            'settings' => $values,
            'scripts' => $settings->read(UpdateLegacyWebsiteScriptsRequest::KEYS),
            'initialFiles' => $this->settingsImagePreviews($request, array_values(array_intersect(UpdateWebsiteAppearanceRequest::IMAGES, $keys))),
        ]);
    }

    public function update(UpdateWebsiteAppearanceRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $settings->write($request->validated('settings'), UpdateWebsiteAppearanceRequest::SWITCHES);

        return redirect()->route('website.appearance');
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateWebsiteScriptsRequest;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteScriptsController extends Controller implements HasMiddleware
{
    public const SWITCHES = [];

    public const LOCALIZED = ['about_us_description', 'contact_address', 'widget_one', 'widget_one_labels', 'frontend_copyright_text'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_website_scripts', only: ['edit', 'update'])];
    }

    public function edit(Request $request, WebsiteSettings $settings): Response
    {

        $values = $settings->read(UpdateWebsiteScriptsRequest::KEYS, [], self::SWITCHES);

        return Inertia::render('Store/Admin/Website/Scripts', ['settings' => $values,
        ]);
    }

    public function update(UpdateWebsiteScriptsRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $values = $request->validated('settings');
        $settings->write($values, self::SWITCHES);

        return redirect()->route('website.scripts');
    }
}

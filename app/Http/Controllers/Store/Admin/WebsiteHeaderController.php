<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateWebsiteHeaderRequest;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteHeaderController extends Controller implements HasMiddleware
{
    public const SWITCHES = ['show_language_switcher', 'show_currency_switcher', 'header_stikcy'];

    public const ARRAYS = ['header_menu_labels', 'header_menu_links'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:header_setup', only: ['edit', 'update'])];
    }

    public function edit(WebsiteSettings $settings): Response
    {
        return Inertia::render('Store/Admin/Website/Header', [
            'settings' => $settings->read(UpdateWebsiteHeaderRequest::KEYS, self::ARRAYS, self::SWITCHES),
        ]);
    }

    public function update(UpdateWebsiteHeaderRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $settings->write($request->validated('settings'), self::SWITCHES);

        return redirect()->route('website.header');
    }
}

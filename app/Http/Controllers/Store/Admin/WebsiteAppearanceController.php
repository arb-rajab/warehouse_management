<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateWebsiteAppearanceRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteScriptsRequest;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteAppearanceController extends Controller implements HasMiddleware
{
    public const SWITCHES = ['show_cookies_agreement', 'show_website_popup', 'show_subscribe_form'];

    public const LOCALIZED = ['about_us_description', 'contact_address', 'widget_one', 'widget_one_labels', 'frontend_copyright_text'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:website_appearance', only: ['edit', 'update'])];
    }

    public function edit(Request $request, WebsiteSettings $settings): Response
    {

        $values = $settings->read(UpdateWebsiteAppearanceRequest::KEYS, [], self::SWITCHES);
        $values['cookies_agreement_text'] = clean_html($values['cookies_agreement_text']);
        $values['website_popup_content'] = clean_html($values['website_popup_content']);

        return Inertia::render('Store/Admin/Website/Appearance', ['settings' => $values,
            'scripts' => $request->user('store')->can('manage_website_scripts') ? $settings->read(UpdateWebsiteScriptsRequest::KEYS) : null,
        ]);
    }

    public function update(UpdateWebsiteAppearanceRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $values = $request->validated('settings');
        $values['cookies_agreement_text'] = clean_html($values['cookies_agreement_text']);
        $values['website_popup_content'] = clean_html($values['website_popup_content']);
        $settings->write($values, self::SWITCHES);

        return redirect()->route('website.appearance');
    }
}

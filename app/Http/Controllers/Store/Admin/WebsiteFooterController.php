<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditWebsiteFooterRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest;
use App\Models\Language;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteFooterController extends Controller implements HasMiddleware
{
    public const SWITCHES = ['show_social_links', 'registration_tutorial_button'];

    public const LOCALIZED = ['about_us_description', 'contact_address', 'widget_one', 'widget_one_labels', 'frontend_copyright_text'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:footer_setup', only: ['edit', 'update'])];
    }

    public function edit(EditWebsiteFooterRequest $request, WebsiteSettings $settings): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $values = $settings->read(UpdateWebsiteFooterRequest::KEYS, ['widget_one_labels', 'widget_one_links'], self::SWITCHES, $lang, self::LOCALIZED);
        $values['about_us_description'] = clean_html($values['about_us_description']);
        $values['frontend_copyright_text'] = clean_html($values['frontend_copyright_text']);

        return Inertia::render('Store/Admin/Website/Footer', ['settings' => $values,
            'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(),
            'lang' => $lang,
        ]);
    }

    public function update(UpdateWebsiteFooterRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $values = $request->validated('settings');
        $values['about_us_description'] = clean_html($values['about_us_description']);
        $values['frontend_copyright_text'] = clean_html($values['frontend_copyright_text']);
        $settings->write($values, self::SWITCHES, $request->validated('lang'), self::LOCALIZED);

        return redirect()->route('website.footer', ['lang' => $request->validated('lang')]);
    }
}

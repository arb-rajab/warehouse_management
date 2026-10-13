<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\UpdateWebsiteFooterRequest;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The storefront footer's settings, one language at a time for the
 * translatable texts (.ai/rules/store-backoffice-migration.md §20).
 */
class WebsiteFooterController extends Controller implements HasMiddleware
{
    use ProvidesStoreReferenceOptions;
    use ProvidesStoreUploadPreviews;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:footer_setup')];
    }

    /**
     * Without a `lang`, the footer opens in the back office's current language,
     * as the Blade sidebar linked it.
     */
    public function edit(EditReferenceTranslationRequest $request, WebsiteSettings $settings): Response
    {
        $languages = $this->languageOptions();
        $locale = app()->getLocale();
        $lang = $request->validated('lang') ?? ($languages->contains('code', $locale) ? $locale : config('app.default_language'));
        $values = $settings->read(UpdateWebsiteFooterRequest::enabledKeys(), UpdateWebsiteFooterRequest::ARRAYS, ['show_social_links'], $lang, UpdateWebsiteFooterRequest::LOCALIZED);
        foreach (UpdateWebsiteFooterRequest::RICH_TEXT as $key) {
            $values[$key] = clean_html($values[$key]);
        }
        // The Blade form ticked the tutorial switch for any stored value, the
        // social links one only for `on`.
        $values['registration_tutorial_button'] = (bool) get_setting('registration_tutorial_button');
        // One row per label of this language, each with the shared link at
        // its index, as the Blade form listed them.
        $values['widget_one_links'] = array_pad(array_slice($values['widget_one_links'], 0, count($values['widget_one_labels'])), count($values['widget_one_labels']), '');

        return Inertia::render('Store/Admin/Website/Footer', [
            'settings' => $values,
            'lang' => $lang,
            'languages' => $languages,
            'initialFiles' => $this->settingsImagePreviews($request, ['footer_logo', 'payment_method_images']),
        ]);
    }

    public function update(UpdateWebsiteFooterRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $lang = $request->validated('lang');
        $settings->write($request->validated('settings'), UpdateWebsiteFooterRequest::SWITCHES, $lang, UpdateWebsiteFooterRequest::LOCALIZED);

        return redirect()->route('website.footer', ['lang' => $lang]);
    }
}

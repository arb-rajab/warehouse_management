<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Support\StoreLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    /** One year, in minutes. */
    private const int COOKIE_MINUTES = 525600;

    public function changeLanguage(Request $request): Response|RedirectResponse
    {
        $language = Language::query()->select(['name', 'code'])->where('code', $request->input('locale'))->first();
        if ($language === null) {
            flash(__('storefront.common.language_not_found'))->error();

            return back();
        }
        $request->session()->put('locale', $language->code);
        flash(__('storefront.common.language_changed', ['language' => $language->name]))->success();

        return response('');
    }

    /**
     * The storefront header's Arabic/English toggle. A plain form POST (CSRF via
     * the web group), so it works without the theme's JavaScript.
     */
    public function toggleLanguage(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', Rule::in(StoreLocale::TOGGLE_LOCALES)],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ]);

        $code = StoreLocale::contentCodeFor($request->string('locale')->value());
        $request->session()->put('locale', $code);
        $redirect = $request->input('redirect');

        return redirect()->to($this->sameOriginPath(is_string($redirect) ? $redirect : null))
            ->withCookie(cookie(StoreLocale::COOKIE, $code, self::COOKIE_MINUTES));
    }

    public function getTranslation(string $check): Response
    {
        return response('');
    }

    /**
     * Only a local absolute path is followed; anything that could name another
     * host (`//host`, `/\host`, a scheme, control characters) goes home instead.
     */
    private function sameOriginPath(?string $path): string
    {
        if ($path === null
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || parse_url($path, PHP_URL_HOST) !== null
            || parse_url($path, PHP_URL_SCHEME) !== null) {
            return route('home');
        }

        return url($path);
    }
}

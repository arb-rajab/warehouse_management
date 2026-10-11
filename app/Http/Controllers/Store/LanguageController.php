<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LanguageController extends Controller
{
    public function changeLanguage(Request $request): Response|RedirectResponse
    {
        $language = Language::query()->select(['name', 'code'])->where('code', $request->input('locale'))->first();
        if ($language === null) {
            flash(translate('Language not found'))->error();

            return back();
        }
        $request->session()->put('locale', $language->code);
        flash(translate('Language changed to ').$language->name)->success();

        return response('');
    }

    public function getTranslation(string $check): Response
    {
        return response('');
    }
}

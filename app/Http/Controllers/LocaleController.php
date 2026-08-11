<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::tryFrom($locale) !== null, 404);

        $request->session()->put('locale', $locale);

        return redirect()->back();
    }
}

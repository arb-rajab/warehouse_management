<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Requests\Store\Admin\UpdateLegacyWebsiteScriptsRequest;
use App\Services\Store\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Narrow compatibility for the retained appearance form; rendering policy is unchanged. */
class LegacyWebsiteScriptsController extends Controller implements HasMiddleware
{
    use FlashesSuccess;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:website_appearance')];
    }

    public function update(UpdateLegacyWebsiteScriptsRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        $settings->write($request->safe()->only(UpdateLegacyWebsiteScriptsRequest::KEYS));
        $this->flashSuccess('website_scripts_have_been_updated_successfully');

        return redirect()->route('website.appearance');
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateWebsiteHomeRequest;
use App\Models\Page;
use App\Services\Store\WebsiteSettings;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class WebsiteHomeController extends Controller implements HasMiddleware
{
    private const ARRAYS = ['home_slider_images', 'home_slider_links', 'home_categories'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:edit_website_page', only: ['edit', 'update'])];
    }

    public function edit(WebsiteSettings $settings): Response
    {
        Page::query()->select(['id'])->where('slug', 'home')->where('type', 'home_page')->firstOrFail();

        return Inertia::render('Store/Admin/Website/Home', [
            'settings' => $settings->read(UpdateWebsiteHomeRequest::KEYS, self::ARRAYS),
            'couponEnabled' => (bool) get_setting('coupon_system'),
            'categories' => DB::table('categories')->leftJoin('category_translations', function (JoinClause $join) {
                $join->on('category_translations.category_id', '=', 'categories.id')->where('category_translations.lang', app()->getLocale());
            })->select(['categories.id', 'categories.name', 'category_translations.name as translated_name'])->orderBy('categories.name')->orderBy('categories.id')->get()->map(fn (stdClass $category) => ['id' => $category->id, 'name' => $category->translated_name ?? $category->name])->all(),
        ]);
    }

    public function update(UpdateWebsiteHomeRequest $request, WebsiteSettings $settings): RedirectResponse
    {
        Page::query()->select(['id'])->where('slug', 'home')->where('type', 'home_page')->firstOrFail();
        $settings->write($request->validated('settings'));

        return redirect()->route('website.home.edit');
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditWebsiteFooterRequest;
use App\Http\Requests\Store\Admin\FilterWebsitePagesRequest;
use App\Http\Requests\Store\Admin\StoreWebsitePageRequest;
use App\Http\Requests\Store\Admin\UpdateWebsitePageRequest;
use App\Http\Resources\Store\WebsitePageResource;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WebsitePageController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_website_pages', only: ['index']),
            new Middleware('permission:add_website_page', only: ['create', 'store']),
            new Middleware('permission:edit_website_page', only: ['edit', 'update']),
            new Middleware('permission:delete_website_page', only: ['destroy']),
        ];
    }

    public function index(FilterWebsitePagesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Website/Pages/Index', [
            'pages' => $this->paginated(WebsitePageResource::collection(Page::query()->select(['id', 'title', 'slug', 'type'])->with('page_translations:id,page_id,lang,title')->orderBy('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage],
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Website/Pages/Create', ['lang' => config('app.default_language'), 'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get()]);
    }

    public function store(StoreWebsitePageRequest $request): RedirectResponse
    {
        abort_unless($request->validated('lang') === config('app.default_language'), 403);
        DB::transaction(function () use ($request) {
            $values = $request->safe()->except('lang');
            $values['content'] = clean_html($values['content']);
            $page = Page::forceCreate([...$values, 'type' => 'custom_page']);
            PageTranslation::query()->create(['page_id' => $page->id, 'lang' => $request->validated('lang'), 'title' => $values['title'], 'content' => $values['content']]);
        });

        return redirect()->route('website.pages');
    }

    public function edit(EditWebsiteFooterRequest $request, Page $page): Response|HttpResponse
    {
        if ($page->type === 'home_page') {
            return Inertia::location(route('website.home.edit'));
        }
        $lang = $request->validated('lang') ?? config('app.default_language');
        $translation = $page->page_translations()->select(['id', 'page_id', 'lang', 'title', 'content'])->where('lang', $lang)->first();

        return Inertia::render('Store/Admin/Website/Pages/Edit', [
            'page' => ['id' => $page->id, 'type' => $page->type, 'title' => $translation->title ?? $page->title, 'slug' => $page->slug, 'content' => clean_html($translation->content ?? $page->content), 'meta_title' => $page->meta_title ?? '', 'meta_description' => $page->meta_description ?? '', 'keywords' => $page->keywords ?? '', 'meta_image' => (string) ($page->meta_image ?? '')],
            'lang' => $lang, 'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateWebsitePageRequest $request, Page $page): RedirectResponse
    {
        abort_if($page->type === 'home_page', 403);
        abort_if($page->type !== 'custom_page' && $request->validated('slug') !== $page->slug, 403);
        DB::transaction(function () use ($request, $page) {
            $values = $request->safe()->except('lang');
            $lang = $request->validated('lang');
            $values['content'] = clean_html($values['content']);
            PageTranslation::query()->updateOrCreate(['page_id' => $page->id, 'lang' => $lang], ['title' => $values['title'], 'content' => $values['content']]);
            if ($lang !== config('app.default_language')) {
                unset($values['title'], $values['content']);
            }
            $page->forceFill($values)->save();
        });

        return redirect()->route('website.pages');
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        abort_unless($page->type === 'custom_page', 403);
        DB::transaction(function () use ($page) {
            $page->page_translations()->delete();
            $page->delete();
        });

        return $this->redirectPreservingQuery('website.pages', $request);
    }
}

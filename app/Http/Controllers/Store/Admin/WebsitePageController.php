<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterWebsitePagesRequest;
use App\Http\Requests\Store\Admin\StoreWebsitePageRequest;
use App\Http\Requests\Store\Admin\UpdateWebsitePageRequest;
use App\Http\Resources\Store\WebsitePageFormResource;
use App\Http\Resources\Store\WebsitePageResource;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The storefront's website pages: custom pages, the policy pages and the home
 * page row, whose own settings are WebsiteHomeController's. The storefront
 * renders them through root PageController, unchanged
 * (.ai/rules/store-backoffice-migration.md §20).
 */
class WebsitePageController extends Controller implements HasMiddleware
{
    use ProvidesStoreReferenceOptions;
    use ProvidesStoreUploadPreviews;

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
        $pages = Page::query()->select(['id', 'type', 'title', 'slug'])
            ->with(['page_translations' => fn (Relation $query) => $query->select(['id', 'page_id', 'lang', 'title'])->where('lang', app()->getLocale())])
            ->orderBy('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Store/Admin/Website/Pages/Index', [
            'pages' => $this->paginated(WebsitePageResource::collection($pages)),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Website/Pages/Create', ['homeUrl' => route('home')]);
    }

    /** A new page is a custom page, written in the default language. */
    public function store(StoreWebsitePageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $page = new Page;
            $page->type = 'custom_page';
            $page->slug = $data['slug'];
            $page->title = $data['title'];
            $page->content = $data['content'];
            $this->fillMeta($page, $data);
            $page->save();
            $this->saveTranslation($page, config('app.default_language'), $data['title'], $data['content']);
        });

        return redirect()->route('website.pages');
    }

    /** The home page row opens its own settings, as the Blade list sent it there. */
    public function edit(EditReferenceTranslationRequest $request, Page $page): Response|RedirectResponse
    {
        if ($page->type === 'home_page') {
            return redirect()->route('website.home.edit');
        }
        $lang = $request->validated('lang') ?? config('app.default_language');
        $translation = PageTranslation::query()->select(['id', 'page_id', 'lang', 'title', 'content'])
            ->where('page_id', $page->id)->where('lang', $lang)->first();
        $metaImage = $page->meta_image === null ? null : Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find($page->meta_image);

        return Inertia::render('Store/Admin/Website/Pages/Edit', [
            'page' => new WebsitePageFormResource($page),
            'title' => $translation->title ?? $page->title ?? '',
            'content' => clean_html($translation->content ?? $page->content),
            'lang' => $lang,
            'languages' => $this->languageOptions(),
            'homeUrl' => route('home'),
            'initialFiles' => ['meta_image' => $this->uploadPreviews($request, $metaImage)],
        ]);
    }

    /**
     * As the Blade writer did: the slug changes only on a custom page, the
     * base title and content only in the default language, the meta fields
     * on every save, and the language's translation is written or replaced.
     */
    public function update(UpdateWebsitePageRequest $request, Page $page): RedirectResponse
    {
        abort_if($page->type === 'home_page', 403);
        $data = $request->validated();
        DB::transaction(function () use ($page, $data): void {
            $page = Page::query()->lockForUpdate()->findOrFail($page->id);
            if ($page->type === 'custom_page') {
                $page->slug = $data['slug'];
            }
            if ($data['lang'] === config('app.default_language')) {
                $page->title = $data['title'];
                $page->content = $data['content'];
            }
            $this->fillMeta($page, $data);
            $page->save();
            $this->saveTranslation($page, $data['lang'], $data['title'], $data['content']);
        });

        return redirect()->route('website.pages');
    }

    /** Only a custom page can be deleted, as only its row offered it. */
    public function destroy(Request $request, Page $page): RedirectResponse
    {
        abort_unless($page->type === 'custom_page', 403);
        DB::transaction(function () use ($page): void {
            $page = Page::query()->lockForUpdate()->findOrFail($page->id);
            $page->page_translations()->delete();
            $page->delete();
        });

        return $this->redirectPreservingQuery('website.pages', $request);
    }

    /** @param array<string, mixed> $data */
    private function fillMeta(Page $page, array $data): void
    {
        $page->meta_title = $data['meta_title'] ?? null;
        $page->meta_description = $data['meta_description'] ?? null;
        $page->keywords = $data['keywords'] ?? null;
        $page->meta_image = isset($data['meta_image']) ? (string) $data['meta_image'] : null;
    }

    private function saveTranslation(Page $page, string $lang, string $title, string $content): void
    {
        $translation = PageTranslation::query()->firstOrNew(['lang' => $lang, 'page_id' => $page->id]);
        $translation->title = $title;
        $translation->content = $content;
        $translation->save();
    }
}

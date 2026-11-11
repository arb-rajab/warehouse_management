<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\ReadsHighlight;
use App\Http\Requests\Store\Admin\FilterLanguagesRequest;
use App\Http\Requests\Store\Admin\ImportAppTranslationsRequest;
use App\Http\Requests\Store\Admin\StoreLanguageRequest;
use App\Http\Requests\Store\Admin\UpdateDefaultLanguageRequest;
use App\Http\Requests\Store\Admin\UpdateLanguageRequest;
use App\Http\Requests\Store\Admin\UpdateLanguageStatusRequest;
use App\Http\Requests\Store\Admin\UpdateTranslationsRequest;
use App\Http\Resources\Store\LanguageResource;
use App\Models\Language;
use App\Support\NewestFirst;
use App\Support\StoreDefaultLanguage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LanguageController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use ReadsHighlight;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:language_setup')];
    }

    public function index(FilterLanguagesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Languages/Index', [
            'languages' => $this->paginated(LanguageResource::collection(Language::query()
                ->select(['id', 'name', 'code', 'app_lang_code', 'rtl', 'status'])
                ->tap(NewestFirst::apply(...))->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage],
            'defaultLanguage' => $this->defaultCode(),
            'defaultLanguageSetting' => $this->defaultLanguageSetting(),
            'highlight' => $this->highlight($request, ['default_language']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Languages/Create');
    }

    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        $language = new Language;
        $language->forceFill($request->validated())->save();
        Cache::forget('app.languages');
        $this->flashSuccess('language_has_been_created_successfully');

        return redirect()->route('languages.index');
    }

    public function edit(Language $language): Response
    {
        return Inertia::render('Store/Admin/Languages/Edit', ['language' => new LanguageResource($language)]);
    }

    public function update(UpdateLanguageRequest $request, Language $language): RedirectResponse
    {
        $language->forceFill($request->validated())->save();
        Cache::forget('app.languages');
        $this->flashSuccess('language_has_been_updated_successfully');

        return redirect()->route('languages.index');
    }

    public function destroy(Request $request, Language $language): RedirectResponse
    {
        abort_if(in_array($language->code, ['en', $this->defaultCode()], true), 403);
        if ($request->session()->get('locale') === $language->code) {
            $request->session()->put('locale', $this->defaultCode());
        }
        $language->delete();
        Cache::forget('app.languages');
        $this->flashSuccess('language_has_been_deleted_successfully');

        return $this->redirectPreservingQuery('languages.index', $request);
    }

    public function updateStatus(UpdateLanguageStatusRequest $request): RedirectResponse
    {
        $language = Language::query()->findOrFail($request->integer('id'));
        abort_if($language->code === $this->defaultCode() && ! $request->boolean('status'), 403);
        $language->status = $request->boolean('status');
        $language->save();
        Cache::forget('app.languages');
        $this->flashSuccess('language_status_has_been_updated_successfully');

        return $this->redirectPreservingQuery('languages.index', $request);
    }

    /**
     * Choose the default content language (StoreDefaultLanguage): an existing,
     * active language, or empty to use the server's DEFAULT_LANGUAGE again.
     */
    public function updateDefault(UpdateDefaultLanguageRequest $request): RedirectResponse
    {
        app(StoreDefaultLanguage::class)->save($request->validated('default_language'));
        $this->flashSuccess('default_language_has_been_updated_successfully');

        return redirect()->route('languages.index');
    }

    public function updateRtlStatus(UpdateLanguageStatusRequest $request): RedirectResponse
    {
        $language = Language::query()->findOrFail($request->integer('id'));
        $language->rtl = (int) $request->boolean('status');
        $language->save();
        Cache::forget('app.languages');
        $this->flashSuccess('language_rtl_status_has_been_updated_successfully');

        return $this->redirectPreservingQuery('languages.index', $request);
    }

    public function show(FilterLanguagesRequest $request, Language $language): Response
    {
        return $this->translationPage($request, $language, false);
    }

    public function showAppTranslations(FilterLanguagesRequest $request, Language $language): Response
    {
        return $this->translationPage($request, $language, true);
    }

    public function storeTranslations(UpdateTranslationsRequest $request): RedirectResponse
    {
        return $this->saveTranslations($request, false);
    }

    public function storeAppTranslations(UpdateTranslationsRequest $request): RedirectResponse
    {
        return $this->saveTranslations($request, true);
    }

    public function importAppTranslations(ImportAppTranslationsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->translations() as $key => $value) {
                $this->writeTranslation('app_translations', 'en', $key, $value);
            }
        });
        $this->flashSuccess('app_translations_have_been_imported_successfully');

        return redirect()->route('languages.index');
    }

    public function exportAppTranslations(Language $language): StreamedResponse
    {
        abort_if(blank($language->app_lang_code), 422);
        $contents = DB::table('app_translations')->where('lang', $language->app_lang_code)
            ->orderBy('id')->pluck('lang_value', 'lang_key')->toJson(JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        },
            'app_'.preg_replace('/[^a-zA-Z0-9_-]/', '', $language->app_lang_code ?? '').'.arb', ['Content-Type' => 'application/json']);
    }

    /** The effective default language code (saved in the admin, else DEFAULT_LANGUAGE). */
    private function defaultCode(): ?string
    {
        return app(StoreDefaultLanguage::class)->get();
    }

    /**
     * What the default-language control shows: the saved code, the one in use
     * and its source, the `.env` code, the state of the one in use, and the
     * choices (active languages, plus the saved language when it is switched
     * off or gone, so the page shows what the setup check reads).
     *
     * @return array<string, mixed>
     */
    private function defaultLanguageSetting(): array
    {
        $default = app(StoreDefaultLanguage::class);
        $effective = $default->effective();
        $saved = $default->stored();
        $options = Language::query()->where('status', 1)->orderBy('name')->get(['code', 'name'])
            ->map(fn (Language $language): array => ['code' => $language->code, 'name' => $language->name, 'active' => true])->all();

        if ($saved !== null && ! in_array($saved, array_column($options, 'code'), true)) {
            $other = Language::query()->where('code', $saved)->first(['code', 'name']);
            $options[] = ['code' => $saved, 'name' => $other->name ?? $saved, 'active' => false];
        }

        $current = filled($effective['value']) ? Language::query()->where('code', $effective['value'])->first(['id', 'status']) : null;

        return [
            'saved' => $saved,
            'effective' => $effective,
            'environment' => $default->fromEnvironment(),
            'state' => match (true) {
                $current === null => 'missing',
                (int) $current->status !== 1 => 'inactive',
                default => 'ok',
            },
            'options' => $options,
        ];
    }

    private function translationPage(FilterLanguagesRequest $request, Language $language, bool $app): Response
    {
        $table = $app ? 'app_translations' : 'translations';
        $perPage = $this->resolvePerPage($request, 50);
        $keys = DB::table($table)->select(['id', 'lang_key', 'lang_value'])->where('lang', 'en');
        $search = $request->validated('search');
        if (is_string($search) && $search !== '') {
            $keys->where('lang_key', 'like', '%'.($app ? $search : preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', strtolower($search)))).'%');
        }
        $rows = $keys->orderBy('id')->paginate($perPage)->withQueryString();
        $values = DB::table($table)->where('lang', $app ? $language->app_lang_code : $language->code)
            ->whereIn('lang_key', $rows->pluck('lang_key'))->orderBy('id')->pluck('lang_value', 'lang_key');
        $rows->setCollection($rows->getCollection()->map(fn (\stdClass $row): array => [
            'id' => $row->id, 'key' => $row->lang_key, 'source' => $row->lang_value,
            'value' => $values->get($row->lang_key, ''),
        ]));

        return Inertia::render('Store/Admin/Languages/Translations', [
            'language' => new LanguageResource($language), 'app' => $app,
            'translations' => ['data' => $rows->items(), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(), 'from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'links' => $rows->linkCollection()->toArray()]],
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    private function saveTranslations(UpdateTranslationsRequest $request, bool $app): RedirectResponse
    {
        $language = Language::query()->findOrFail($request->integer('id'));
        $lang = $app ? $language->app_lang_code : $language->code;
        abort_if(blank($lang), 422);
        DB::transaction(function () use ($request, $app, $lang) {
            foreach ($request->validated('translations') as $translation) {
                $this->writeTranslation($app ? 'app_translations' : 'translations', $lang, $translation['key'], $translation['value'] ?? '');
            }
        });
        if (! $app) {
            Cache::forget('translations-'.$language->code);
        }
        $this->flashSuccess($app ? 'app_translations_have_been_updated_successfully' : 'translations_have_been_updated_successfully');

        return $this->redirectPreservingQuery($app ? 'app-translations.show' : 'languages.show', $request, ['language' => $language]);
    }

    private function writeTranslation(string $table, string $lang, string $key, string $value): void
    {
        DB::table($table)->updateOrInsert(['lang' => $lang, 'lang_key' => $key], [
            'lang_value' => $value, 'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterCategoriesRequest;
use App\Http\Requests\Store\Admin\StoreCategoryRequest;
use App\Http\Requests\Store\Admin\UpdateCategoryFeaturedRequest;
use App\Http\Requests\Store\Admin\UpdateCategoryRequest;
use App\Http\Resources\Store\CategoryFormResource;
use App\Http\Resources\Store\CategoryResource;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\Upload;
use App\Utility\CategoryUtility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller implements HasMiddleware
{
    use ProvidesStoreReferenceOptions;
    use ProvidesStoreUploadPreviews;

    private const array COLUMNS = ['id', 'name', 'parent_id', 'order_level', 'digital', 'banner', 'icon', 'cover_image', 'commision_rate', 'slug', 'meta_title', 'meta_description'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_product_categories', only: ['index']),
            new Middleware('permission:add_product_category', only: ['create', 'store']),
            new Middleware('permission:edit_product_category', only: ['edit', 'update', 'updateFeatured']),
            new Middleware('permission:delete_product_category', only: ['destroy']),
        ];
    }

    public function index(FilterCategoriesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Product/Categories/Index', [
            'categories' => $this->paginated(CategoryResource::collection(
                Category::query()->select(['id', 'name', 'parent_id', 'level', 'order_level', 'banner', 'icon', 'cover_image', 'featured', 'commision_rate'])
                    ->with(['category_translations:id,category_id,lang,name', 'parentCategory:id,name', 'parentCategory.category_translations:id,category_id,lang,name'])
                    ->when($search !== null, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orderByDesc('order_level')->orderBy('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Product/Categories/Create', $this->formOptions());
    }

    public function edit(EditReferenceTranslationRequest $request, Category $category): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $category = Category::query()->select(self::COLUMNS)
            ->with(['category_translations:id,category_id,lang,name', 'attributes:id'])->findOrFail($category->id);
        $images = Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])
            ->whereIn('id', array_filter([$category->banner, $category->icon, $category->cover_image]))->get()->keyBy('id');
        $initialFiles = [];
        foreach (['banner', 'icon', 'cover_image'] as $field) {
            $image = $images->get($category->$field);
            $initialFiles[$field] = $this->uploadPreviews($request, $image);
        }

        return Inertia::render('Store/Admin/Product/Categories/Edit', [
            ...$this->formOptions($category),
            'category' => new CategoryFormResource($category),
            'name' => $category->getTranslation('name', $lang),
            'lang' => $lang,
            'languages' => $this->languageOptions(),
            'initialFiles' => $initialFiles,
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(?Category $category = null): array
    {
        $rows = Category::query()->select(['id', 'name', 'parent_id', 'level'])
            ->with('category_translations:id,category_id,lang,name')->orderBy('id')->get();
        $groups = $rows->groupBy('parent_id');
        $roots = $groups->get(0, collect());
        if ($category) {
            $roots = $roots->sortBy('name');
        }
        $options = [];
        $walk = function ($children, int $depth, array $visited) use (&$walk, &$options, $groups, $category): void {
            foreach ($children as $child) {
                if ($child->id === $category?->id || in_array($child->id, $visited, true)) {
                    continue;
                }
                $options[] = ['id' => $child->id, 'name' => ($depth > 0 ? str_repeat('--', max(0, $child->level)).' ' : '').$child->getTranslation('name')];
                $walk($groups->get($child->id, collect()), $depth + 1, [...$visited, $child->id]);
            }
        };
        $walk($roots, 0, []);

        return [
            'parents' => $options,
            'attributes' => Attribute::query()->select(['id', 'name'])
                ->with('attribute_translations:id,attribute_id,lang,name')->orderBy('id')->get()
                ->map(fn (Attribute $attribute) => ['id' => $attribute->id, 'name' => $attribute->getTranslation('name')]),
            'commissionEnabled' => get_setting('category_wise_commission') == 1,
        ];
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $this->lockHierarchy();
            $data = Validator::make($request->validated(), $request->rules())->validate();
            $this->saveCategory(new Category, $data, config('app.default_language'));
        });

        return redirect()->route('categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $this->lockHierarchy();
            $category = Category::query()->findOrFail($category->id);
            $request->route()->setParameter('category', $category);
            $data = Validator::make($request->validated(), $request->rules())->validate();
            $this->saveCategory($category, $data, $data['lang']);
        });
        Cache::forget('featured_categories');

        return redirect()->route('categories.edit', ['category' => $category->id, 'lang' => $request->validated('lang')]);
    }

    /** @param array<string, mixed> $data */
    private function saveCategory(Category $category, array $data, string $lang): void
    {
        $existing = $category->exists;
        if (! $existing || $lang === config('app.default_language')) {
            $category->name = $data['name'];
        }
        $category->order_level = $data['order_level'] ?? ($existing ? $category->order_level : 0);
        foreach (['digital', 'banner', 'icon', 'cover_image', 'meta_title', 'meta_description'] as $field) {
            $category->$field = $data[$field] ?? null;
        }
        $previousLevel = $existing ? $category->level : 0;
        $category->parent_id = (int) $data['parent_id'];
        $category->level = $category->parent_id === 0 ? 0 : Category::query()->findOrFail($category->parent_id)->level + 1;
        if ($existing && $category->level > $previousLevel) {
            CategoryUtility::move_level_down($category->id);
        } elseif ($existing && $category->level < $previousLevel) {
            CategoryUtility::move_level_up($category->id);
        }
        $category->slug = $this->slug($data['name'], $data['slug'] ?? null, $existing);
        if (isset($data['commision_rate'])) {
            $category->commision_rate = $data['commision_rate'];
        }
        $category->save();
        $category->attributes()->sync($data['filtering_attributes'] ?? []);
        CategoryTranslation::query()->updateOrCreate(['category_id' => $category->id, 'lang' => $lang], ['name' => $data['name']]);
    }

    private function slug(string $name, ?string $slug, bool $existing): string
    {
        if ($slug !== null && $existing) {
            return strtolower($slug);
        }
        $normalized = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $slug ?? $name));

        return $slug !== null ? $normalized : $normalized.'-'.Str::random(5);
    }

    private function lockHierarchy(): void
    {
        Category::query()->without('category_translations')->select('id')->orderBy('id')->lockForUpdate()->get();
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $this->lockHierarchy();
            $category = Category::query()->findOrFail($category->id);
            $category->attributes()->detach();
            $category->category_translations()->delete();
            foreach (Product::query()->select(['id', 'category_id'])->where('category_id', $category->id)->lockForUpdate()->get() as $product) {
                $product->category_id = null;
                $product->save();
            }
            CategoryUtility::delete_category($category->id);
        });
        Cache::forget('featured_categories');

        return $this->redirectPreservingQuery('categories.index', $request);
    }

    public function updateFeatured(UpdateCategoryFeaturedRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $category = Category::query()->lockForUpdate()->findOrFail($request->integer('id'));
            $category->featured = (int) $request->validated('status');
            $category->save();
        });
        Cache::forget('featured_categories');

        return $this->redirectPreservingQuery('categories.index', $request);
    }
}

<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreBrandLogos;
use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterBrandsRequest;
use App\Http\Requests\Store\Admin\StoreBrandRequest;
use App\Http\Requests\Store\Admin\UpdateBrandRequest;
use App\Http\Resources\Store\BrandFormResource;
use App\Http\Resources\Store\BrandResource;
use App\Models\Brand;
use App\Models\BrandTranslation;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller implements HasMiddleware
{
    use ProvidesStoreBrandLogos;
    use ProvidesStoreReferenceOptions;
    use ProvidesStoreUploadPreviews;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_brands', only: ['index']),
            new Middleware('permission:add_brand', only: ['store']),
            new Middleware('permission:edit_brand', only: ['edit', 'update']),
            new Middleware('permission:delete_brand', only: ['destroy']),
        ];
    }

    public function index(FilterBrandsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        $brands = Brand::query()->select(['id', 'name', 'logo'])
            ->with('brand_translations:id,brand_id,lang,name')
            ->when($search !== null, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->paginate($perPage)->withQueryString();
        $this->attachBrandLogoUrls($brands->getCollection());

        return Inertia::render('Store/Admin/Product/Brands/Index', [
            'brands' => $this->paginated(BrandResource::collection($brands)),
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $brand = new Brand;
            $brand->name = $data['name'];
            $brand->meta_title = $data['meta_title'] ?? null;
            $brand->meta_description = $data['meta_description'] ?? null;
            $brand->slug = isset($data['slug']) ? str_replace(' ', '-', $data['slug']) : $this->generatedSlug($data['name']);
            $brand->logo = $data['logo'] ?? null;
            $brand->save();
            $this->saveTranslation($brand, config('app.default_language'), $data['name']);
        });

        return redirect()->route('brands.index');
    }

    public function edit(EditReferenceTranslationRequest $request, Brand $brand): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $brand = Brand::query()->select(['id', 'name', 'logo', 'slug', 'meta_title', 'meta_description'])
            ->with('brand_translations:id,brand_id,lang,name')->findOrFail($brand->id);
        $logo = $brand->logo === null ? null : Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find($brand->logo);

        return Inertia::render('Store/Admin/Product/Brands/Edit', [
            'brand' => new BrandFormResource($brand),
            'name' => $brand->getTranslation('name', $lang),
            'lang' => $lang,
            'languages' => $this->languageOptions(),
            'initialFiles' => ['logo' => $this->uploadPreviews($request, $logo)],
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($brand, $data): void {
            $brand = Brand::query()->lockForUpdate()->findOrFail($brand->id);
            if ($data['lang'] === config('app.default_language')) {
                $brand->name = $data['name'];
            }
            $brand->meta_title = $data['meta_title'] ?? null;
            $brand->meta_description = $data['meta_description'] ?? null;
            $brand->slug = isset($data['slug']) ? strtolower($data['slug']) : $this->generatedSlug($data['name']);
            $brand->logo = $data['logo'] ?? null;
            $brand->save();
            $this->saveTranslation($brand, $data['lang'], $data['name']);
        });

        return redirect()->route('brands.edit', ['brand' => $brand->id, 'lang' => $data['lang']]);
    }

    /**
     * Deleting a brand still deletes every product row carrying it, as the
     * legacy handler did; the three statements now commit or fail together.
     */
    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        DB::transaction(function () use ($brand): void {
            $brand = Brand::query()->without('brand_translations')->lockForUpdate()->findOrFail($brand->id);
            Product::query()->where('brand_id', $brand->id)->delete();
            $brand->brand_translations()->delete();
            $brand->delete();
        });

        return $this->redirectPreservingQuery('brands.index', $request);
    }

    private function generatedSlug(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $name)).'-'.Str::random(5);
    }

    private function saveTranslation(Brand $brand, string $lang, string $name): void
    {
        $translation = BrandTranslation::query()->firstOrNew(['lang' => $lang, 'brand_id' => $brand->id]);
        $translation->name = $name;
        $translation->save();
    }
}

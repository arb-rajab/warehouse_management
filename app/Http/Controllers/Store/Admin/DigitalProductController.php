<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreProductTaxes;
use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Concerns\UpdatesStoreProductFlags;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterDigitalProductsRequest;
use App\Http\Requests\Store\Admin\StoreDigitalProductRequest;
use App\Http\Requests\Store\Admin\UpdateDigitalProductFlagRequest;
use App\Http\Requests\Store\Admin\UpdateDigitalProductRequest;
use App\Http\Resources\Store\DigitalProductFormResource;
use App\Http\Resources\Store\DigitalProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Models\Upload;
use App\Services\ProductTaxService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The digital products list, its row actions and the create and edit forms.
 *
 * Under Option A, products:sync owns name, ar_name and published on feed-origin
 * rows (synced_at is non-null). Store writers own the remaining business columns
 * and may change those three only on store-origin rows; new store rows start
 * with null synced_at. ProductColumnOwnership and ProductBuilder enforce this
 * boundary (shared-database.md). Deletion policy is unchanged
 * (.ai/archive/store-backoffice/README.md §§14–16, §21, §52). Product, stock, translation and tax writes are transactional.
 */
class DigitalProductController extends Controller implements HasMiddleware
{
    use ProvidesStoreProductTaxes;
    use ProvidesStoreReferenceOptions;
    use ProvidesStoreUploadPreviews;
    use UpdatesStoreProductFlags;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:show_digital_products', only: ['index']),
            new Middleware('permission:add_digital_product', only: ['create', 'store']),
            new Middleware('permission:edit_digital_product', only: ['edit', 'update']),
            new Middleware('permission:delete_digital_product', only: ['destroy']),
            new Middleware('permission:download_digital_product', only: ['download']),
            // The list's switches used ProductController's shared endpoints,
            // which product_edit gates; these keep that gate.
            new Middleware('permission:product_edit', only: ['updatePublished', 'updateFeatured', 'updateTodaysDeal']),
        ];
    }

    public function index(FilterDigitalProductsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Product/DigitalProducts/Index', [
            'products' => $this->paginated(DigitalProductResource::collection(
                Product::query()->digital()
                    ->select(['id', 'name', 'added_by', 'thumbnail_img', 'unit_price', 'todays_deal', 'published', 'featured', 'slug', 'created_at'])
                    ->with('product_translations:id,product_id,lang,name')
                    ->when($search !== null, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Product/DigitalProducts/Create', [
            'categories' => $this->categoryOptions(),
            'taxes' => $this->taxOptions(null),
        ]);
    }

    /**
     * Creates an admin digital product as the legacy form did, with the
     * generated slug, then the product's tax rows, one empty-variant stock row at the unit
     * price with no quantity and the default-language translation.
     */
    public function store(StoreDigitalProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data): void {
            $product = new Product;
            $product->forceFill(['added_by' => 'admin', 'user_id' => $request->user('store')->id, 'digital' => 1]);
            $product->name = $data['name'];
            $product->setAttribute('description', $data['description'] ?? null);
            $this->fillProduct($product, $data);
            $product->slug = $this->generatedSlug($data['name']);
            $product->save();
            $this->saveTaxes($product, $data, false);
            $this->saveStock($product, $data);
            $this->saveTranslation($product, config('app.default_language'), $data);
        });

        return redirect()->route('digitalproducts.index');
    }

    public function edit(EditReferenceTranslationRequest $request, Product $product): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $product = Product::query()->digital()
            ->select(['id', 'name', 'description', 'category_id', 'photos', 'thumbnail_img', 'tags', 'unit_price', 'purchase_price', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'meta_title', 'meta_description', 'meta_img', 'slug', 'file_name'])
            ->with('product_translations:id,product_id,lang,name,description')->findOrFail($product->id);
        $previews = $this->uploadIdPreviews($request, [
            'photos' => $product->getAttribute('photos'),
            'thumbnail_img' => $product->thumbnail_img,
            'meta_img' => $product->getAttribute('meta_img'),
            'file' => $product->getAttribute('file_name'),
        ]);

        return Inertia::render('Store/Admin/Product/DigitalProducts/Edit', [
            'product' => new DigitalProductFormResource($product),
            'name' => $product->getTranslation('name', $lang),
            'description' => clean_html($product->getTranslation('description', $lang)),
            'lang' => $lang,
            'languages' => $this->languageOptions(),
            'categories' => $this->categoryOptions(),
            'taxes' => $this->taxOptions($product),
            'initialFiles' => $previews,
        ]);
    }

    /**
     * Updates as the legacy form did, for digital products only: the base
     * name and description change only in the default language, and that
     * language's translation is written; the stock row is replaced by a new
     * one at the unit price with no quantity; posted taxes replace the
     * product's tax rows. The slug is lowercased, or regenerated as on create
     * from the base name when left blank.
     */
    public function update(UpdateDigitalProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($product, $data): void {
            $product = $this->lockedDigitalProduct($product);
            if ($data['lang'] === config('app.default_language')) {
                $product->name = $data['name'];
                $product->setAttribute('description', $data['description'] ?? null);
            }
            $this->fillProduct($product, $data);
            $product->slug = isset($data['slug'])
                ? strtolower($data['slug'])
                : $this->generatedSlug($product->name);
            $product->stocks()->delete();
            $product->save();
            $this->saveTaxes($product, $data, true);
            $this->saveStock($product, $data);
            $this->saveTranslation($product, $data['lang'], $data);
        });

        return redirect()->route('digitalproducts.edit', ['product' => $product->id, 'lang' => $data['lang']]);
    }

    /**
     * Deleting a digital product still removes its translations and stock
     * rows and then hard-deletes the `products` row, as the legacy handler
     * did; the three statements now commit or fail together.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            $product = $this->lockedDigitalProduct($product);
            $product->product_translations()->delete();
            $product->stocks()->delete();
            $product->delete();
        });

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    /**
     * The legacy route still takes the encrypted product id. A malformed id
     * or a file missing from local storage is a 404 rather than the legacy
     * 500 or empty 200.
     */
    public function download(string $id): BinaryFileResponse|StreamedResponse
    {
        try {
            $productId = filter_var(decrypt($id), FILTER_VALIDATE_INT);
        } catch (DecryptException) {
            abort(404);
        }
        abort_if($productId === false, 404);

        $product = Product::query()->digital()->select(['id', 'file_name'])->findOrFail($productId);
        $uploadId = filter_var($product->getAttribute('file_name'), FILTER_VALIDATE_INT);
        abort_if($uploadId === false, 404);
        $upload = Upload::query()->select(['id', 'file_name', 'file_original_name', 'extension'])->findOrFail($uploadId);

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->download((string) $upload->file_name, $upload->file_original_name.'.'.$upload->extension);
        }

        $path = base_path('public/'.$upload->file_name);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    /**
     * Writes as ProductController::updatePublished() does, through the
     * switch logic the product lists share (UpdatesStoreProductFlags).
     */
    public function updatePublished(UpdateDigitalProductFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductPublished(Product::query()->digital(), $product, $request->boolean('status'));

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    public function updateFeatured(UpdateDigitalProductFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductFeatured(Product::query()->digital(), $product, $request->boolean('status'));

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    public function updateTodaysDeal(UpdateDigitalProductFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductTodaysDeal(Product::query()->digital(), $product, $request->boolean('status'));

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    /**
     * The bound product, locked for the surrounding transaction; anything
     * but a digital product is a 404 here.
     */
    private function lockedDigitalProduct(Product $product): Product
    {
        return $this->lockedProduct(Product::query()->digital(), $product);
    }

    /**
     * The legacy slug: the name's letters, digits and hyphens (spaces become
     * hyphens), `-` and five random digits.
     */
    private function generatedSlug(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $name)).'-'.rand(10000, 99999);
    }

    /**
     * The digital category tree the legacy forms offered: the digital root
     * categories with every category below them (Category::treeOptions()).
     *
     * @return list<array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return Category::treeOptions(fn ($roots) => $roots->where('digital', 1));
    }

    /**
     * The fields both forms write the same way. The tags field holds
     * comma-separated tags, stored trimmed and without repeats, comma-joined
     * as the legacy tag input stored them; the discount range becomes the
     * start and end timestamps ProductService writes, or none.
     *
     * @param  array<string, mixed>  $data
     */
    private function fillProduct(Product $product, array $data): void
    {
        $tags = array_values(array_unique(array_filter(array_map(trim(...), explode(',', (string) ($data['tags'] ?? ''))), fn (string $tag): bool => $tag !== '')));
        $dates = isset($data['date_range']) ? explode(' to ', $data['date_range']) : null;
        $product->forceFill([
            'category_id' => $data['category_id'],
            'digital' => 1,
            'photos' => $data['photos'] ?? null,
            'thumbnail_img' => $data['thumbnail_img'] ?? null,
            'tags' => implode(',', $tags),
            'unit_price' => $data['unit_price'],
            'purchase_price' => $data['purchase_price'],
            'discount' => $data['discount'],
            'discount_type' => $data['discount_type'],
            'discount_start_date' => $dates ? strtotime($dates[0]) : null,
            'discount_end_date' => $dates ? strtotime($dates[1]) : null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_img' => $data['meta_img'] ?? null,
            'file_name' => isset($data['file']) ? (string) $data['file'] : null,
        ]);
    }

    /**
     * Posted taxes are stored through ProductTaxService; on update they first
     * replace every tax row of the product. With no taxes posted (no active
     * tax), the product's rows stay, as before.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveTaxes(Product $product, array $data, bool $replace): void
    {
        if (empty($data['tax_id'])) {
            return;
        }
        if ($replace) {
            ProductTax::query()->where('product_id', $product->id)->delete();
        }
        (new ProductTaxService)->store(['tax_id' => $data['tax_id'], 'tax' => $data['tax'], 'tax_type' => $data['tax_type'], 'product_id' => $product->id]);
    }

    /** @param array<string, mixed> $data */
    private function saveStock(Product $product, array $data): void
    {
        $stock = new ProductStock;
        $stock->forceFill(['product_id' => $product->id, 'variant' => '', 'price' => $data['unit_price'], 'sku' => '', 'qty' => 0]);
        $stock->save();
    }

    /** @param array<string, mixed> $data */
    private function saveTranslation(Product $product, string $lang, array $data): void
    {
        $translation = ProductTranslation::query()->firstOrNew(['lang' => $lang, 'product_id' => $product->id]);
        $translation->forceFill(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $translation->save();
    }
}

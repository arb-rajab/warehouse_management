<?php

namespace App\Http\Controllers\Store\Admin;

use App\Enums\StoreProductList;
use App\Http\Controllers\Concerns\UpdatesStoreProductFlags;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteProductsRequest;
use App\Http\Requests\Store\Admin\FilterAllProductsRequest;
use App\Http\Requests\Store\Admin\FilterInHouseProductsRequest;
use App\Http\Requests\Store\Admin\ProductListActionRequest;
use App\Http\Requests\Store\Admin\UpdateProductListFlagRequest;
use App\Http\Resources\Store\ProductListResource;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-house and all-products lists and their row actions, one Inertia
 * page for both. The seller list, the create/edit forms (which need the
 * rich-text editor) and the jQuery endpoints the seller list posts to stay
 * on the root ProductController's Blade.
 *
 * Every row action names its list (`list`), touches only a product that
 * list shows (404 otherwise) and returns to it with the list's filters.
 */
class ProductController extends Controller implements HasMiddleware
{
    use UpdatesStoreProductFlags;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:show_in_house_products', only: ['inHouse']),
            new Middleware('permission:show_all_products', only: ['all']),
            // The root ProductController's own gates for the same writes.
            new Middleware('permission:product_edit', only: ['updatePublished', 'updateFeatured', 'updateForSale']),
            new Middleware('permission:product_duplicate', only: ['duplicate']),
            new Middleware('permission:product_delete', only: ['destroy', 'bulkDestroy']),
        ];
    }

    /**
     * The in-house list keeps the legacy filters: one search phrase over
     * the name, the Arabic name and the barcodes, the three state switches
     * matched exactly whether ticked or not, the category, the optional
     * legacy sort, then newest first.
     */
    public function inHouse(FilterInHouseProductsRequest $request): Response
    {
        $search = $request->validated('search');
        $query = $this->listQuery(StoreProductList::InHouse)
            ->when($search !== null, fn (Builder $query) => $query->where(fn (Builder $match) => $this->matchSearchTerm($match, $search)))
            ->where('published', $request->boolean('active'))
            ->where('for_sale', $request->boolean('for_sale'))
            ->where('featured', $request->boolean('featured'));
        $this->applyCategoryAndSort($query, $request);

        return $this->renderList(StoreProductList::InHouse, $request, $query->orderByDesc('created_at')->orderByDesc('id'), [
            'active' => $request->boolean('active'),
            'for_sale' => $request->boolean('for_sale'),
            'featured' => $request->boolean('featured'),
        ]);
    }

    /**
     * The all-products list keeps its legacy filters: the owner, every word
     * of the search over the names, material id and barcodes, one preset or
     * the four state switches matched exactly, the category and the
     * optional legacy sort. With none of those, products the Otajer sync
     * retired stay hidden. It had no order of its own, so rows keep their
     * primary-key order.
     */
    public function all(FilterAllProductsRequest $request): Response
    {
        $search = $request->validated('search');
        $userId = $request->validated('user_id');
        $flags = ['active' => 'published', 'for_sale' => 'for_sale', 'featured' => 'featured', 'deleted' => 'deleted_from_api'];
        $presets = ['allDeleted' => 'deleted_from_api', 'allActive' => 'published', 'allForSale' => 'for_sale', 'allFeatured' => 'featured'];
        $preset = Arr::first(FilterAllProductsRequest::PRESETS, fn (string $name) => $request->boolean($name));
        $query = $this->listQuery(StoreProductList::All)
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($search !== null, fn (Builder $query) => $query->where(function (Builder $words) use ($search): void {
                foreach (array_filter(explode(' ', trim($search)), fn (string $word) => $word !== '') as $word) {
                    $words->where(function (Builder $match) use ($word): void {
                        $this->matchSearchTerm($match, $word);
                        $match->orWhere('mat_id', 'like', '%'.$word.'%');
                    });
                }
            }));
        if ($preset !== null && $preset !== 'allProducts') {
            $query->where($presets[$preset], true);
        } elseif ($preset === null && collect($flags)->keys()->contains(fn (string $flag) => $request->boolean($flag))) {
            foreach ($flags as $flag => $column) {
                $query->where($column, $request->boolean($flag));
            }
        } elseif ($preset === null) {
            $query->where('deleted_from_api', false);
        }
        $this->applyCategoryAndSort($query, $request);

        return $this->renderList(StoreProductList::All, $request, $query->orderBy('id'), [
            ...collect($flags)->keys()->mapWithKeys(fn (string $flag) => [$flag => $request->boolean($flag)])->all(),
            'user_id' => $userId === null ? null : (int) $userId,
            'preset' => $preset,
        ]);
    }

    public function updatePublished(UpdateProductListFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductPublished($this->listQuery($request->productList()), $product, $request->boolean('status'));

        return $this->redirectToList($request->productList(), $request);
    }

    public function updateFeatured(UpdateProductListFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductFeatured($this->listQuery($request->productList()), $product, $request->boolean('status'));

        return $this->redirectToList($request->productList(), $request);
    }

    /**
     * Writes what ProductController::updateSaleStatus() writes: taking a
     * product off sale also unfeatures it and empties its stock, putting it
     * on sale restocks it to 1,000.
     */
    public function updateForSale(UpdateProductListFlagRequest $request, Product $product): RedirectResponse
    {
        $forSale = $request->boolean('status');
        DB::transaction(function () use ($request, $product, $forSale): void {
            $product = $this->lockedProduct($this->listQuery($request->productList()), $product);
            $product->setAttribute('for_sale', (int) $forSale);
            $this->saveAvailability($product, $forSale);
        });
        $this->clearStorefrontCaches();

        return $this->redirectToList($request->productList(), $request);
    }

    /**
     * Copies the `products` row as ProductController::duplicate() does, with
     * a random suffix on its slug, and its stock and tax rows; translations,
     * variations and barcodes are not copied, as before. This is a second
     * writer of `products` beside `products:sync`; see the store migration
     * guide.
     */
    public function duplicate(ProductListActionRequest $request, Product $product, ProductStockService $stocks, ProductTaxService $taxes): RedirectResponse
    {
        DB::transaction(function () use ($request, $product, $stocks, $taxes): void {
            $source = $this->lockedProduct($this->listQuery($request->productList()), $product);
            $copy = $source->replicate();
            $copy->setAttribute('slug', $source->slug.'-'.Str::random(5));
            $copy->save();
            $stocks->product_duplicate_store($source->stocks()->orderBy('id')->get(), $copy);
            $taxes->product_duplicate_store($source->taxes()->orderBy('id')->get(), $copy);
        });

        return $this->redirectToList($request->productList(), $request);
    }

    /**
     * Deleting a product still hard-deletes the `products` row after its
     * translations, stock, taxes, variations and store barcodes, then its
     * cart lines, as ProductController::destroy() did; the statements now
     * commit or fail together. Whether to delete WMS-owned products at all is
     * an open owner decision (see the store migration guide).
     */
    public function destroy(ProductListActionRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $this->deleteProduct($this->lockedProduct($this->listQuery($request->productList()), $product));
        });
        $this->clearStorefrontCaches();

        return $this->redirectToList($request->productList(), $request);
    }

    /**
     * Every selected product must be one the list shows; that is checked
     * with the rows locked, before any of them is deleted.
     */
    public function bulkDestroy(BulkDeleteProductsRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('id'));
        DB::transaction(function () use ($request, $ids): void {
            $products = $this->listQuery($request->productList())->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($ids)) {
                throw ValidationException::withMessages(['id' => __('validation.exists', ['attribute' => 'id'])]);
            }
            $products->each(fn (Product $product) => $this->deleteProduct($product));
        });
        $this->clearStorefrontCaches();

        return $this->redirectToList($request->productList(), $request);
    }

    /**
     * The products a list shows before any filter: never an auction or
     * wholesale product, and for the in-house list only the admin's own
     * physical products.
     *
     * @return Builder<Product>
     */
    private function listQuery(StoreProductList $list): Builder
    {
        $query = Product::query()->where('auction_product', 0)->where('wholesale_product', 0);

        return $list === StoreProductList::InHouse ? $query->where('added_by', 'admin')->where('digital', 0) : $query;
    }

    /**
     * One search word or phrase against the name, the Arabic name and both
     * barcode tables: the WMS barcodes the merged `barcodes()` relation now
     * reads, and the store barcodes the legacy Otajer import fills with each
     * product's material id, which the legacy search meant to cover.
     *
     * @param  Builder<Product>  $query
     */
    private function matchSearchTerm(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';
        $query->orWhere('name', 'like', $like)
            ->orWhere('ar_name', 'like', $like)
            ->orWhereHas('barcodes', fn (Builder $barcodes) => $barcodes->where('barcode', 'like', $like))
            ->orWhereHas('storeBarcodes', fn (Builder $barcodes) => $barcodes->where('barcode', 'like', $like));
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCategoryAndSort(Builder $query, FilterInHouseProductsRequest|FilterAllProductsRequest $request): void
    {
        $categoryId = $request->validated('category_id');
        $query->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId));
        $sort = $request->productListSort();
        if ($sort !== null) {
            $query->orderBy($sort[0], $sort[1]);
        }
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $listFilters
     */
    private function renderList(StoreProductList $list, FilterInHouseProductsRequest|FilterAllProductsRequest $request, Builder $query, array $listFilters): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $products = $query
            ->select(['id', 'name', 'ar_name', 'thumbnail_img', 'mat_id', 'api_unit_name', 'unit_equal', 'num_of_sale', 'unit_price', 'collection_id', 'variant_product', 'low_stock_quantity', 'category_id', 'for_sale', 'published', 'featured', 'deleted_from_api', 'slug'])
            ->with([
                'stocks' => fn ($stocks) => $stocks->select(['id', 'product_id', 'variant', 'qty'])->orderBy('id'),
                'category:id,name',
                'category.category_translations:id,category_id,lang,name',
                'collection:id,amount',
            ])
            ->paginate($perPage)->withQueryString();

        return Inertia::render('Store/Admin/Product/Products/Index', [
            'listType' => $list->value,
            'products' => $this->paginated(ProductListResource::collection($products)),
            'filters' => [
                'search' => $request->validated('search'),
                'category_id' => $request->validated('category_id') === null ? null : (int) $request->validated('category_id'),
                'type' => $request->validated('type'),
                ...$listFilters,
                'per_page' => $perPage,
            ],
            // The legacy category filter listed every category by its base name.
            'categories' => Category::query()->without('category_translations')->select(['id', 'name'])->orderBy('id')->get()->map(fn (Category $category) => ['id' => $category->id, 'name' => $category->name])->all(),
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    private function deleteProduct(Product $product): void
    {
        $product->product_translations()->delete();
        $product->stocks()->delete();
        $product->taxes()->delete();
        $product->variations()->delete();
        $product->storeBarcodes()->delete();
        $product->delete();
        Cart::query()->where('product_id', $product->id)->delete();
    }

    /**
     * Back to the list the action came from, with its filters and page; the
     * `list` marker itself stays out of the list's own URL.
     */
    private function redirectToList(StoreProductList $list, Request $request): RedirectResponse
    {
        return redirect()->route($list === StoreProductList::InHouse ? 'products.admin' : 'products.all', Arr::except($request->query(), ['list']));
    }
}

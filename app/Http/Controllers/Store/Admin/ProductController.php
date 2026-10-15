<?php

namespace App\Http\Controllers\Store\Admin;

use App\Enums\StoreProductList;
use App\Http\Controllers\Concerns\ProvidesStoreProductTaxes;
use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Concerns\UpdatesStoreProductFlags;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteProductsRequest;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterAllProductsRequest;
use App\Http\Requests\Store\Admin\FilterInHouseProductsRequest;
use App\Http\Requests\Store\Admin\ProductListActionRequest;
use App\Http\Requests\Store\Admin\ProductVariantCombinationsRequest;
use App\Http\Requests\Store\Admin\StoreProductRequest;
use App\Http\Requests\Store\Admin\UpdateProductListFlagRequest;
use App\Http\Requests\Store\Admin\UpdateProductRequest;
use App\Http\Resources\Store\ProductFormResource;
use App\Http\Resources\Store\ProductListResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Color;
use App\Models\FlashDeal;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use App\Services\Store\InHouseProductWriter;
use App\Services\Store\ProductVariantCombinations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
 * page for both, and the physical product create and edit forms. The seller
 * list and the jQuery endpoints it posts to stay on the root
 * ProductController's Blade.
 *
 * Every row action names its list (`list`), touches only a product that
 * list shows (404 otherwise) and returns to it with the list's filters.
 */
class ProductController extends Controller implements HasMiddleware
{
    use ProvidesStoreProductTaxes;
    use ProvidesStoreReferenceOptions;
    use ProvidesStoreUploadPreviews;
    use UpdatesStoreProductFlags;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:add_new_product', only: ['create', 'store', 'variantCombinations']),
            // The root ProductController's gate for the edit form it rendered.
            new Middleware('permission:product_edit', only: ['edit', 'sellerEdit', 'update', 'editVariantCombinations']),
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

    /**
     * The physical product create form, with the options the Blade form
     * listed.
     */
    public function create(): Response
    {
        return Inertia::render('Store/Admin/Product/Products/Create', $this->formOptions(null));
    }

    /**
     * Creates the product, its stock rows, taxes, flash deal entry,
     * default-language translation, types and store barcodes in one
     * transaction, then clears the compiled views and the cache as the
     * legacy form did, once the write has committed.
     */
    public function store(StoreProductRequest $request, InHouseProductWriter $writer): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(fn () => $writer->create($data, $request->variantNames(), $request->variantColorCodes(), StoreProductRequest::cashPaymentEnabled()));
        $this->clearStorefrontCaches();

        return redirect()->route('products.admin');
    }

    /**
     * The variant rows the chosen colours and attribute values generate,
     * named and ordered as the Blade SKU table named them, each defaulting to
     * the unit price, no SKU and a quantity of 10 as that table did.
     */
    public function variantCombinations(ProductVariantCombinationsRequest $request): JsonResponse
    {
        $price = (string) ($request->validated('unit_price') ?? '');

        return response()->json(array_map(fn (string $variant): array => [
            'variant' => $variant,
            'price' => $price,
            'sku' => '',
            'qty' => '10',
        ], $request->variantNames()));
    }

    /**
     * The edit form opened from the in-house or all-products list (and the
     * language tabs). A digital product goes to its own form.
     */
    public function edit(EditReferenceTranslationRequest $request, Product $product): Response|RedirectResponse
    {
        return $this->renderEdit($request, $product, 'admin');
    }

    /**
     * The same edit form, opened from the Blade seller product list, whose
     * language tabs stay on this route.
     */
    public function sellerEdit(EditReferenceTranslationRequest $request, Product $product): Response|RedirectResponse
    {
        return $this->renderEdit($request, $product, 'seller');
    }

    /**
     * Updates the product, its stock rows, taxes, flash deal entry, the
     * edited language's translation, types and store barcodes in one
     * transaction (InHouseProductWriter::update()), then clears the compiled
     * views and the cache as the legacy form did, once the write has
     * committed, and returns to the edit form in the same language.
     */
    public function update(UpdateProductRequest $request, Product $product, InHouseProductWriter $writer): RedirectResponse
    {
        $data = $request->validated();
        $product = DB::transaction(fn (): Product => $writer->update(
            $this->lockedProduct(Product::query()->physical(), $product),
            $data,
            $request->variantNames(),
            $request->variantColorCodes(),
            UpdateProductRequest::cashPaymentEnabled(),
            UpdateProductRequest::productWiseShippingEnabled(),
        ));
        $this->clearStorefrontCaches();

        return redirect()->route($product->getAttribute('added_by') === 'seller' ? 'products.seller.edit' : 'products.admin.edit', ['product' => $product->id, 'lang' => $data['lang']]);
    }

    /**
     * The edit form's variant rows: named and ordered as on create, each
     * keeping the price, SKU, quantity and photo of the stored stock row of
     * the same name; a variant with no stored row starts at the unit price
     * as typed, no SKU and a quantity of 10, as on create.
     */
    public function editVariantCombinations(ProductVariantCombinationsRequest $request, Product $product): JsonResponse
    {
        return response()->json($this->editVariantRows($request, $product, $request->variantNames(), (string) ($request->validated('unit_price') ?? '')));
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

    /**
     * The options both product forms list, as the Blade forms listed them:
     * the physical category tree, every brand, every colour by name, every
     * attribute, the active flash deals and the active taxes with the
     * product's amounts (each while its business setting shows it).
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Product $product): array
    {
        $flashDeals = StoreProductRequest::flashDealsEnabled();
        $vat = StoreProductRequest::vatSectionEnabled();

        return [
            'categories' => Category::treeOptions(fn ($roots) => $roots->where('digital', 0)),
            'brands' => Brand::query()->select(['id', 'name'])->orderBy('id')->get()
                ->map(fn (Brand $brand): array => ['id' => $brand->id, 'name' => (string) $brand->getTranslation('name')])->all(),
            'colors' => Color::query()->select(['id', 'name', 'code'])->orderBy('name')->orderBy('id')->get()
                ->map(fn (Color $color): array => ['id' => $color->id, 'name' => (string) $color->getAttribute('name'), 'code' => (string) $color->getAttribute('code')])->all(),
            'attributes' => Attribute::query()->select(['id', 'name'])->orderBy('id')->get()
                ->map(fn (Attribute $attribute): array => ['id' => $attribute->id, 'name' => (string) $attribute->getTranslation('name')])->all(),
            'flashDeals' => $flashDeals ? FlashDeal::query()->without('flash_deal_translations')->select(['id', 'title'])->where('status', 1)->orderBy('id')->get()
                ->map(fn (FlashDeal $deal): array => ['id' => $deal->getKey(), 'name' => (string) $deal->getAttribute('title')])->all() : [],
            'taxes' => $vat ? $this->taxOptions($product) : [],
            'sections' => [
                'shipping' => StoreProductRequest::shippingSectionEnabled(),
                'productWiseShipping' => StoreProductRequest::productWiseShippingEnabled(),
                'flashDeals' => $flashDeals,
                'vat' => $vat,
                'cashOnDelivery' => StoreProductRequest::cashPaymentEnabled(),
            ],
        ];
    }

    /**
     * The edit form in the requested language (the default without one):
     * the form's options, the product's fields, the edited language's name,
     * unit and cleaned description, the stored variant choices with their
     * rows, and the previews of its stored uploads the caller may see.
     */
    private function renderEdit(EditReferenceTranslationRequest $request, Product $product, string $listType): Response|RedirectResponse
    {
        if ((int) $product->getAttribute('digital') === 1) {
            return redirect()->route('digitalproducts.edit', ['product' => $product->id, 'lang' => $request->validated('lang')]);
        }
        $lang = $request->validated('lang') ?? config('app.default_language');
        $product = Product::query()->physical()
            ->select([
                'id', 'name', 'added_by', 'category_id', 'brand_id', 'unit', 'weight', 'max_qty', 'min_qty', 'tags', 'photos', 'old_photo',
                'thumbnail_img', 'video_provider', 'video_link', 'pdf', 'unit_price', 'discount', 'discount_type', 'discount_start_date',
                'discount_end_date', 'external_link', 'external_link_btn', 'description', 'meta_title', 'meta_description', 'meta_img', 'slug',
                'shipping_type', 'shipping_cost', 'is_quantity_multiplied', 'est_shipping_days', 'low_stock_quantity', 'stock_visibility_state',
                'cash_on_delivery', 'featured', 'todays_deal', 'colors', 'choice_options',
            ])
            ->with([
                'product_translations:id,product_id,lang,name,unit,description',
                'stocks' => fn ($stocks) => $stocks->select(['id', 'product_id', 'variant', 'sku', 'qty'])->orderBy('id'),
                'variations' => fn ($variations) => $variations->select(['id', 'product_id', 'name'])->orderBy('id'),
                'storeBarcodes' => fn ($barcodes) => $barcodes->select(['id', 'product_id', 'barcode'])->orderBy('id'),
                'flash_deal_product:id,product_id,flash_deal_id',
            ])
            ->findOrFail($product->id);
        $options = $this->formOptions($product);
        $flashDealId = $product->flash_deal_product?->getAttribute('flash_deal_id');
        $variants = $this->storedVariantChoices($product, $options['colors'], $options['attributes']);
        $names = $variants['choice_options'] !== [] && collect($variants['choice_options'])->contains(fn (array $choice): bool => $choice['values'] === [])
            ? []
            : app(ProductVariantCombinations::class)->names($variants['colors'], array_column($variants['choice_options'], 'values'));

        return Inertia::render('Store/Admin/Product/Products/Edit', [
            ...$options,
            'product' => new ProductFormResource($product, in_array($flashDealId, array_column($options['flashDeals'], 'id'), true) ? (int) $flashDealId : null),
            'name' => (string) $product->getTranslation('name', $lang),
            'unit' => (string) $product->getTranslation('unit', $lang),
            'description' => clean_html((string) $product->getTranslation('description', $lang)),
            'lang' => $lang,
            'languages' => $this->languageOptions(),
            'listType' => $listType,
            'variants' => [...$variants, 'rows' => $this->editVariantRows($request, $product, $names, (string) $product->getAttribute('unit_price'))],
            'initialFiles' => $this->uploadIdPreviews($request, [
                'photos' => $product->getAttribute('photos'),
                'thumbnail_img' => $product->thumbnail_img,
                'old_photo' => $product->getAttribute('old_photo'),
                'pdf' => $product->getAttribute('pdf'),
                'meta_img' => $product->getAttribute('meta_img'),
            ]),
        ]);
    }

    /**
     * The product's stored colours and choices as the variant fields offer
     * them: colours in the list's order (on while any is chosen), attributes
     * in their stored order, each attribute's values in its value list's order
     * with repeated values once, and that list itself. A colour, attribute or
     * value that no longer exists is left out, so it is dropped on save, as
     * the Blade selects dropped it.
     *
     * @param  list<array{id: int, name: string, code: string}>  $colors
     * @param  list<array{id: int, name: string}>  $attributes
     * @return array{colors_active: bool, colors: list<string>, choice_options: list<array{attribute_id: int, values: list<string>}>, attribute_values: array<int, list<array{id: int, value: string}>>}
     */
    private function storedVariantChoices(Product $product, array $colors, array $attributes): array
    {
        $storedColors = json_decode((string) $product->getAttribute('colors'), true);
        $storedColors = is_array($storedColors) ? array_map('strval', array_filter($storedColors, 'is_scalar')) : [];
        $chosenColors = array_values(array_unique(array_filter(array_column($colors, 'code'), fn (string $code): bool => in_array($code, $storedColors, true))));

        $attributeIds = array_column($attributes, 'id');
        $storedChoices = json_decode((string) $product->getAttribute('choice_options'), true);
        $choices = [];
        foreach (is_array($storedChoices) ? $storedChoices : [] as $choice) {
            $id = is_array($choice) && is_numeric($choice['attribute_id'] ?? null) ? (int) $choice['attribute_id'] : null;
            if ($id !== null && in_array($id, $attributeIds, true) && ! array_key_exists($id, $choices)) {
                $choices[$id] = array_map('strval', array_filter((array) ($choice['values'] ?? []), 'is_scalar'));
            }
        }
        $values = AttributeValue::query()->select(['id', 'attribute_id', 'value'])->whereIn('attribute_id', array_keys($choices))->orderBy('id')->get()->groupBy('attribute_id');
        $attributeValues = [];
        $choiceOptions = [];
        foreach ($choices as $id => $storedValues) {
            $attributeValues[$id] = array_values(($values->get($id) ?? collect())->unique(fn (AttributeValue $value): string => (string) $value->getAttribute('value'))
                ->map(fn (AttributeValue $value): array => ['id' => (int) $value->getKey(), 'value' => (string) $value->getAttribute('value')])->all());
            $choiceOptions[] = [
                'attribute_id' => $id,
                'values' => array_values(array_filter(array_column($attributeValues[$id], 'value'), fn (string $value): bool => in_array($value, $storedValues, true))),
            ];
        }

        return [
            'colors_active' => $chosenColors !== [],
            'colors' => $chosenColors,
            'choice_options' => $choiceOptions,
            'attribute_values' => $attributeValues,
        ];
    }

    /**
     * The variant rows of `$names` for the edit form: each keeps the price,
     * SKU, quantity and photo (with its preview where the caller may see it)
     * of the product's first stock row of that name; any other starts at
     * `$unitPrice`, no SKU, a quantity of 10 and no photo.
     *
     * @param  list<string>  $names
     * @return list<array{variant: string, price: string, sku: string, qty: string, image: string|null, files: array<int, mixed>}>
     */
    private function editVariantRows(Request $request, Product $product, array $names, string $unitPrice): array
    {
        if ($names === []) {
            return [];
        }
        $stocks = ProductStock::query()->select(['id', 'variant', 'price', 'sku', 'qty', 'image'])
            ->where('product_id', $product->id)->whereIn('variant', $names)->orderBy('id')->get()
            ->unique(fn (ProductStock $stock): string => (string) $stock->getAttribute('variant'))
            ->keyBy(fn (ProductStock $stock): string => 'variant:'.$stock->getAttribute('variant'));
        $previews = $this->uploadIdPreviews($request, $stocks->map(fn (ProductStock $stock) => $stock->getAttribute('image'))->all());

        return array_map(function (string $name) use ($stocks, $previews, $unitPrice): array {
            $stock = $stocks->get('variant:'.$name);

            return $stock === null
                ? ['variant' => $name, 'price' => $unitPrice, 'sku' => '', 'qty' => '10', 'image' => null, 'files' => []]
                : [
                    'variant' => $name,
                    'price' => (string) $stock->getAttribute('price'),
                    'sku' => (string) $stock->getAttribute('sku'),
                    'qty' => (string) $stock->getAttribute('qty'),
                    'image' => $stock->getAttribute('image') === null ? null : (string) $stock->getAttribute('image'),
                    'files' => $previews['variant:'.$name] ?? [],
                ];
        }, $names);
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

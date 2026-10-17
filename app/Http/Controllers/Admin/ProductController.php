<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellLogFilterOptions;
use App\Http\Controllers\Concerns\BuildsProductQrLabels;
use App\Http\Controllers\Concerns\ExpiringSoonDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterProductsRequest;
use App\Http\Requests\Admin\SearchProductsRequest;
use App\Http\Requests\Admin\StoreProductBarcodeRequest;
use App\Http\Requests\Admin\UpdateProductBoxCountRequest;
use App\Http\Requests\Admin\UpdateProductMinimumPalletsRequest;
use App\Http\Resources\ProductOptionResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductSetting;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ProductController extends Controller
{
    use BuildsCellLogFilterOptions, BuildsProductQrLabels;

    /**
     * @var list<string>
     */
    private const array SORTABLE_COLUMNS = [
        'id', 'name', 'full_cells_count', 'opened_cells_count', 'expired_cells_count',
        'expiring_soon_count',
    ];

    public function index(FilterProductsRequest $request): Response
    {
        $expiringSoonDays = $request->filled('expires_within_days')
            ? $request->integer('expires_within_days')
            : ExpiringSoonDefaults::CUSTOM_WINDOW_DAYS;

        $direction = $request->string('sort_direction')->value() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($request->string('sort_by')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort_by')->value()
            : 'name';
        $perPage = $this->resolvePerPage($request, 20);

        $products = Product::query()
            ->select(['id', 'name', 'ar_name', 'thumbnail_img', 'published'])
            ->with(Product::WITH_DERIVED_ATTRIBUTES)
            ->with('barcodes:id,product_id,barcode')
            ->withOccupancyCounts($request, $expiringSoonDays)
            ->addSelect(['pallets_count' => Pallet::query()
                ->selectRaw('count(*)')
                ->whereColumn('pallets.product_id', 'products.id')])
            ->when($request->filled('product_id'), fn (Builder $query) => $query->whereIn('id', $request->productIds()))
            ->when($request->boolean('hide_inactive'), fn (Builder $query) => $query->where('published', true))
            ->when($request->boolean('inactive'), fn (Builder $query) => $query->where('published', false)->inWarehouse())
            ->when($request->boolean('low_stock'), fn (Builder $query) => $query->whereExists($this->lowStockExistsSubquery()))
            ->matchingOccupancyFilters($request)
            ->matchingHistoryFilters($request)
            ->orderBy($sortBy, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $this->paginated(ProductSummaryResource::collection($products)),
            'expiringSoonDays' => $expiringSoonDays,
            'filters' => [
                ...$request->only([
                    'state', 'expired', 'expires_within_days', 'inactive', 'hide_inactive', 'low_stock', 'product_id',
                    'user_id', 'action', 'date_from', 'date_to', 'created_within_days',
                    'sort_by', 'sort_direction',
                ]),
                // The resolved value (not the raw request), so the per-page
                // selector always reflects the actual page size, including
                // the default when no `per_page` param was sent.
                'per_page' => $perPage,
            ],
            'filterOptions' => $this->productRowUserActionFilterOptions($request->productIds()),
        ]);
    }

    /**
     * Sets how many boxes a full pallet of this product holds.
     *
     * The product itself belongs to the store app and is never written here —
     * this is WMS-owned data in `wms_product_settings`, which the store has no
     * column for. `updateOrCreate` because a product the store added may have
     * no settings row yet. See .ai/rules/shared-database.md.
     *
     * Redirects to `admin.products.index` rather than `back()` — the Referer
     * header is stripped app-wide by `no-referrer` policy, and Inertia SPA
     * visits don't update the session's previous URL, so `back()` lands on
     * whatever the last full page load was (typically the dashboard).
     */
    public function updateBoxCount(UpdateProductBoxCountRequest $request, Product $product): RedirectResponse
    {
        $product->setting()->updateOrCreate([], [
            'boxes_count' => $request->integer('boxes_count'),
        ]);

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * Sets, or clears (with `null`), how many pallets of this product should
     * be in the warehouse at minimum. WMS-owned data in
     * `wms_product_settings`, the same as `updateBoxCount()` above — see
     * .ai/rules/shared-database.md.
     *
     * `firstOrNew()` rather than `updateOrCreate([], [...])`: the latter
     * would leave `boxes_count` off the insert for a product with no settings
     * row yet, and `wms_product_settings.boxes_count` has its own DB-level
     * default of 1 — divorced from `Product::DEFAULT_BOXES_COUNT` (50), which
     * is what the product was actually showing (via `boxesCount()`'s
     * fallback) before this row existed. Explicitly carrying that default
     * into the new row keeps the displayed box count from silently dropping
     * to 1 the moment a threshold is set on an otherwise-unconfigured
     * product. Only stamped on a genuinely new row — an existing one keeps
     * whatever box count it already has.
     */
    public function updateMinimumPallets(UpdateProductMinimumPalletsRequest $request, Product $product): RedirectResponse
    {
        $setting = $product->setting()->firstOrNew();

        if (! $setting->exists) {
            $setting->boxes_count = Product::DEFAULT_BOXES_COUNT;
        }

        $setting->minimum_pallets = $request->input('minimum_pallets');
        $setting->save();

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * Attaches a new barcode to a product. WMS-owned data in
     * `wms_product_barcodes`, the same as `updateBoxCount()`/
     * `updateMinimumPallets()` above — see .ai/rules/shared-database.md.
     *
     * Redirects to `admin.products.index` rather than `back()` — see
     * `updateBoxCount()`'s docblock.
     */
    public function storeBarcode(StoreProductBarcodeRequest $request, Product $product): RedirectResponse
    {
        $product->barcodes()->create([
            'barcode' => $request->validated('barcode'),
        ]);

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * Detaches a barcode from a product. The route uses `scopeBindings()`, so
     * `$barcode` only resolves when it belongs to `$product` — without that,
     * an admin could delete any product's barcode by guessing its id while
     * looking at a different product's row.
     */
    public function destroyBarcode(Request $request, Product $product, ProductBarcode $barcode): RedirectResponse
    {
        $barcode->delete();

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * The admin product filter's searchable catalog: an inactive product is
     * only offered while the warehouse still holds it (see
     * Product::activeOrInWarehouse()). Unlike index(), the products page
     * itself, which lists every product — and so its own product filter
     * sends `include_inactive=1`, searching every product, deactivated or not.
     *
     * @return array<string, mixed>
     */
    public function search(SearchProductsRequest $request): array
    {
        return $this->paginated(ProductOptionResource::collection(
            Product::query()
                ->select(['id', 'name', 'ar_name', 'published'])
                ->searchByName($request->string('q')->value())
                ->unless($request->boolean('include_inactive'), fn (Builder $query) => $query->activeOrInWarehouse())
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString()
        ));
    }

    /**
     * The product's default barcode — its id as a Code 128 label, meant to be
     * printed and stuck on every box/pallet of that product — see
     * BuildsProductQrLabels. Derived from the id, never stored, so it cannot
     * be edited or deleted like a custom barcode.
     */
    public function exportQr(Product $product): HttpResponse
    {
        $setting = Setting::current();

        return response($this->productQrLabelImage($product, $setting->qr_code_width, $setting->qr_code_height))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"product-{$product->id}-barcode.svg\"");
    }

    /**
     * The default barcode's label from {@see exportQr()}, served inline so the
     * products page can show it in an `<img>` without downloading it.
     */
    public function previewDefaultBarcode(Request $request, Product $product): HttpResponse
    {
        $setting = Setting::current();

        $etag = md5(implode('|', [
            $this->productLabelName($product),
            $product->id,
            $setting->qr_code_width,
            $setting->qr_code_height,
        ]));

        $notModified = $this->notModifiedResponse($request, $etag, 'private, no-cache');

        if ($notModified !== null) {
            return $notModified;
        }

        return response($this->productQrLabelImage($product, $setting->qr_code_width, $setting->qr_code_height))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'private, no-cache')
            ->setEtag($etag);
    }

    /**
     * One of the product's stored barcodes as a printable Code 128 SVG label,
     * carrying exactly that barcode's value. The route uses `scopeBindings()`,
     * so `$barcode` only resolves when it belongs to `$product`.
     */
    public function downloadBarcode(Product $product, ProductBarcode $barcode): HttpResponse
    {
        return response($this->barcodeLabelSvg($product, $barcode))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"{$this->barcodeFileName($product, $barcode)}\"");
    }

    /**
     * The same label as {@see downloadBarcode()}, served inline so the
     * products page can show it in an `<img>` without downloading it.
     */
    public function previewBarcode(Request $request, Product $product, ProductBarcode $barcode): HttpResponse
    {
        $setting = Setting::current();

        // Every input of the label, hashed before the SVG is rendered, so a
        // repeat request is answered with a 304 without rendering it.
        $etag = md5(implode('|', [
            $this->productLabelName($product),
            $barcode->barcode,
            $setting->qr_code_width,
            $setting->qr_code_height,
        ]));

        $notModified = $this->notModifiedResponse($request, $etag, 'private, no-cache');

        if ($notModified !== null) {
            return $notModified;
        }

        return response($this->barcodeLabelSvg($product, $barcode))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'private, no-cache')
            ->setEtag($etag);
    }

    /**
     * An empty 304 when the request's `If-None-Match` already holds $etag,
     * otherwise null. Lets an action skip expensive work before it starts.
     */
    private function notModifiedResponse(Request $request, string $etag, string $cacheControl): ?HttpResponse
    {
        $response = response('')->setEtag($etag)->header('Cache-Control', $cacheControl);

        return $response->isNotModified($request) ? $response : null;
    }

    private function barcodeLabelSvg(Product $product, ProductBarcode $barcode): string
    {
        $setting = Setting::current();

        try {
            return $this->productBarcodeLabelImage($product, $barcode->barcode, $setting->qr_code_width, $setting->qr_code_height);
        } catch (InvalidArgumentException) {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, __('messages.barcode_not_printable', ['barcode' => $barcode->barcode]));
        }
    }

    /**
     * A ZIP holding one SVG label per barcode of the product — its default
     * (id) barcode plus every custom one, and none belonging to any other
     * product. Built whole or not at all: a barcode that cannot be rendered
     * fails the download rather than being left out of a ZIP that looks complete.
     */
    public function downloadAllBarcodes(Product $product): BinaryFileResponse
    {
        $barcodes = $product->barcodes()->orderBy('id')->get(['id', 'product_id', 'barcode']);

        $setting = Setting::current();
        $path = tempnam(sys_get_temp_dir(), 'barcodes');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $current = (string) $product->id;

        try {
            $zip->addFromString(
                "product-{$product->id}-barcode.svg",
                $this->productQrLabelImage($product, $setting->qr_code_width, $setting->qr_code_height),
            );

            foreach ($barcodes as $barcode) {
                $current = $barcode->barcode;
                $zip->addFromString(
                    $this->barcodeFileName($product, $barcode),
                    $this->productBarcodeLabelImage($product, $barcode->barcode, $setting->qr_code_width, $setting->qr_code_height),
                );
            }
        } catch (InvalidArgumentException) {
            $zip->close();
            @unlink($path);
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, __('messages.barcode_not_printable', ['barcode' => $current]));
        }

        if (! $zip->close()) {
            @unlink($path);
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()
            ->download($path, "product-{$product->id}-barcodes.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Unique per barcode (its id leads) and safe on any filesystem, whatever
     * characters the barcode value holds.
     */
    private function barcodeFileName(Product $product, ProductBarcode $barcode): string
    {
        $safeValue = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $barcode->barcode), '_');

        return "product-{$product->id}-barcode-{$barcode->id}".($safeValue !== '' ? "-{$safeValue}" : '').'.svg';
    }

    /**
     * A correlated existence check for whether the product on each outer row
     * has a configured minimum-pallets threshold that its current pallet
     * count (across every state, not just full/opened — this is a stock
     * level, not an occupancy filter) falls below. A product with no
     * threshold configured (`minimum_pallets` null) never matches — see
     * Product::minimumPallets().
     *
     * @return Builder<ProductSetting>
     */
    private function lowStockExistsSubquery(): Builder
    {
        return ProductSetting::query()
            ->selectRaw('1')
            ->whereColumn('wms_product_settings.product_id', 'products.id')
            ->whereNotNull('minimum_pallets')
            ->whereRaw('minimum_pallets > (select count(*) from pallets where pallets.product_id = products.id)');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsCellLogFilterOptions;
use App\Http\Controllers\Concerns\ExpiringSoonDefaults;
use App\Http\Controllers\Concerns\FlashesAdminSuccess;
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
use App\Models\ProductImageReview;
use App\Models\ProductSetting;
use App\Models\Setting;
use App\Rules\NewProductBarcode;
use App\Services\ProductBarcodeLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ProductController extends Controller
{
    use BuildsCellLogFilterOptions, FlashesAdminSuccess;

    public function __construct(private readonly ProductBarcodeLabels $labels) {}

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
            ->select(['id', 'name', 'ar_name', 'thumbnail_img', 'warehouse_image_path', 'published'])
            ->addSelect(['image_verification' => ProductImageReview::currentStatusQuery()])
            ->when($request->filled('image_verification'), fn (Builder $query) => $query->whereExists(
                ProductImageReview::currentStatusQuery()->where('status', $request->string('image_verification')->value()),
            ))
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
            ->when(
                $sortBy === 'name' && app()->getLocale() === 'ar',
                fn (Builder $query) => $query->orderByRaw("COALESCE(NULLIF(ar_name, ''), name) {$direction}"),
                fn (Builder $query) => $query->orderBy($sortBy, $direction),
            )
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $this->paginated(ProductSummaryResource::collection($products)),
            'expiringSoonDays' => $expiringSoonDays,
            'filters' => [
                ...$request->only([
                    'image_verification', 'state', 'expired', 'expires_within_days', 'inactive', 'hide_inactive', 'low_stock', 'product_id',
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
        $this->flashAdminSuccess('box_count_has_been_updated_successfully');

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
        $this->flashAdminSuccess('minimum_pallets_has_been_updated_successfully');

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * Attaches a new barcode to a product, in `product_barcodes`, the one
     * barcode table the store shares (see ProductBarcode). The table has no
     * unique index, so the request's rules run again under the shared barcode
     * lock right before the insert: of two admins adding the same value at
     * once, the second gets the same "already taken" error.
     *
     * Redirects to `admin.products.index` rather than `back()` — see
     * `updateBoxCount()`'s docblock.
     */
    public function storeBarcode(StoreProductBarcodeRequest $request, Product $product): RedirectResponse
    {
        $barcode = $request->validated('barcode');
        NewProductBarcode::locked(function () use ($product, $barcode): void {
            Validator::make(['barcode' => $barcode], StoreProductBarcodeRequest::barcodeRules())->validate();
            $product->barcodes()->create(['barcode' => $barcode]);
        });
        $this->flashAdminSuccess('barcode_has_been_added_successfully');

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * Detaches a barcode from a product. The route uses `scopeBindings()`, so
     * `$barcode` only resolves when it belongs to `$product` — without that,
     * an admin could delete any product's barcode by guessing its id while
     * looking at a different product's row. The own-id row the Otajer import
     * stores is a 404, as the page does not list it: the store app's barcode
     * search finds the product by it.
     */
    public function destroyBarcode(Request $request, Product $product, ProductBarcode $barcode): RedirectResponse
    {
        abort_if($barcode->isOwnProductId(), HttpResponse::HTTP_NOT_FOUND);
        $barcode->delete();
        $this->flashAdminSuccess('barcode_has_been_removed_successfully');

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
     * ProductBarcodeLabels. Derived from the id, never stored, so it cannot
     * be edited or deleted like a custom barcode.
     */
    public function exportQr(Product $product): HttpResponse
    {
        $setting = Setting::current();

        return response($this->labels->productQrLabelImage($product, $setting->qr_code_width, $setting->qr_code_height))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"product-{$product->id}-barcode.svg\"");
    }

    /**
     * The default barcode's label from {@see exportQr()}, served inline so the
     * products page can show it in an `<img>` without downloading it.
     */
    public function previewDefaultBarcode(Request $request, Product $product): HttpResponse
    {
        $etag = $this->labels->etag($product, (string) $product->id);

        $notModified = $this->notModifiedResponse($request, $etag, 'private, no-cache');

        if ($notModified !== null) {
            return $notModified;
        }

        // The id label is the id's own barcode label (productQrLabelImage()).
        return response($this->labels->barcodeSvg($product, (string) $product->id))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'inline')
            ->header('Cache-Control', 'private, no-cache')
            ->setEtag($etag);
    }

    /**
     * One of the product's stored barcodes as a printable Code 128 SVG label,
     * carrying that barcode's value as the store pages show it (Arabic digits
     * converted, spaces trimmed), so one value always gives one label. The
     * route uses `scopeBindings()`, so `$barcode` only resolves when it
     * belongs to `$product`; the own-id row and a blank value are 404s (the
     * page lists neither), a value Code 128 cannot draw a 422.
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
        // Every input of the label, hashed before the SVG is rendered, so a
        // repeat request is answered with a 304 without rendering it.
        $etag = $this->labels->etag($product, $this->drawableValue($barcode));

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
        return $this->labels->barcodeSvg($product, $this->drawableValue($barcode));
    }

    /**
     * The value a label of $barcode encodes: normalized as the store pages
     * show it. Aborts with a 404 for a row the page does not list (the own-id
     * row, a blank value) and a 422 for a value Code 128 cannot draw.
     */
    private function drawableValue(ProductBarcode $barcode): string
    {
        $value = $this->labels->normalize($barcode->barcode);
        abort_if($value === '' || $barcode->isOwnProductId(), HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->labels->isPrintable($value), HttpResponse::HTTP_UNPROCESSABLE_ENTITY, __('messages.barcode_not_printable', ['barcode' => $value]));

        return $value;
    }

    /**
     * A ZIP holding one SVG label per barcode of the product — its default
     * (id) barcode plus every one the page lists (not the own-id row, which
     * the default label already is), and none belonging to any other product.
     * A value Code 128 cannot draw (store barcodes may be any text) is left
     * out and named in `not-printable.txt` inside the ZIP, so one such value
     * never blocks the download; when none can be drawn the ZIP holds the
     * default label and that file.
     */
    public function downloadAllBarcodes(Product $product): BinaryFileResponse
    {
        $barcodes = ProductBarcode::listedInWms($product->barcodes()->get(['id', 'product_id', 'barcode']));

        $setting = Setting::current();
        $path = tempnam(sys_get_temp_dir(), 'barcodes');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $zip->addFromString(
            "product-{$product->id}-barcode.svg",
            $this->labels->productQrLabelImage($product, $setting->qr_code_width, $setting->qr_code_height),
        );

        $notPrintable = [];

        foreach ($barcodes as $barcode) {
            $value = $this->labels->normalize($barcode->barcode);

            if (! $this->labels->isPrintable($value)) {
                $notPrintable[] = $value;

                continue;
            }

            $zip->addFromString(
                $this->barcodeFileName($product, $barcode),
                $this->labels->productBarcodeLabelImage($product, $value, $setting->qr_code_width, $setting->qr_code_height),
            );
        }

        if ($notPrintable !== []) {
            $zip->addFromString('not-printable.txt', implode("\n", [__('messages.barcodes_not_printable_in_zip'), '', ...$notPrintable])."\n");
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

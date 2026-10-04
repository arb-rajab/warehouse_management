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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Psr\Http\Message\StreamInterface;

class ProductController extends Controller
{
    use BuildsCellLogFilterOptions, BuildsProductQrLabels;

    /**
     * @var list<string>
     */
    private const array SORTABLE_COLUMNS = [
        'name', 'full_cells_count', 'opened_cells_count', 'expired_cells_count',
        'expiring_soon_count',
    ];

    /** Longest side, in pixels, of an image served by {@see image()} — ample for a 3D map box face. */
    private const int PRODUCT_IMAGE_MAX_SIZE = 256;

    private const int PRODUCT_IMAGE_MAX_BYTES = 5 * 1024 * 1024;

    private const int PRODUCT_IMAGE_MAX_PIXELS = 25_000_000;

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
                    'state', 'expired', 'expires_within_days', 'inactive', 'low_stock', 'product_id',
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
    public function addBarcode(StoreProductBarcodeRequest $request, Product $product): RedirectResponse
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
    public function removeBarcode(Request $request, Product $product, ProductBarcode $barcode): RedirectResponse
    {
        $barcode->delete();

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function search(SearchProductsRequest $request): array
    {
        return $this->paginated(ProductOptionResource::collection(
            Product::query()
                ->select(['id', 'name', 'ar_name'])
                ->where('published', true)
                ->searchByName($request->string('q')->value())
                ->orderBy('name')
                ->paginate(20)
        ));
    }

    /**
     * One Code 128 barcode per product id, meant to be printed and stuck on every
     * box/pallet of that product — see BuildsProductQrLabels.
     */
    public function exportQr(Product $product): HttpResponse
    {
        $setting = Setting::current();

        return response($this->productQrLabelImage($product, $setting->qr_code_width, $setting->qr_code_height))
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"product-{$product->id}-barcode.svg\"");
    }

    /**
     * The product's store-hosted image, re-served from this app's own origin.
     *
     * The 3D map draws product images as WebGL textures, and WebGL rejects a
     * cross-origin image unless its host sends CORS headers — which the
     * store's image host doesn't, even though a plain `<img>` of the same URL
     * displays fine. The upstream bytes are decoded and re-encoded as a
     * downscaled PNG rather than passed through, so only a real raster image
     * (never SVG/HTML) is ever served from this origin.
     */
    public function image(Product $product): HttpResponse
    {
        $product->loadMissing('thumbnailUpload:id,file_name,external_link');
        $url = $product->image_url;

        if ($url === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        abort_unless($this->isAllowedImageUrl($url), HttpResponse::HTTP_BAD_GATEWAY);

        try {
            $upstream = Http::timeout(10)
                ->withOptions(['allow_redirects' => false, 'stream' => true])
                ->get($url);
        } catch (ConnectionException) {
            abort(HttpResponse::HTTP_BAD_GATEWAY);
        }

        abort_unless($upstream->successful(), HttpResponse::HTTP_BAD_GATEWAY);

        $body = $this->readCapped($upstream->toPsrResponse()->getBody(), self::PRODUCT_IMAGE_MAX_BYTES);

        $png = $body === null ? null : $this->normalizedProductImage($body);

        if ($png === null) {
            abort(HttpResponse::HTTP_BAD_GATEWAY);
        }

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'private, max-age=86400');
    }

    /**
     * Whether the image may be fetched server-side: https, the default port and
     * a host the store serves its images from (the asset base URL's host plus
     * `store.image_hosts`). `external_link` is whatever the store's uploads
     * table holds, so it is never trusted to point somewhere harmless.
     */
    private function isAllowedImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === '') {
            return false;
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            return false;
        }

        $assetHost = parse_url((string) config('store.asset_base_url'), PHP_URL_HOST);
        $allowed = array_map('strtolower', array_filter([...config('store.image_hosts'), $assetHost]));

        return in_array($host, $allowed, true);
    }

    /**
     * The stream's bytes, or null once it holds more than `$maxBytes` — read
     * in chunks so an oversized body is abandoned rather than buffered.
     */
    private function readCapped(StreamInterface $stream, int $maxBytes): ?string
    {
        $body = '';

        while (! $stream->eof()) {
            $body .= $stream->read(8192);

            if (strlen($body) > $maxBytes) {
                return null;
            }
        }

        return $body;
    }

    /**
     * Decodes any raster format GD reads, downscales it to fit
     * PRODUCT_IMAGE_MAX_SIZE (never upscaling) and re-encodes it as PNG,
     * keeping transparency. Null when the bytes aren't a decodable image.
     */
    private function normalizedProductImage(string $bytes): ?string
    {
        $dimensions = @getimagesizefromstring($bytes);

        // Refuse before decoding: a small file can declare a huge canvas, and GD
        // allocates width x height x 4 bytes for it.
        if ($dimensions === false || $dimensions[0] * $dimensions[1] > self::PRODUCT_IMAGE_MAX_PIXELS) {
            return null;
        }

        // `@`: GD warns on undecodable input; a false return is handled below.
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        imagepalettetotruecolor($source);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::PRODUCT_IMAGE_MAX_SIZE / max($width, $height));
        $target = imagescale(
            $source,
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        );

        if ($target === false) {
            return null;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);

        ob_start();
        imagepng($target);

        return (string) ob_get_clean();
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

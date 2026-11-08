<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterProductsRequest;
use App\Http\Resources\ProductPalletResource;
use App\Http\Resources\ProductResource;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

class ProductController extends Controller
{
    /**
     * An inactive product is only ever listed while the warehouse still holds
     * it (see Product::activeOrInWarehouse()): with no `product_status` that
     * means every active product plus the inactive ones still in the
     * warehouse, `product_status=active` keeps active products only, and
     * `product_status=inactive` returns just the inactive ones still in the
     * warehouse.
     */
    public function index(FilterProductsRequest $request): AnonymousResourceCollection
    {
        $productPublished = $request->productPublished();

        return ProductResource::collection(
            Product::query()
                ->select('id', 'name', 'ar_name', 'thumbnail_img', 'warehouse_image_path', 'published')
                ->with(Product::WITH_DERIVED_ATTRIBUTES)
                ->searchByName($request->string('q')->value())
                ->activeOrInWarehouse()
                ->when($productPublished !== null, fn (Builder $query) => $query->where('published', $productPublished))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString()
        );
    }

    /**
     * A deactivated product (`published = 0`) is returned normally rather than
     * 404ing — a QR label printed while it was active still needs to resolve
     * to "this product isn't sold anymore" (active: false) instead of looking
     * like an unknown/broken label. The default id barcode resolves first;
     * otherwise the code is matched exactly (as the database compares it)
     * against every barcode in the shared `product_barcodes` table: those
     * added in the WMS, on the store form and by the Otajer import. A code
     * several products hold answers 409 `ambiguous_barcode` with their ids
     * rather than picking one. Stored values are not converted (Arabic-Indic
     * digits) and the legacy `products.barcode` column is not read;
     * `products:barcode-conflicts` lists both. Unknown ids and barcodes
     * return 404. Direct lookups remain exempt from the listing's
     * Product::activeOrInWarehouse() rule even when the warehouse no longer
     * holds any of the product.
     */
    #[DocumentedResponse(409, description: 'The code is not a product id and several products hold it as a barcode (`error_code`: `ambiguous_barcode`; `product_ids` lists them, ascending).', type: 'array{message: string, error_code: string, product_ids: list<int>}')]
    public function show(string $product): ProductResource|JsonResponse
    {
        $query = Product::query()
            ->select('id', 'name', 'ar_name', 'thumbnail_img', 'warehouse_image_path', 'published')
            ->with(Product::WITH_DERIVED_ATTRIBUTES);

        $resolvedProduct = preg_match('/^[1-9][0-9]{0,17}$/', $product)
            ? (clone $query)->find($product)
            : null;

        if ($resolvedProduct !== null) {
            return new ProductResource($resolvedProduct);
        }

        $productIds = ProductBarcode::query()->where('barcode', $product)->distinct()->orderBy('product_id')->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id);

        if ($productIds->count() > 1) {
            return response()->json([
                'message' => __('messages.barcode_ambiguous'),
                'error_code' => 'ambiguous_barcode',
                'product_ids' => $productIds->all(),
            ], HttpResponse::HTTP_CONFLICT);
        }

        return new ProductResource($query->whereKey($productIds->all())->firstOrFail());
    }

    /**
     * Every current pallet of this product, unpaginated, with its location — the
     * mobile app's simple-mode "where is this product?" search. Ordered by
     * expiration_date ascending (nulls last, since an undated pallet never expires
     * soonest), then added_at ascending, then location, so the operator always
     * drives to the oldest-expiring stock first.
     */
    public function pallets(Product $product): AnonymousResourceCollection
    {
        $productBoxesCount = $product->load('setting:product_id,boxes_count')->boxes_count;

        $pallets = Pallet::query()
            ->select(['pallets.id', 'pallets.cell_id', 'pallets.expiration_date', 'pallets.remaining_boxes', 'pallets.created_at'])
            ->join('cells', 'pallets.cell_id', '=', 'cells.id')
            ->join('rows', 'cells.row_id', '=', 'rows.id')
            ->where('pallets.product_id', $product->id)
            ->with([
                'cell:id,row_id,cell_number,flat_number,state,is_active',
                'cell.row:id,letter',
                'cellEnteredLog:cell_status_logs.id,cell_status_logs.pallet_id,cell_status_logs.action,cell_status_logs.created_at',
            ])
            ->orderByRaw('pallets.expiration_date IS NULL')
            ->orderBy('pallets.expiration_date')
            ->orderBy('pallets.created_at')
            ->orderBy('rows.letter')
            ->orderBy('cells.cell_number')
            ->orderBy('cells.flat_number')
            ->get()
            ->each(fn (Pallet $pallet) => $pallet->setAttribute('product_boxes_count', $productBoxesCount));

        return ProductPalletResource::collection($pallets);
    }
}

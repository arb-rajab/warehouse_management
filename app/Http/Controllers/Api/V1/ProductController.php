<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterProductsRequest;
use App\Http\Resources\ProductPalletResource;
use App\Http\Resources\ProductResource;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
                ->select('id', 'name', 'ar_name', 'thumbnail_img', 'published')
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
     * like an unknown/broken label. Both the default id barcode and additional
     * WMS barcodes resolve here, with canonical product ids taking priority.
     * Unknown ids and barcodes return 404. Direct lookups remain exempt from
     * the listing's Product::activeOrInWarehouse() rule even when the warehouse
     * no longer holds any of the product.
     */
    public function show(string $product): ProductResource
    {
        $query = Product::query()
            ->select('id', 'name', 'ar_name', 'thumbnail_img', 'published')
            ->with(Product::WITH_DERIVED_ATTRIBUTES);

        $resolvedProduct = preg_match('/^[1-9][0-9]{0,17}$/', $product)
            ? (clone $query)->find($product)
            : null;

        $resolvedProduct ??= $query
            ->whereHas('barcodes', fn (Builder $query) => $query->where('barcode', $product))
            ->firstOrFail();

        return new ProductResource($resolvedProduct);
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

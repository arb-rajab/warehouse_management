<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterProductsRequest;
use App\Http\Resources\ProductPalletResource;
use App\Http\Resources\ProductResource;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(FilterProductsRequest $request): AnonymousResourceCollection
    {
        // Inactive products are excluded unless the caller explicitly opts in
        // via product_status=inactive.
        $productPublished = $request->productPublished() ?? true;

        return ProductResource::collection(
            Product::query()
                ->select('id', 'name', 'ar_name', 'thumbnail_img', 'published')
                ->with(Product::WITH_DERIVED_ATTRIBUTES)
                ->searchByName($request->string('q')->value())
                ->where('published', $productPublished)
                ->orderBy('name')
                ->paginate(20)
        );
    }

    /**
     * A deactivated product (`published = 0`) is returned normally rather than
     * 404ing — a QR label printed while it was active still needs to resolve
     * to "this product isn't sold anymore" (active: false) instead of looking
     * like an unknown/broken label. Only a genuinely nonexistent id 404s.
     */
    public function show(int $product): ProductResource
    {
        $product = Product::query()
            ->select('id', 'name', 'ar_name', 'thumbnail_img', 'published')
            ->with(Product::WITH_DERIVED_ATTRIBUTES)
            ->find($product);

        abort_if($product === null, 404);

        return new ProductResource($product);
    }

    /**
     * Every current pallet of this product, unpaginated, with its location — the
     * mobile app's simple-mode "where is this product?" search. Ordered by
     * expiration_date ascending (nulls last, since an undated pallet never expires
     * soonest), then added_at ascending, then location, so the operator always
     * drives to the oldest-expiring stock first.
     */
    public function pallets(int $product): AnonymousResourceCollection
    {
        $product = Product::query()
            ->select(['id'])
            ->with('setting:product_id,boxes_count')
            ->find($product);

        abort_if($product === null, 404);

        $productBoxesCount = $product->boxes_count;

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

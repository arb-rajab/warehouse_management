<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FilterProductPalletsRequest;
use App\Http\Requests\Api\V1\FilterProductsRequest;
use App\Http\Requests\Api\V1\StoreProductBarcodeRequest;
use App\Http\Resources\ProductPalletResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\ProductBarcodeAttacher;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private readonly ProductBarcodeAttacher $barcodeAttacher) {}

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
     * drives to the oldest-expiring stock first. Each pallet names its
     * `warehouse`; `warehouse_ids[]` keeps only the pallets stored there.
     * Received but unplaced pallets are stock too, so they are listed with
     * `placed: false` and a null location (their warehouse is their receipt's),
     * in the same expiry order and after the placed ones on a tie.
     */
    public function pallets(FilterProductPalletsRequest $request, Product $product): AnonymousResourceCollection
    {
        $productBoxesCount = $product->load('setting:product_id,boxes_count')->boxes_count;

        $pallets = Pallet::query()
            ->select(['pallets.id', 'pallets.cell_id', 'pallets.receipt_id', 'pallets.expiration_date', 'pallets.remaining_boxes', 'pallets.note', 'pallets.created_at'])
            ->leftJoin('cells', 'pallets.cell_id', '=', 'cells.id')
            ->leftJoin('rows', 'cells.row_id', '=', 'rows.id')
            ->where('pallets.product_id', $product->id)
            ->forWarehouses($request->warehouseIds())
            ->with([
                'cell:id,row_id,cell_number,flat_number,state,is_active',
                'cell.row:id,warehouse_id,letter',
                'cell.row.warehouse:id,name',
                ...Pallet::WITH_RECEIPT_WAREHOUSE,
                'cellEnteredLog:cell_status_logs.id,cell_status_logs.pallet_id,cell_status_logs.action,cell_status_logs.created_at',
            ])
            ->orderByRaw('pallets.expiration_date IS NULL')
            ->orderBy('pallets.expiration_date')
            ->orderBy('pallets.created_at')
            ->orderByRaw('pallets.cell_id IS NULL')
            ->orderBy('rows.letter')
            ->orderBy('cells.cell_number')
            ->orderBy('cells.flat_number')
            ->get()
            ->each(fn (Pallet $pallet) => $pallet->setAttribute('product_boxes_count', $productBoxesCount));

        return ProductPalletResource::collection($pallets);
    }

    /**
     * Adds a barcode to the product, in `product_barcodes`, exactly as the WMS
     * products page does (`ProductBarcodeAttacher`: the value is trimmed,
     * printable ASCII up to 64 characters, checked by `NewProductBarcode` and
     * inserted under its lock). Answers 201 with the product's listed barcodes,
     * the new one included and flagged in `barcode`; the own-id row the import
     * stores is never touched and never listed. A value this product already
     * holds, or the id of a product, is 422 like the WMS form; a value another
     * product holds is 409 `barcode_taken` with those product ids (ascending).
     * An unknown product is 404.
     */
    #[DocumentedResponse(201, description: 'The barcode was added; `barcode` is the new row and `barcodes` every barcode the product lists, oldest first.', type: 'array{data: array{product_id: int, barcode: array{id: int, barcode: string, printable: bool}, barcodes: list<array{id: int, barcode: string, printable: bool}>}}')]
    #[DocumentedResponse(409, description: 'Another product already holds the code (`error_code`: `barcode_taken`; `product_ids` lists those products, ascending).', type: 'array{message: string, error_code: string, product_ids: list<int>}')]
    public function storeBarcode(StoreProductBarcodeRequest $request, Product $product): JsonResponse
    {
        $value = $request->validated('barcode');

        try {
            $created = $this->barcodeAttacher->attach($product, $value);
        } catch (ValidationException $exception) {
            // Lost a race: the value was taken between the request's check and the lock.
            $holders = $this->barcodeAttacher->otherHolders($product, $value);

            if ($holders === []) {
                throw $exception;
            }

            return StoreProductBarcodeRequest::takenResponse($holders);
        }

        $barcodes = ProductBarcode::listedInWms(
            $product->barcodes()->select(['id', 'product_id', 'barcode'])->orderBy('id')->get()
        );

        return response()->json(['data' => [
            'product_id' => $product->id,
            'barcode' => ProductSummaryResource::barcodeShape($created),
            'barcodes' => $barcodes->map(fn (ProductBarcode $barcode): array => ProductSummaryResource::barcodeShape($barcode))->all(),
        ]], HttpResponse::HTTP_CREATED);
    }
}

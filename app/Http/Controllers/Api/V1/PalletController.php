<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ChecksIfMatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkPalletNoteRequest;
use App\Http\Requests\Api\V1\ChangePalletProductRequest;
use App\Http\Requests\Api\V1\EmptyPalletRequest;
use App\Http\Requests\Api\V1\FilterPalletsRequest;
use App\Http\Requests\Api\V1\FilterUnplacedPalletsRequest;
use App\Http\Requests\Api\V1\OpenPalletRequest;
use App\Http\Requests\Api\V1\PlacePalletRequest;
use App\Http\Requests\Api\V1\RemovePalletBoxesRequest;
use App\Http\Requests\Api\V1\StorePalletRequest;
use App\Http\Requests\Api\V1\TransferPalletRequest;
use App\Http\Resources\PalletListResource;
use App\Http\Resources\PalletResource;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use App\Services\PalletActionService;
use App\Services\PalletCorrectionService;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PalletController extends Controller
{
    use ChecksIfMatch;

    private const array EAGER_LOAD = [
        'product:id,name,ar_name,thumbnail_img,warehouse_image_path,published',
        'product.thumbnailUpload:id,file_name,external_link',
        'product.setting:product_id,boxes_count',
        'cell.row:id,warehouse_id,letter',
        'cell.row.warehouse:id,name',
        ...Pallet::WITH_RECEIPT_WAREHOUSE,
        // Table-qualified — see the comment on Cell::WITH_CONTENTS for why.
        'cellEnteredLog:cell_status_logs.id,cell_status_logs.pallet_id,cell_status_logs.action,cell_status_logs.created_at',
    ];

    /**
     * What PalletListResource reads: PalletResource's relations, the receipt
     * with its creation time, and the cell's state for the ETag.
     */
    private const array LIST_EAGER_LOAD = [
        'product:id,name,ar_name,thumbnail_img,warehouse_image_path,published',
        'product.thumbnailUpload:id,file_name,external_link',
        'product.setting:product_id,boxes_count',
        'cell.row:id,warehouse_id,letter',
        'cell.row.warehouse:id,name',
        'receipt:id,warehouse_id,created_at',
        'receipt.warehouse:id,name',
        // Table-qualified — see the comment on Cell::WITH_CONTENTS for why.
        'cellEnteredLog:cell_status_logs.id,cell_status_logs.pallet_id,cell_status_logs.action,cell_status_logs.created_at',
    ];

    public function __construct(
        private readonly PalletActionService $palletActions,
        private readonly PalletCorrectionService $palletCorrections,
    ) {}

    /**
     * Every current pallet, placed and unplaced, for the admin to find and
     * select (for the bulk note and the product change). Sorted by product
     * name, then note (pallets without one last), then oldest first, so the
     * pallets of one product with the same note sit together. Each item adds
     * the receipt it came from (`{id, created_at}` or null) and its `etag`.
     * `per_page` up to 200 (default 20). Warehouse admins only.
     */
    public function index(FilterPalletsRequest $request): AnonymousResourceCollection
    {
        $placed = $request->booleanFilter('placed');
        $hasNote = $request->booleanFilter('has_note');

        $pallets = Pallet::query()
            ->when($request->productIds(), fn (Builder $query, array $productIds) => $query->whereIn('product_id', $productIds))
            ->forWarehouses($request->warehouseIds())
            ->when($placed === true, fn (Builder $query) => $query->placed())
            ->when($placed === false, fn (Builder $query) => $query->unplaced())
            ->when($hasNote === true, fn (Builder $query) => $query->whereNotNull('note'))
            ->when($hasNote === false, fn (Builder $query) => $query->whereNull('note'))
            ->when($request->filled('receipt_id'), fn (Builder $query) => $query->where('receipt_id', $request->integer('receipt_id')))
            ->when($request->searchTerm(), function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';

                $query->where(fn (Builder $matches) => $matches
                    ->whereRaw('pallets.note like ? escape ?', [$like, '\\'])
                    ->orWhereHas('product', fn (Builder $product) => $product
                        ->whereRaw('products.name like ? escape ?', [$like, '\\'])
                        ->orWhereRaw('products.ar_name like ? escape ?', [$like, '\\'])));
            })
            ->with(self::LIST_EAGER_LOAD)
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'pallets.product_id'))
            ->orderBy('product_id')
            ->orderByRaw('pallets.note IS NULL')
            ->orderBy('note')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return PalletListResource::collection($pallets);
    }

    /**
     * Give the selected pallets (1 to 500) one shared note; `null` clears it.
     * A sent `etag` per pallet is checked like `If-Match`. All or nothing:
     * when any pallet fails, nothing is written and the 409 names each
     * failing pallet. The cell log gets one `note_changed` entry per pallet
     * whose note changed (with `old_note`/`new_note`), all with one
     * `batch_id`. Answers the selected pallets, in the order sent, with their
     * new `etag`. Warehouse admins only.
     */
    #[DocumentedResponse(409, description: 'At least one pallet could not be changed and nothing was written (`error_code`: `bulk_rejected`); `failed` lists each one with its `error_code`: `pallet_not_found` (gone or emptied) or `pallet_changed` (its `etag` no longer matches).', type: "array{message: string, error_code: 'bulk_rejected', failed: list<array{id: int, error_code: string, message: string}>}")]
    public function bulkNote(BulkPalletNoteRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return $this->listed($this->palletCorrections->changeNote($request->selectedPallets(), $request->note(), $user->id));
    }

    /**
     * Change the product of the selected pallets (1 to 500; one pallet is a
     * list of one), whatever product they hold now, in any warehouse, placed
     * or not. The target product must be active; `reason` (3 to 255
     * characters) is kept in the history. Location, state, boxes and expiry
     * stay; the note is cleared unless `keep_note` is true. A sent `etag` per
     * pallet is checked like `If-Match`. All or nothing. The cell log gets one
     * `product_changed` entry per pallet (`old_product`, `new_product`,
     * `reason`, `old_note`/`new_note`, the user), all with one `batch_id`.
     * Answers the pallets, in the order sent, with their new `etag`.
     * Warehouse admins only.
     */
    #[DocumentedResponse(409, description: 'At least one pallet could not be changed and nothing was written (`error_code`: `bulk_rejected`); `failed` lists each one with its `error_code`: `pallet_not_found` (gone or emptied), `pallet_changed` (its `etag` no longer matches), `pallet_same_product` (it already holds the product) or `cell_in_active_round` (its row is being verified).', type: "array{message: string, error_code: 'bulk_rejected', failed: list<array{id: int, error_code: string, message: string}>}")]
    public function changeProduct(ChangePalletProductRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return $this->listed($this->palletCorrections->changeProduct(
            $request->selectedPallets(),
            $request->integer('product_id'),
            $request->string('reason')->trim()->value(),
            $request->boolean('keep_note'),
            $user->id,
        ));
    }

    /**
     * The given pallets as the pallet list presents them, in the given order.
     *
     * @param  list<int>  $ids
     */
    private function listed(array $ids): AnonymousResourceCollection
    {
        $order = array_flip($ids);

        return PalletListResource::collection(Pallet::query()
            ->whereKey($ids)
            ->with(self::LIST_EAGER_LOAD)
            ->get()
            ->sortBy(fn (Pallet $pallet): int => $order[$pallet->id])
            ->values());
    }

    #[DocumentedResponse(409, description: 'The requested slot is not empty (`error_code`: `slot_not_empty`).', type: "array{message: string, error_code: 'slot_not_empty'}")]
    #[DocumentedResponse(409, description: 'The requested slot is inactive (`error_code`: `slot_inactive`).', type: "array{message: string, error_code: 'slot_inactive'}")]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: "array{message: string, error_code: 'cell_in_active_round'}")]
    public function store(StorePalletRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $pallet = $this->palletActions->store(
            $request->resolvedSlot()->id,
            $request->integer('product_id'),
            $request->input('expiration_date'),
            $user->id,
            $request->input('note'),
            $request->imageReviewData(),
        );

        return (new PalletResource($pallet->load(self::EAGER_LOAD)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }

    /**
     * The received pallets that have no cell yet (`placed: false`, null
     * `state` and `location`, `warehouse` from their receipt), oldest received
     * first, 20 per page. `warehouse_ids[]` keeps those received into these
     * warehouses and `product_id[]` those of these products. Place one with
     * `POST pallets/{pallet}/place`.
     */
    public function unplaced(FilterUnplacedPalletsRequest $request): AnonymousResourceCollection
    {
        $pallets = Pallet::query()
            ->select(['id', 'product_id', 'cell_id', 'receipt_id', 'expiration_date', 'remaining_boxes', 'note', 'created_at'])
            ->unplaced()
            ->when($request->productIds(), fn (Builder $query, array $productIds) => $query->whereIn('product_id', $productIds))
            ->forWarehouses($request->warehouseIds())
            ->with(self::EAGER_LOAD)
            ->oldest('created_at')
            ->oldest('id')
            ->paginate(20)
            ->withQueryString();

        return PalletResource::collection($pallets);
    }

    /**
     * Place a received, unplaced pallet into an empty slot of its own
     * warehouse (the coordinates and optional `warehouse_id` as on pallet
     * store). The cell becomes full (opened on a first level where opening is
     * allowed) and the cell log records it as `stored`. Honours `If-Match`
     * like the other pallet actions; the response carries the new `ETag`.
     */
    #[DocumentedResponse(409, description: 'The pallet is already in a cell; move it with a transfer (`error_code`: `pallet_already_placed`).', type: "array{message: string, error_code: 'pallet_already_placed'}")]
    #[DocumentedResponse(409, description: 'The slot is in another warehouse than the pallet\'s receipt (`error_code`: `pallet_warehouse_mismatch`).', type: "array{message: string, error_code: 'pallet_warehouse_mismatch'}")]
    #[DocumentedResponse(409, description: 'The requested slot is not empty (`error_code`: `slot_not_empty`).', type: "array{message: string, error_code: 'slot_not_empty'}")]
    #[DocumentedResponse(409, description: 'The requested slot is inactive (`error_code`: `slot_inactive`).', type: "array{message: string, error_code: 'slot_inactive'}")]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: "array{message: string, error_code: 'cell_in_active_round'}")]
    #[DocumentedResponse(412, description: 'The `If-Match` header no longer matches the pallet\'s current `ETag` (`error_code`: `precondition_failed`).', type: 'array{message: string, error_code: string}')]
    public function place(PlacePalletRequest $request, Pallet $pallet): PalletResource
    {
        /** @var User $user */
        $user = $request->user();

        $pallet = $this->palletActions->place(
            $pallet,
            $request->resolvedSlot()->id,
            $user->id,
            $request->input('note'),
            $this->ifMatchEtags($request),
        );

        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'Only a full pallet can be opened (`error_code`: `pallet_not_full`).', type: "array{message: string, error_code: 'pallet_not_full'}")]
    #[DocumentedResponse(409, description: 'The pallet\'s level is not one of the levels where opening is allowed (`error_code`: `open_level_not_allowed`).', type: "array{message: string, error_code: 'open_level_not_allowed'}")]
    #[DocumentedResponse(409, description: 'The requested boxes_count meets or exceeds what remains on the pallet, and confirm_empty was not set (`error_code`: `insufficient_boxes_remaining`).', type: "array{message: string, error_code: 'insufficient_boxes_remaining'}")]
    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: "array{message: string, error_code: 'slot_inactive'}")]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: "array{message: string, error_code: 'cell_in_active_round'}")]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: "array{message: string, error_code: 'pallet_not_placed'}")]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: "array{message: string, error_code: 'pallet_moved'}")]
    #[DocumentedResponse(412, description: 'The `If-Match` header no longer matches the pallet\'s current `ETag` (`error_code`: `precondition_failed`).', type: 'array{message: string, error_code: string}')]
    public function open(OpenPalletRequest $request, Pallet $pallet): PalletResource|Response
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->palletActions->open(
            $pallet,
            $request->filled('boxes_count') ? $request->integer('boxes_count') : null,
            $request->boolean('confirm_empty'),
            $user->id,
            $request->input('note'),
            ifMatchEtags: $this->ifMatchEtags($request),
        );

        if ($result['emptied']) {
            return response()->noContent();
        }

        return new PalletResource($result['pallet']->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'Boxes can only be removed from an opened pallet (`error_code`: `pallet_not_opened`).', type: "array{message: string, error_code: 'pallet_not_opened'}")]
    #[DocumentedResponse(409, description: 'The requested boxes_count meets or exceeds what remains on the pallet, and confirm_empty was not set (`error_code`: `insufficient_boxes_remaining`).', type: "array{message: string, error_code: 'insufficient_boxes_remaining'}")]
    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: "array{message: string, error_code: 'slot_inactive'}")]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: "array{message: string, error_code: 'cell_in_active_round'}")]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: "array{message: string, error_code: 'pallet_not_placed'}")]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: "array{message: string, error_code: 'pallet_moved'}")]
    #[DocumentedResponse(412, description: 'The `If-Match` header no longer matches the pallet\'s current `ETag` (`error_code`: `precondition_failed`).', type: 'array{message: string, error_code: string}')]
    public function removeBoxes(RemovePalletBoxesRequest $request, Pallet $pallet): PalletResource|Response
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->palletActions->removeBoxes(
            $pallet,
            $request->integer('boxes_count'),
            $request->boolean('confirm_empty'),
            $user->id,
            $request->input('note'),
            ifMatchEtags: $this->ifMatchEtags($request),
        );

        if ($result['emptied']) {
            return response()->noContent();
        }

        return new PalletResource($result['pallet']->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: "array{message: string, error_code: 'slot_inactive'}")]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: "array{message: string, error_code: 'cell_in_active_round'}")]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: "array{message: string, error_code: 'pallet_not_placed'}")]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: "array{message: string, error_code: 'pallet_moved'}")]
    #[DocumentedResponse(412, description: 'The `If-Match` header no longer matches the pallet\'s current `ETag` (`error_code`: `precondition_failed`).', type: 'array{message: string, error_code: string}')]
    public function empty(EmptyPalletRequest $request, Pallet $pallet): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->palletActions->emptyPallet($pallet, $user->id, $request->input('note'), $this->ifMatchEtags($request));

        return response()->noContent();
    }

    #[DocumentedResponse(409, description: 'The destination slot is not empty (`error_code`: `destination_not_empty`).', type: "array{message: string, error_code: 'destination_not_empty'}")]
    #[DocumentedResponse(409, description: 'The source or destination cell is inactive (`error_code`: `slot_inactive`).', type: "array{message: string, error_code: 'slot_inactive'}")]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: "array{message: string, error_code: 'cell_in_active_round'}")]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: "array{message: string, error_code: 'pallet_not_placed'}")]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: "array{message: string, error_code: 'pallet_moved'}")]
    #[DocumentedResponse(409, description: 'The destination is the cell the pallet is already in (`error_code`: `pallet_already_at_location`).', type: "array{message: string, error_code: 'pallet_already_at_location'}")]
    #[DocumentedResponse(412, description: 'The `If-Match` header no longer matches the pallet\'s current `ETag` (`error_code`: `precondition_failed`).', type: 'array{message: string, error_code: string}')]
    public function transfer(TransferPalletRequest $request, Pallet $pallet): PalletResource
    {
        /** @var User $user */
        $user = $request->user();

        $pallet = $this->palletActions->transfer(
            $pallet,
            $request->resolvedSlot()->id,
            $user->id,
            $request->input('note'),
            $this->ifMatchEtags($request),
        );

        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }
}

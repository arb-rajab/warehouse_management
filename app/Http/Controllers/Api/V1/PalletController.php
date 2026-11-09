<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ChecksIfMatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyPalletRequest;
use App\Http\Requests\Api\V1\FilterUnplacedPalletsRequest;
use App\Http\Requests\Api\V1\OpenPalletRequest;
use App\Http\Requests\Api\V1\PlacePalletRequest;
use App\Http\Requests\Api\V1\RemovePalletBoxesRequest;
use App\Http\Requests\Api\V1\StorePalletRequest;
use App\Http\Requests\Api\V1\TransferPalletRequest;
use App\Http\Resources\PalletResource;
use App\Models\Pallet;
use App\Models\User;
use App\Services\PalletActionService;
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

    public function __construct(private readonly PalletActionService $palletActions) {}

    #[DocumentedResponse(409, description: 'The requested slot is not empty (`error_code`: `slot_not_empty`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested slot is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: 'array{message: string, error_code: string}')]
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
            ->select(['id', 'product_id', 'cell_id', 'receipt_line_id', 'expiration_date', 'remaining_boxes', 'created_at'])
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
    #[DocumentedResponse(409, description: 'The pallet is already in a cell; move it with a transfer (`error_code`: `pallet_already_placed`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The slot is in another warehouse than the pallet\'s receipt (`error_code`: `pallet_warehouse_mismatch`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested slot is not empty (`error_code`: `slot_not_empty`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested slot is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: 'array{message: string, error_code: string}')]
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

    #[DocumentedResponse(409, description: 'Only a full pallet can be opened (`error_code`: `pallet_not_full`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet\'s level is not one of the levels where opening is allowed (`error_code`: `open_level_not_allowed`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested boxes_count meets or exceeds what remains on the pallet, and confirm_empty was not set (`error_code`: `insufficient_boxes_remaining`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: 'array{message: string, error_code: string}')]
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

    #[DocumentedResponse(409, description: 'Boxes can only be removed from an opened pallet (`error_code`: `pallet_not_opened`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested boxes_count meets or exceeds what remains on the pallet, and confirm_empty was not set (`error_code`: `insufficient_boxes_remaining`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: 'array{message: string, error_code: string}')]
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

    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(412, description: 'The `If-Match` header no longer matches the pallet\'s current `ETag` (`error_code`: `precondition_failed`).', type: 'array{message: string, error_code: string}')]
    public function empty(EmptyPalletRequest $request, Pallet $pallet): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->palletActions->emptyPallet($pallet, $user->id, $request->input('note'), $this->ifMatchEtags($request));

        return response()->noContent();
    }

    #[DocumentedResponse(409, description: 'The destination slot is not empty (`error_code`: `destination_not_empty`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The source or destination cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The cell\'s row is being verified by an unfinished round (`error_code`: `cell_in_active_round`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet has no cell yet; place it first (`error_code`: `pallet_not_placed`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet was moved by another request after it was loaded (`error_code`: `pallet_moved`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The destination is the cell the pallet is already in (`error_code`: `pallet_already_at_location`).', type: 'array{message: string, error_code: string}')]
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

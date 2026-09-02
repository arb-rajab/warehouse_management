<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Exceptions\InvalidSlotStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyPalletRequest;
use App\Http\Requests\Api\V1\OpenPalletRequest;
use App\Http\Requests\Api\V1\RemovePalletBoxesRequest;
use App\Http\Requests\Api\V1\StorePalletRequest;
use App\Http\Requests\Api\V1\TransferPalletRequest;
use App\Http\Resources\PalletResource;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PalletController extends Controller
{
    private const array EAGER_LOAD = ['product:id,name,image_url,boxes_count', 'cell.row:id,letter'];

    /**
     * Read a cell inside the surrounding transaction, holding a row lock on it so
     * concurrent requests cannot act on the same slot at the same time. Rejects an
     * inactive (corrupted / out of service) cell so every pallet action on either
     * side of a transfer is blocked while a cell is deactivated.
     */
    private function lockCell(int $cellId): Cell
    {
        $cell = Cell::query()->lockForUpdate()->findOrFail($cellId);

        if (! $cell->is_active) {
            throw new InvalidSlotStateException('slot_inactive', __('messages.slot_inactive'));
        }

        return $cell;
    }

    /**
     * Read a pallet inside the surrounding transaction, holding a row lock on it so
     * concurrent requests cannot decrement its remaining_boxes at the same time.
     */
    private function lockPallet(int $palletId): Pallet
    {
        return Pallet::query()->lockForUpdate()->findOrFail($palletId);
    }

    /**
     * Whether a pallet currently has at least the given number of boxes left on it.
     */
    private function hasEnoughBoxes(Pallet $pallet, int $boxesCount): bool
    {
        return $boxesCount <= $pallet->remaining_boxes;
    }

    /**
     * Decrement a pallet's remaining_boxes by the given amount. Callers must check
     * hasEnoughBoxes() first — this does not guard against going negative.
     */
    private function decrementRemainingBoxes(Pallet $pallet, int $boxesCount): void
    {
        $pallet->update(['remaining_boxes' => $pallet->remaining_boxes - $boxesCount]);
    }

    /**
     * Empty an already cell-locked, pallet-locked pallet: delete it, free its cell,
     * and log the Emptied transition. Shared by empty() and by open()/removeBoxes()
     * when the caller confirms emptying instead of a boxes_count that would exceed
     * what remains (see the confirm_empty request field).
     */
    private function emptyLockedPallet(Cell $cell, Pallet $pallet, int $userId, ?string $note): void
    {
        $fromState = $cell->state;
        $productId = $pallet->product_id;
        $palletId = $pallet->id;
        $remainingBoxes = $pallet->remaining_boxes;

        $pallet->delete();

        $cell->update(['state' => CellState::Empty]);

        $this->logCellStatus(
            $cell,
            CellLogAction::Emptied,
            $fromState,
            CellState::Empty,
            $productId,
            $palletId,
            $userId,
            $note,
            boxesCount: $remainingBoxes,
        );
    }

    /**
     * The shared insufficient-boxes/confirm_empty flow for open() and removeBoxes():
     * decrement the pallet's remaining_boxes when it has enough, or — when it doesn't
     * and the caller set confirm_empty — empty the pallet instead of throwing. Returns
     * true when the pallet was emptied (caller should treat this as the terminal
     * outcome and skip its own state/log follow-up), false when boxes were decremented
     * normally (caller proceeds with its own state/log follow-up).
     */
    private function applyBoxesRemoval(Request $request, Cell $cell, Pallet $lockedPallet, User $user, int $boxesCount): bool
    {
        if (! $this->hasEnoughBoxes($lockedPallet, $boxesCount)) {
            if (! $request->boolean('confirm_empty')) {
                throw new InvalidSlotStateException('insufficient_boxes_remaining', __('messages.insufficient_boxes_remaining'));
            }

            $this->emptyLockedPallet($cell, $lockedPallet, $user->id, $request->input('note'));

            return true;
        }

        $this->decrementRemainingBoxes($lockedPallet, $boxesCount);

        return false;
    }

    /**
     * Write a CellStatusLog row for a cell state transition.
     */
    private function logCellStatus(
        Cell $cell,
        CellLogAction $action,
        CellState $fromState,
        CellState $toState,
        ?int $productId,
        ?int $palletId,
        int $userId,
        ?string $note,
        ?int $relatedCellId = null,
        ?int $boxesCount = null,
    ): void {
        CellStatusLog::create([
            'cell_id' => $cell->id,
            'related_cell_id' => $relatedCellId,
            'action' => $action,
            'from_state' => $fromState,
            'to_state' => $toState,
            'product_id' => $productId,
            'pallet_id' => $palletId,
            'boxes_count' => $boxesCount,
            'user_id' => $userId,
            'note' => $note,
        ]);
    }

    #[DocumentedResponse(409, description: 'The requested slot is not empty (`error_code`: `slot_not_empty`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested slot is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    public function store(StorePalletRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $pallet = DB::transaction(function () use ($request, $user) {
            $cell = $this->lockCell($request->resolvedSlot()->id);

            if ($cell->state !== CellState::Empty) {
                throw new InvalidSlotStateException('slot_not_empty', __('messages.slot_not_empty'));
            }

            $product = Product::query()->findOrFail($request->integer('product_id'));

            $pallet = Pallet::create([
                'product_id' => $product->id,
                'cell_id' => $cell->id,
                'expiration_date' => $request->input('expiration_date'),
                'remaining_boxes' => $product->boxes_count,
            ]);

            $cell->update(['state' => CellState::Full]);

            $this->logCellStatus(
                $cell,
                CellLogAction::Stored,
                CellState::Empty,
                CellState::Full,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
                boxesCount: $pallet->remaining_boxes,
            );

            return $pallet;
        });

        return (new PalletResource($pallet->load(self::EAGER_LOAD)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'Only a full pallet can be opened (`error_code`: `pallet_not_full`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested boxes_count exceeds what remains on the pallet, and confirm_empty was not set (`error_code`: `insufficient_boxes_remaining`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    public function open(OpenPalletRequest $request, Pallet $pallet): PalletResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $emptied = false;

        DB::transaction(function () use ($request, $pallet, $user, &$emptied) {
            $cell = $this->lockCell($pallet->cell_id);
            $lockedPallet = $this->lockPallet($pallet->id);

            if ($cell->state !== CellState::Full) {
                throw new InvalidSlotStateException('pallet_not_full', __('messages.pallet_not_full'));
            }

            $boxesCount = $request->integer('boxes_count');

            if ($this->applyBoxesRemoval($request, $cell, $lockedPallet, $user, $boxesCount)) {
                $emptied = true;

                return;
            }

            $cell->update(['state' => CellState::Opened]);

            $this->logCellStatus(
                $cell,
                CellLogAction::Opened,
                CellState::Full,
                CellState::Opened,
                $lockedPallet->product_id,
                $lockedPallet->id,
                $user->id,
                $request->input('note'),
                boxesCount: $lockedPallet->remaining_boxes,
            );
        });

        if ($emptied) {
            return response()->json(null, 204);
        }

        return new PalletResource($pallet->refresh()->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'Boxes can only be removed from an opened pallet (`error_code`: `pallet_not_opened`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The requested boxes_count exceeds what remains on the pallet, and confirm_empty was not set (`error_code`: `insufficient_boxes_remaining`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    public function removeBoxes(RemovePalletBoxesRequest $request, Pallet $pallet): PalletResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $emptied = false;

        DB::transaction(function () use ($request, $pallet, $user, &$emptied) {
            $cell = $this->lockCell($pallet->cell_id);
            $lockedPallet = $this->lockPallet($pallet->id);

            if ($cell->state !== CellState::Opened) {
                throw new InvalidSlotStateException('pallet_not_opened', __('messages.pallet_not_opened'));
            }

            $boxesCount = $request->integer('boxes_count');

            if ($this->applyBoxesRemoval($request, $cell, $lockedPallet, $user, $boxesCount)) {
                $emptied = true;

                return;
            }

            $this->logCellStatus(
                $cell,
                CellLogAction::BoxesRemoved,
                $cell->state,
                $cell->state,
                $lockedPallet->product_id,
                $lockedPallet->id,
                $user->id,
                $request->input('note'),
                boxesCount: $lockedPallet->remaining_boxes,
            );
        });

        if ($emptied) {
            return response()->json(null, 204);
        }

        return new PalletResource($pallet->refresh()->load(self::EAGER_LOAD));
    }

    #[DocumentedResponse(409, description: 'The pallet\'s cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    public function empty(EmptyPalletRequest $request, Pallet $pallet): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $pallet, $user) {
            $cell = $this->lockCell($pallet->cell_id);
            $lockedPallet = $this->lockPallet($pallet->id);

            $this->emptyLockedPallet($cell, $lockedPallet, $user->id, $request->input('note'));
        });

        return response()->json(null, 204);
    }

    #[DocumentedResponse(409, description: 'The destination slot is not empty (`error_code`: `destination_not_empty`).', type: 'array{message: string, error_code: string}')]
    #[DocumentedResponse(409, description: 'The source or destination cell is inactive (`error_code`: `slot_inactive`).', type: 'array{message: string, error_code: string}')]
    public function transfer(TransferPalletRequest $request, Pallet $pallet): PalletResource
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($pallet, $request, $user) {
            $sourceCell = $this->lockCell($pallet->cell_id);
            $destinationCell = $this->lockCell($request->resolvedSlot()->id);

            if ($destinationCell->state !== CellState::Empty) {
                throw new InvalidSlotStateException('destination_not_empty', __('messages.destination_not_empty'));
            }

            $carryOverState = $sourceCell->state;

            $sourceCell->update(['state' => CellState::Empty]);
            $destinationCell->update(['state' => $carryOverState]);
            $pallet->update(['cell_id' => $destinationCell->id]);

            $this->logCellStatus(
                $sourceCell,
                CellLogAction::TransferredOut,
                $carryOverState,
                CellState::Empty,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
                relatedCellId: $destinationCell->id,
                boxesCount: $pallet->remaining_boxes,
            );

            $this->logCellStatus(
                $destinationCell,
                CellLogAction::TransferredIn,
                CellState::Empty,
                $carryOverState,
                $pallet->product_id,
                $pallet->id,
                $user->id,
                $request->input('note'),
                relatedCellId: $sourceCell->id,
                boxesCount: $pallet->remaining_boxes,
            );
        });

        return new PalletResource($pallet->load(self::EAGER_LOAD));
    }
}

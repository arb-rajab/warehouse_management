<?php

namespace App\Services;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Exceptions\InvalidSlotStateException;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * The cell/pallet occupancy state machine (store/open/remove-boxes/empty/transfer),
 * shared by every caller that can perform these actions — currently
 * Api\V1\PalletController (mobile app) and Admin\PalletController (admin web UI).
 * Every public method here opens its own transaction, locks the cell(s)/pallet it
 * touches, and writes the matching CellStatusLog row before returning.
 */
class PalletActionService
{
    /**
     * Read a cell inside the surrounding transaction, holding a row lock on it so
     * concurrent requests cannot act on the same slot at the same time. Rejects an
     * inactive (corrupted / out of service) cell so every pallet action on either
     * side of a transfer is blocked while a cell is deactivated.
     */
    public function lockCell(int $cellId): Cell
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
     * Whether a pallet currently has more boxes left on it than the given amount.
     * A boxes_count equal to remaining_boxes would empty the pallet, so it's treated
     * the same as exceeding it — routed through the confirm_empty flow rather than
     * silently emptied, in case the worker meant to remove some boxes rather than all
     * of them and just mistyped the exact remaining count.
     */
    private function hasEnoughBoxes(Pallet $pallet, int $boxesCount): bool
    {
        return $boxesCount < $pallet->remaining_boxes;
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
     * and log the Emptied transition. Shared by emptyPallet() and by open()/
     * removeBoxes() when the caller confirms emptying instead of a boxes_count that
     * would exceed what remains.
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
     * and the caller confirmed — empty the pallet instead of throwing. Returns true
     * when the pallet was emptied (caller should treat this as the terminal outcome
     * and skip its own state/log follow-up), false when boxes were decremented
     * normally (caller proceeds with its own state/log follow-up).
     */
    private function applyBoxesRemoval(Cell $cell, Pallet $lockedPallet, int $userId, int $boxesCount, bool $confirmEmpty, ?string $note): bool
    {
        if (! $this->hasEnoughBoxes($lockedPallet, $boxesCount)) {
            if (! $confirmEmpty) {
                throw new InvalidSlotStateException('insufficient_boxes_remaining', __('messages.insufficient_boxes_remaining'));
            }

            $this->emptyLockedPallet($cell, $lockedPallet, $userId, $note);

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

    /**
     * Store a new pallet into an Empty cell, transitioning it to Full.
     */
    public function store(int $cellId, int $productId, string $expirationDate, int $userId, ?string $note): Pallet
    {
        return DB::transaction(function () use ($cellId, $productId, $expirationDate, $userId, $note) {
            $cell = $this->lockCell($cellId);

            if ($cell->state !== CellState::Empty) {
                throw new InvalidSlotStateException('slot_not_empty', __('messages.slot_not_empty'));
            }

            // `boxes_count` resolves through the wms_product_settings relation,
            // so it has to be loaded here rather than read off the products row.
            $product = Product::query()
                ->select(['id'])
                ->with('setting:product_id,boxes_count')
                ->findOrFail($productId);

            $pallet = Pallet::create([
                'product_id' => $product->id,
                'cell_id' => $cell->id,
                'expiration_date' => $expirationDate,
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
                $userId,
                $note,
                boxesCount: $pallet->remaining_boxes,
            );

            return $pallet;
        });
    }

    /**
     * Open a Full pallet (Full→Opened), removing its initial boxes_count in the
     * same transaction — see .ai/rules/v1.md for why this is a deliberate merge.
     *
     * @return array{pallet: ?Pallet, emptied: bool}
     */
    public function open(Pallet $pallet, int $boxesCount, bool $confirmEmpty, int $userId, ?string $note): array
    {
        $emptied = false;

        DB::transaction(function () use ($pallet, $boxesCount, $confirmEmpty, $userId, $note, &$emptied) {
            $cell = $this->lockCell($pallet->cell_id);
            $lockedPallet = $this->lockPallet($pallet->id);

            if ($cell->state !== CellState::Full) {
                throw new InvalidSlotStateException('pallet_not_full', __('messages.pallet_not_full'));
            }

            if ($this->applyBoxesRemoval($cell, $lockedPallet, $userId, $boxesCount, $confirmEmpty, $note)) {
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
                $userId,
                $note,
                boxesCount: $lockedPallet->remaining_boxes,
            );
        });

        return ['pallet' => $emptied ? null : $pallet->refresh(), 'emptied' => $emptied];
    }

    /**
     * Remove more boxes from an already-Opened pallet.
     *
     * @return array{pallet: ?Pallet, emptied: bool}
     */
    public function removeBoxes(Pallet $pallet, int $boxesCount, bool $confirmEmpty, int $userId, ?string $note): array
    {
        $emptied = false;

        DB::transaction(function () use ($pallet, $boxesCount, $confirmEmpty, $userId, $note, &$emptied) {
            $cell = $this->lockCell($pallet->cell_id);
            $lockedPallet = $this->lockPallet($pallet->id);

            if ($cell->state !== CellState::Opened) {
                throw new InvalidSlotStateException('pallet_not_opened', __('messages.pallet_not_opened'));
            }

            if ($this->applyBoxesRemoval($cell, $lockedPallet, $userId, $boxesCount, $confirmEmpty, $note)) {
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
                $userId,
                $note,
                boxesCount: $lockedPallet->remaining_boxes,
            );
        });

        return ['pallet' => $emptied ? null : $pallet->refresh(), 'emptied' => $emptied];
    }

    /**
     * Empty a pallet regardless of remaining_boxes: delete it and free its cell.
     */
    public function emptyPallet(Pallet $pallet, int $userId, ?string $note): void
    {
        DB::transaction(function () use ($pallet, $userId, $note) {
            $cell = $this->lockCell($pallet->cell_id);
            $lockedPallet = $this->lockPallet($pallet->id);

            $this->emptyLockedPallet($cell, $lockedPallet, $userId, $note);
        });
    }

    /**
     * Move a pallet to another Empty cell — the source cell frees to Empty, the
     * destination inherits the source's prior state.
     */
    public function transfer(Pallet $pallet, int $destinationCellId, int $userId, ?string $note): Pallet
    {
        DB::transaction(function () use ($pallet, $destinationCellId, $userId, $note) {
            $sourceCell = $this->lockCell($pallet->cell_id);
            $destinationCell = $this->lockCell($destinationCellId);

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
                $userId,
                $note,
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
                $userId,
                $note,
                relatedCellId: $sourceCell->id,
                boxesCount: $pallet->remaining_boxes,
            );
        });

        return $pallet->refresh();
    }
}

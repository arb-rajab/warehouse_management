<?php

namespace App\Services;

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Exceptions\InvalidSlotStateException;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Setting;
use Illuminate\Support\Carbon;
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
     *
     * Also rejects a cell whose row an unfinished verification round claims: the
     * point of a round is to compare the shelf against what the system recorded,
     * which a pallet moving mid-count would invalidate. Both surfaces that can
     * perform these actions go through here, so this covers the mobile app and the
     * admin panel alike — Admin\PalletController catches InvalidSlotStateException
     * specifically, which is why this is that exception rather than a new type.
     *
     * Deliberately not blocked: Admin\CellController::toggleActive(), which takes a
     * cell in or out of service without touching pallet contents and is the
     * maintenance escape hatch for a shelf found damaged mid-round.
     */
    public function lockCell(int $cellId): Cell
    {
        $cell = $this->lockCellRow($cellId);

        $this->assertCellAcceptsPalletActions($cell);

        return $cell;
    }

    /**
     * Take the row lock on a cell without lockCell()'s guards, for callers that
     * still have to check something more specific first (see lockPalletInCell()).
     */
    private function lockCellRow(int $cellId): Cell
    {
        return Cell::query()->lockForUpdate()->findOrFail($cellId);
    }

    /**
     * The inactive-cell and active-round guards described on lockCell().
     */
    private function assertCellAcceptsPalletActions(Cell $cell): void
    {
        if (! $cell->is_active) {
            throw new InvalidSlotStateException('slot_inactive', __('messages.slot_inactive'));
        }

        if (Row::query()->whereKey($cell->row_id)->underActiveVerification()->exists()) {
            throw new InvalidSlotStateException('cell_in_active_round', __('messages.cell_in_active_round'));
        }
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
     * Lock an existing pallet together with the cell it sits in.
     *
     * Every lock in this service — and in CellVerificationService, which reaches
     * a pallet only through its cell — is taken cell first, pallet second, so two
     * transactions never wait on each other in opposite orders. The price is that
     * the cell is resolved from the caller's `$pallet->cell_id`, read before any
     * lock was held: a concurrent transfer can have moved the pallet since. So
     * once both locks are held, the locked pallet's own cell_id is checked
     * against the cell locked for it, and a pallet that moved is refused with
     * `pallet_moved` instead of acting on — and logging against — the cell it
     * used to be in. That check runs before the cell's own guards, so the error
     * names the real problem rather than the state of a cell the pallet left.
     *
     * @return array{0: Cell, 1: Pallet}
     */
    private function lockPalletInCell(Pallet $pallet): array
    {
        $cell = $this->lockCellRow($pallet->cell_id);
        $lockedPallet = $this->lockPallet($pallet->id);

        $this->assertPalletStillInCell($lockedPallet, $cell);
        $this->assertCellAcceptsPalletActions($cell);

        return [$cell, $lockedPallet];
    }

    private function assertPalletStillInCell(Pallet $lockedPallet, Cell $cell): void
    {
        if ($lockedPallet->cell_id !== $cell->id) {
            throw new InvalidSlotStateException('pallet_moved', __('messages.pallet_moved'));
        }
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
     * Normalize a caller-submitted expiration date to a bare date string: the
     * validation rule only requires a parseable date, so a caller-submitted
     * value carrying a time component (e.g. a raw ISO datetime) would
     * otherwise be stored verbatim — and unlike MySQL's DATE column, SQLite
     * keeps that time component intact instead of truncating it, so the
     * pallet reads back as its correct date everywhere the app formats it via
     * toDateString(), but a raw `expiration_date <= $until` comparison
     * (BuildsDashboardStats::expiringWindowCounts()) can then exclude it right at
     * the boundary.
     */
    private function normalizeExpirationDate(?string $expirationDate): ?string
    {
        return $expirationDate !== null ? Carbon::parse($expirationDate)->toDateString() : null;
    }

    /**
     * A cell on the first level (`flat_number === 1`) is opened automatically as soon
     * as a pallet lands in it — store() and transfer() both route their target state
     * through here instead of assuming Full. `$default` is what the state would be
     * without this rule (Full for store(), the carried-over source state for transfer()).
     *
     * The auto-open is still an opening, so it only happens while the first level is
     * one of the settings' allowed open levels; otherwise the pallet lands in `$default`
     * like on any other level (and, being Full, cannot be opened there later either).
     */
    private function targetStateForCell(Cell $cell, CellState $default): CellState
    {
        return $cell->flat_number === 1 && Setting::allowsOpeningAtLevel(1) ? CellState::Opened : $default;
    }

    private function assertOpeningAllowedAtLevel(Cell $cell): void
    {
        if (! Setting::allowsOpeningAtLevel($cell->flat_number)) {
            throw new InvalidSlotStateException('open_level_not_allowed', __('messages.open_level_not_allowed'));
        }
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
     * Store a new pallet into an Empty cell, transitioning it to Full — or straight to
     * Opened when the cell is on the first level (see targetStateForCell()).
     */
    public function store(int $cellId, int $productId, ?string $expirationDate, int $userId, ?string $note): Pallet
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
                'expiration_date' => $this->normalizeExpirationDate($expirationDate),
                'remaining_boxes' => $product->boxes_count,
            ]);

            $toState = $this->targetStateForCell($cell, CellState::Full);

            $cell->update(['state' => $toState]);

            $this->logCellStatus(
                $cell,
                CellLogAction::Stored,
                CellState::Empty,
                $toState,
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
     * Open a Full pallet (Full→Opened), optionally removing boxes in the same
     * transaction. A null $boxesCount just transitions the cell to Opened and
     * leaves remaining_boxes untouched — see .ai/rules/v1.md.
     *
     * A pallet on a level outside the settings' allowed open levels is refused
     * (`open_level_not_allowed`). `$enforceAllowedLevels` is false only for
     * CellVerificationService, which replays what a worker physically found on
     * the shelf: an accepted report of an opened pallet must be able to record
     * it whatever the policy for opening new ones is.
     *
     * @return array{pallet: ?Pallet, emptied: bool}
     */
    public function open(Pallet $pallet, ?int $boxesCount, bool $confirmEmpty, int $userId, ?string $note, bool $enforceAllowedLevels = true): array
    {
        $emptied = false;

        DB::transaction(function () use ($pallet, $boxesCount, $confirmEmpty, $userId, $note, $enforceAllowedLevels, &$emptied) {
            [$cell, $lockedPallet] = $this->lockPalletInCell($pallet);

            if ($cell->state !== CellState::Full) {
                throw new InvalidSlotStateException('pallet_not_full', __('messages.pallet_not_full'));
            }

            if ($enforceAllowedLevels) {
                $this->assertOpeningAllowedAtLevel($cell);
            }

            if ($boxesCount !== null
                && $this->applyBoxesRemoval($cell, $lockedPallet, $userId, $boxesCount, $confirmEmpty, $note)) {
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
            [$cell, $lockedPallet] = $this->lockPalletInCell($pallet);

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
            [$cell, $lockedPallet] = $this->lockPalletInCell($pallet);

            $this->emptyLockedPallet($cell, $lockedPallet, $userId, $note);
        });
    }

    /**
     * Move a pallet to another Empty cell — the source cell frees to Empty, the
     * destination inherits the source's prior state, unless the destination is on
     * the first level, in which case it's opened automatically regardless of the
     * source's state (see targetStateForCell()).
     *
     * Both cells are locked in ascending id order, then the pallet (see
     * lockPalletInCell() for why cells come first): locking source-then-
     * destination would let an A→B transfer and a concurrent B→A one each hold
     * their first cell while waiting on the other's. Every write and log below
     * goes through the locked pallet, never the caller's possibly-stale copy.
     */
    public function transfer(Pallet $pallet, int $destinationCellId, int $userId, ?string $note): Pallet
    {
        DB::transaction(function () use ($pallet, $destinationCellId, $userId, $note) {
            $cellIds = array_unique([$pallet->cell_id, $destinationCellId]);
            sort($cellIds);

            $lockedCells = [];

            foreach ($cellIds as $cellId) {
                $lockedCells[$cellId] = $this->lockCellRow($cellId);
            }

            $sourceCell = $lockedCells[$pallet->cell_id];
            $lockedPallet = $this->lockPallet($pallet->id);

            $this->assertPalletStillInCell($lockedPallet, $sourceCell);

            if ($destinationCellId === $sourceCell->id) {
                throw new InvalidSlotStateException('pallet_already_at_location', __('messages.pallet_already_at_location'));
            }

            $destinationCell = $lockedCells[$destinationCellId];

            $this->assertCellAcceptsPalletActions($sourceCell);
            $this->assertCellAcceptsPalletActions($destinationCell);

            if ($destinationCell->state !== CellState::Empty) {
                throw new InvalidSlotStateException('destination_not_empty', __('messages.destination_not_empty'));
            }

            $sourceState = $sourceCell->state;
            $destinationState = $this->targetStateForCell($destinationCell, $sourceState);

            $sourceCell->update(['state' => CellState::Empty]);
            $destinationCell->update(['state' => $destinationState]);
            $lockedPallet->update(['cell_id' => $destinationCell->id]);

            $this->logCellStatus(
                $sourceCell,
                CellLogAction::TransferredOut,
                $sourceState,
                CellState::Empty,
                $lockedPallet->product_id,
                $lockedPallet->id,
                $userId,
                $note,
                relatedCellId: $destinationCell->id,
                boxesCount: $lockedPallet->remaining_boxes,
            );

            $this->logCellStatus(
                $destinationCell,
                CellLogAction::TransferredIn,
                CellState::Empty,
                $destinationState,
                $lockedPallet->product_id,
                $lockedPallet->id,
                $userId,
                $note,
                relatedCellId: $sourceCell->id,
                boxesCount: $lockedPallet->remaining_boxes,
            );
        });

        return $pallet->refresh();
    }

    /**
     * Edit an already-stored pallet's product, expiration date, and/or remaining
     * boxes, without moving it or changing its cell's state. It locks the
     * cell/pallet the same way as every other action and updates through the
     * model, so PalletObserver's wasChanged(['expiration_date', 'product_id',
     * ..., 'remaining_boxes']) guard still catches the change and flushes the
     * dashboard stats cache.
     *
     * A product change is logged as a `product_changed` CellStatusLog row
     * carrying the new product, so the cell's history shows when (and by whom)
     * the pallet stopped being what it was stored as — the previous product is
     * on the pallet's earlier log rows. An expiration date or a positive
     * remaining_boxes correction on its own still writes no log row. The
     * product is looked up here too, like store() does, rather than trusting
     * the caller's validation to have run against the same database state.
     *
     * $remainingBoxes sets the column directly (an admin correction), unlike
     * open()/removeBoxes()'s boxes_count, which is an amount to subtract.
     * Editing it to 0 goes through the same confirm_empty/emptyLockedPallet
     * flow those use — it deletes the pallet, frees the cell, and logs an
     * Emptied transition — rather than storing a 0-box pallet row, which no
     * other path in this service produces.
     */
    public function update(Pallet $pallet, int $productId, ?string $expirationDate, int $remainingBoxes, int $userId, bool $confirmEmpty, ?string $note = null): void
    {
        DB::transaction(function () use ($pallet, $productId, $expirationDate, $remainingBoxes, $userId, $confirmEmpty, $note) {
            [$cell, $lockedPallet] = $this->lockPalletInCell($pallet);

            if ($remainingBoxes === 0) {
                if (! $confirmEmpty) {
                    throw new InvalidSlotStateException('insufficient_boxes_remaining', __('messages.insufficient_boxes_remaining'));
                }

                $this->emptyLockedPallet($cell, $lockedPallet, $userId, $note);

                return;
            }

            $product = Product::query()->select(['id'])->findOrFail($productId);

            $lockedPallet->update([
                'product_id' => $product->id,
                'expiration_date' => $this->normalizeExpirationDate($expirationDate),
                'remaining_boxes' => $remainingBoxes,
            ]);

            if ($lockedPallet->wasChanged('product_id')) {
                $this->logCellStatus(
                    $cell,
                    CellLogAction::ProductChanged,
                    $cell->state,
                    $cell->state,
                    $lockedPallet->product_id,
                    $lockedPallet->id,
                    $userId,
                    $note,
                    boxesCount: $lockedPallet->remaining_boxes,
                );
            }
        });
    }
}

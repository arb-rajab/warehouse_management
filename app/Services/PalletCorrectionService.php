<?php

namespace App\Services;

use App\Enums\CellLogAction;
use App\Exceptions\BulkPalletChangeRejectedException;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Row;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The admin's bulk record corrections of existing pallets: one shared note
 * (changeNote()) and a product change (changeProduct()), for 1 to 500 pallets
 * of any product, warehouse and placement at once. Neither moves a pallet or
 * changes its cell, state, boxes or expiry.
 *
 * Each call is all or nothing in one transaction. The selected pallets are
 * locked in ascending id order, and every check runs under those locks; when
 * any pallet fails, BulkPalletChangeRejectedException names each failing one
 * and nothing is written. Only pallets are locked, never cells: every other
 * pallet action locks a cell first and then at most one pallet, so a request
 * holding pallet locks never waits on a cell and cannot close a cycle, and a
 * cell's state cannot change while its pallet is locked (every state change
 * runs under the pallet's lock), so the ETag read here is the current one.
 *
 * The writes are one UPDATE of the pallets and one multi-row INSERT of cell
 * log entries sharing a `batch_id`. Inserted in one statement, the entries
 * skip CellStatusLogObserver: a desk correction is not shelf work and is never
 * flagged (the observer leaves batch entries out of its rapid-actions window
 * too). A placed pallet's entry names its cell, with from and to state both
 * the cell's state; an unplaced pallet's entry has no cell and no states.
 */
class PalletCorrectionService
{
    /**
     * Give every selected pallet the same note (null clears it), logging one
     * `note_changed` entry per pallet whose note changes, with its old and new
     * note. A pallet that already carries the note is left as it is.
     *
     * @param  list<array{id: int, etag: string|null}>  $selection
     * @return list<int> the selected pallet ids, in the order sent
     *
     * @throws BulkPalletChangeRejectedException
     */
    public function changeNote(array $selection, ?string $note, int $userId): array
    {
        DB::transaction(function () use ($selection, $note, $userId): void {
            $pallets = $this->lockAndCheck($selection);

            $changed = $pallets->filter(fn (Pallet $pallet): bool => $pallet->note !== $note);

            if ($changed->isEmpty()) {
                return;
            }

            Pallet::query()->whereKey($changed->modelKeys())->update(['note' => $note]);

            $this->log($changed, CellLogAction::NoteChanged, $userId, fn (Pallet $pallet): array => [
                'product_id' => $pallet->product_id,
                'old_note' => $pallet->note,
                'new_note' => $note,
            ]);
        });

        return array_column($selection, 'id');
    }

    /**
     * Move every selected pallet to another product, keeping its location,
     * state, boxes and expiry (the boxes stay what the pallet holds, whatever
     * the new product's usual count). The pallet's note is cleared unless
     * `$keepNote`. Refuses a pallet that already holds the product
     * (`pallet_same_product`, so the history never shows an empty change) and
     * one whose cell's row is being verified (`cell_in_active_round`: a round
     * compares the shelf against the record). Logs one `product_changed`
     * entry per pallet with the old and new product, the reason and the old
     * and new note.
     *
     * @param  list<array{id: int, etag: string|null}>  $selection
     * @return list<int> the selected pallet ids, in the order sent
     *
     * @throws BulkPalletChangeRejectedException
     */
    public function changeProduct(array $selection, int $productId, string $reason, bool $keepNote, int $userId): array
    {
        DB::transaction(function () use ($selection, $productId, $reason, $keepNote, $userId): void {
            $pallets = $this->lockAndCheck($selection, fn (Pallet $pallet, bool $inActiveRound): ?string => match (true) {
                $pallet->product_id === $productId => 'pallet_same_product',
                $inActiveRound => 'cell_in_active_round',
                default => null,
            });

            Pallet::query()->whereKey($pallets->modelKeys())->update([
                'product_id' => $productId,
                ...($keepNote ? [] : ['note' => null]),
            ]);

            $this->log($pallets, CellLogAction::ProductChanged, $userId, fn (Pallet $pallet): array => [
                'product_id' => $productId,
                'old_product_id' => $pallet->product_id,
                'reason' => $reason,
                'old_note' => $pallet->note,
                'new_note' => $keepNote ? $pallet->note : null,
            ]);

            // The update skips PalletObserver, which flushes on a product change.
            DB::afterCommit(fn () => DashboardStatsCache::flush());
        });

        return array_column($selection, 'id');
    }

    /**
     * Lock the selected pallets (ascending id), attach their cells, and check
     * each one in turn: gone (`pallet_not_found`), then a sent etag that no
     * longer matches (`pallet_changed`), then `$check`, which also learns
     * whether the pallet's cell is in a row an unfinished verification round
     * claims (always false for an unplaced pallet). The first failure of a
     * pallet is its code; any failure rejects the whole request.
     *
     * @param  list<array{id: int, etag: string|null}>  $selection
     * @param  (Closure(Pallet, bool): ?string)|null  $check
     * @return Collection<int, Pallet> the locked pallets, keyed by id
     *
     * @throws BulkPalletChangeRejectedException
     */
    private function lockAndCheck(array $selection, ?Closure $check = null): Collection
    {
        $pallets = Pallet::query()
            ->whereKey(array_column($selection, 'id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $cells = Cell::query()
            ->select(['id', 'row_id', 'state'])
            ->whereKey($pallets->pluck('cell_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($pallets as $pallet) {
            $pallet->setRelation('cell', $pallet->cell_id === null ? null : $cells->get($pallet->cell_id));
        }

        $rowsUnderVerification = $check === null ? [] : Row::query()
            ->whereKey($cells->pluck('row_id')->unique()->values()->all())
            ->underActiveVerification()
            ->pluck('id')
            ->all();

        $failed = [];

        foreach ($selection as ['id' => $id, 'etag' => $etag]) {
            $pallet = $pallets->get($id);

            $errorCode = match (true) {
                $pallet === null => 'pallet_not_found',
                $etag !== null && $pallet->etag() !== $etag => 'pallet_changed',
                $check === null => null,
                default => $check($pallet, $pallet->cell !== null && in_array($pallet->cell->row_id, $rowsUnderVerification, true)),
            };

            if ($errorCode !== null) {
                $failed[] = ['id' => $id, 'error_code' => $errorCode];
            }
        }

        if ($failed !== []) {
            throw new BulkPalletChangeRejectedException($failed);
        }

        return $pallets;
    }

    /**
     * Insert one cell log entry per pallet, all sharing a new `batch_id`, in
     * one statement. `$fields` adds each entry's action-specific columns.
     *
     * @param  Collection<int, Pallet>  $pallets  locked, with their cells attached
     * @param  Closure(Pallet): array<string, mixed>  $fields
     */
    private function log(Collection $pallets, CellLogAction $action, int $userId, Closure $fields): void
    {
        $batchId = (string) Str::uuid();
        $now = now();

        CellStatusLog::query()->insert($pallets->map(fn (Pallet $pallet): array => [
            'cell_id' => $pallet->cell_id,
            'related_cell_id' => null,
            'action' => $action->value,
            'from_state' => $pallet->cell?->state->value,
            'to_state' => $pallet->cell?->state->value,
            'product_id' => null,
            'old_product_id' => null,
            'pallet_id' => $pallet->id,
            'boxes_count' => $pallet->remaining_boxes,
            'user_id' => $userId,
            'note' => null,
            'batch_id' => $batchId,
            'reason' => null,
            'old_note' => null,
            'new_note' => null,
            ...$fields($pallet),
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }
}

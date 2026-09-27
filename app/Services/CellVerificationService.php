<?php

namespace App\Services;

use App\Enums\CellState;
use App\Exceptions\CellOutsideRoundRowsException;
use App\Exceptions\CellVerificationReportNotAcceptableException;
use App\Exceptions\InvalidSlotStateException;
use App\Exceptions\OverlappingVerificationRoundException;
use App\Exceptions\VerificationRoundCompletedException;
use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Row;
use Illuminate\Support\Facades\DB;

/**
 * Starts, resumes, completes, and reports against verification rounds —
 * shared by Api\V1\CellVerificationRoundController and
 * Api\V1\CellVerificationReportController, mirroring the
 * controller/service split used by PalletActionService.
 *
 * Ownership of the round (may this user act on it at all) is the caller's
 * responsibility — enforced via CellVerificationRoundPolicy before reaching
 * here, not re-checked in this class. This service only owns the business
 * logic once that's settled.
 */
class CellVerificationService
{
    public function __construct(private readonly PalletActionService $palletActions) {}

    /**
     * Start a round over the given rows, claiming them exclusively until it is
     * completed, so two rounds may run at once as long as they share no row.
     * A null `$rowIds` covers the whole warehouse — the round then claims every
     * row, which blocks pallet actions everywhere and leaves no row for a second
     * round to take.
     *
     * Coverage is resolved to actual rows once, here, rather than left as a
     * standing "everything": a row added later belongs to the warehouse the
     * worker was never sent to walk, and silently extending a running round's
     * freeze onto it would be surprising.
     *
     * The `rows` records themselves are locked for the duration of the
     * transaction rather than the matching claims: two workers starting rounds
     * over the same row at the same moment need something already-existing to
     * queue behind, and a locking read over the zero claims that exist before
     * either commits would lock nothing. Whoever takes the row locks first
     * commits their claim; the other's check then reads it and is refused.
     *
     * @param  array<int, int>|null  $rowIds
     *
     * @throws OverlappingVerificationRoundException
     */
    public function startRound(int $userId, ?array $rowIds): CellVerificationRound
    {
        return DB::transaction(function () use ($userId, $rowIds) {
            $scope = Row::query();

            if ($rowIds !== null) {
                $scope->whereIn('id', $rowIds);
            }

            // This read both takes the row locks and resolves what "the whole
            // warehouse" means right now. toBase() before map(), with an
            // explicit closure return type, keeps the result a plain array
            // without relying on how Larastan infers a pluck()'s column type —
            // same reasoning as Product::optionLabels().
            $scopedIds = $scope
                ->lockForUpdate()
                ->get(['id'])
                ->toBase()
                ->map(fn (Row $row): int => $row->id)
                ->all();

            $conflictingLetters = Row::query()
                ->whereIn('id', $scopedIds)
                ->underActiveVerification()
                ->orderBy('letter')
                ->get(['letter'])
                ->toBase()
                ->map(fn (Row $row): string => $row->letter)
                ->all();

            if ($conflictingLetters !== []) {
                throw new OverlappingVerificationRoundException($conflictingLetters);
            }

            $round = CellVerificationRound::create(['user_id' => $userId]);

            $round->rows()->attach($scopedIds);

            return $round;
        });
    }

    /**
     * Mark a round completed. Idempotent: completing an already-completed
     * round is a no-op that returns the round unchanged, rather than an error.
     */
    public function completeRound(CellVerificationRound $round): CellVerificationRound
    {
        if (! $round->isCompleted()) {
            $round->update(['completed_at' => now()]);
        }

        return $round;
    }

    /**
     * Report one cell's verification outcome against an existing round. The
     * expected pallet snapshot is always derived server-side from the cell's
     * current pallet — the client only supplies what it observed.
     *
     * The cell must sit in one of the rows the round claims: only those rows
     * are frozen against pallet actions, so a report from outside them could
     * be read against a pallet that is moving underneath it.
     *
     * @param  array{is_correct: bool, reported_cell_state?: string|null, reported_product_id?: int|null, reported_boxes_count?: int|null, reported_expiration_date?: string|null, note?: string|null}  $reported
     *
     * @throws VerificationRoundCompletedException
     * @throws CellOutsideRoundRowsException
     */
    public function report(CellVerificationRound $round, int $cellId, int $userId, array $reported): CellVerificationReport
    {
        if ($round->isCompleted()) {
            throw new VerificationRoundCompletedException;
        }

        return DB::transaction(function () use ($round, $cellId, $userId, $reported) {
            $cell = Cell::query()->with('pallet')->findOrFail($cellId);

            if (! $round->coversRow($cell->row_id)) {
                throw new CellOutsideRoundRowsException;
            }

            $pallet = $cell->pallet;

            return CellVerificationReport::create([
                'cell_verification_round_id' => $round->id,
                'cell_id' => $cell->id,
                'user_id' => $userId,
                'is_correct' => $reported['is_correct'],
                'expected_cell_state' => $cell->state,
                'expected_product_id' => $pallet?->product_id,
                'expected_boxes_count' => $pallet?->remaining_boxes,
                'expected_expiration_date' => $pallet?->expiration_date,
                'reported_cell_state' => $reported['reported_cell_state'] ?? null,
                'reported_product_id' => $reported['reported_product_id'] ?? null,
                'reported_boxes_count' => $reported['reported_boxes_count'] ?? null,
                'reported_expiration_date' => $reported['reported_expiration_date'] ?? null,
                'note' => $reported['note'] ?? null,
            ]);
        });
    }

    /**
     * Accept an incorrect report: bring the cell's stored pallet in line with
     * what the worker reported seeing, then stamp the report as accepted. The
     * report's own snapshots and `is_correct` are left untouched, so the
     * discrepancy stays on record alongside when (and by whom) it was resolved.
     *
     * The cell must still hold exactly what the report expected — anything
     * else means it changed after the report, and applying the old reported
     * state would overwrite that newer change. Every pallet change goes
     * through PalletActionService, so its cell locking, active-round and
     * inactive-cell guards, and CellStatusLog audit rows all still apply.
     *
     * @throws CellVerificationReportNotAcceptableException
     * @throws InvalidSlotStateException
     */
    public function acceptReport(CellVerificationReport $report, int $userId): CellVerificationReport
    {
        return DB::transaction(function () use ($report, $userId) {
            $lockedReport = CellVerificationReport::query()->lockForUpdate()->findOrFail($report->id);

            if ($lockedReport->is_correct) {
                throw new CellVerificationReportNotAcceptableException('verification_report_already_correct', __('messages.verification_report_already_correct'));
            }

            if ($lockedReport->accepted_at !== null) {
                throw new CellVerificationReportNotAcceptableException('verification_report_already_accepted', __('messages.verification_report_already_accepted'));
            }

            $reportedState = $lockedReport->reported_cell_state;

            // A deleted product nulls reported_product_id, leaving nothing to store.
            if ($reportedState === null || ($reportedState !== CellState::Empty && $lockedReport->reported_product_id === null)) {
                throw new CellVerificationReportNotAcceptableException('verification_report_incomplete', __('messages.verification_report_incomplete'));
            }

            $cell = $this->palletActions->lockCell($lockedReport->cell_id);
            $pallet = Pallet::query()->where('cell_id', $cell->id)->first();

            if (! $this->cellMatchesExpectedSnapshot($cell->state, $pallet, $lockedReport)) {
                throw new CellVerificationReportNotAcceptableException('cell_changed_since_verification_report', __('messages.cell_changed_since_verification_report'));
            }

            $this->applyReportedState($cell->id, $cell->state, $pallet, $lockedReport, $reportedState, $userId);

            $lockedReport->update([
                'accepted_at' => now(),
                'accepted_by' => $userId,
            ]);

            return $lockedReport;
        });
    }

    private function cellMatchesExpectedSnapshot(CellState $cellState, ?Pallet $pallet, CellVerificationReport $report): bool
    {
        return $cellState === $report->expected_cell_state
            && $pallet?->product_id === $report->expected_product_id
            && $pallet?->remaining_boxes === $report->expected_boxes_count
            && $pallet?->expiration_date?->toDateString() === $report->expected_expiration_date?->toDateString();
    }

    /**
     * An opened pallet can't be closed again through PalletActionService, so a
     * report of a full pallet where the system has an opened one replaces it:
     * the old pallet is emptied and the reported one stored fresh.
     */
    private function applyReportedState(int $cellId, CellState $cellState, ?Pallet $pallet, CellVerificationReport $report, CellState $reportedState, int $userId): void
    {
        $note = __('messages.verification_report_accepted_note', ['id' => $report->id]);

        if ($pallet !== null && ($reportedState === CellState::Empty || ($reportedState === CellState::Full && $cellState === CellState::Opened))) {
            $this->palletActions->emptyPallet($pallet, $userId, $note);
            $pallet = null;
        }

        if ($reportedState === CellState::Empty) {
            return;
        }

        /** @var int $productId */
        $productId = $report->reported_product_id;
        $expirationDate = $report->reported_expiration_date?->toDateString();

        $pallet ??= $this->palletActions->store($cellId, $productId, $expirationDate, $userId, $note);

        if ($reportedState === CellState::Opened && $pallet->state === CellState::Full) {
            $pallet = $this->palletActions->open($pallet, null, false, $userId, $note)['pallet'] ?? $pallet;
        }

        $this->palletActions->update(
            $pallet,
            $productId,
            $expirationDate,
            $report->reported_boxes_count ?? $pallet->remaining_boxes,
            $userId,
            false,
            $note,
        );
    }
}

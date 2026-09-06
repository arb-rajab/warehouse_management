<?php

namespace App\Services;

use App\Exceptions\VerificationRoundCompletedException;
use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Starts, resumes, completes, and reports against verification rounds —
 * shared by Api\V1\CellVerificationRoundController and
 * Api\V1\CellVerificationReportController, mirroring the
 * controller/service split used by PalletActionService.
 */
class CellVerificationService
{
    public function startRound(int $userId): CellVerificationRound
    {
        return CellVerificationRound::create(['user_id' => $userId]);
    }

    /**
     * Mark a round completed. Idempotent: completing an already-completed
     * round is a no-op that returns the round unchanged, rather than an error.
     */
    public function completeRound(CellVerificationRound $round, int $userId): CellVerificationRound
    {
        $this->authorizeRound($round, $userId);

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
     * @param  array{is_correct: bool, reported_cell_state?: string|null, reported_product_id?: int|null, reported_boxes_count?: int|null, reported_expiration_date?: string|null, note?: string|null}  $reported
     */
    public function report(CellVerificationRound $round, int $cellId, int $userId, array $reported): CellVerificationReport
    {
        $this->authorizeRound($round, $userId);

        if ($round->isCompleted()) {
            throw new VerificationRoundCompletedException;
        }

        return DB::transaction(function () use ($round, $cellId, $userId, $reported) {
            $cell = Cell::query()->with('pallet')->findOrFail($cellId);
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
     * A user may only view/resume/complete/report against their own rounds.
     */
    public function authorizeRound(CellVerificationRound $round, int $userId): void
    {
        if ($round->user_id !== $userId) {
            throw new HttpException(403, 'This verification round belongs to another user.');
        }
    }
}

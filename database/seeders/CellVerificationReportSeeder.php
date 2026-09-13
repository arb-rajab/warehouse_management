<?php

namespace Database\Seeders;

use App\Models\Cell;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class CellVerificationReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rounds = CellVerificationRound::with('rows:id,letter')->get();
        $cells = Cell::with('pallet')->get();

        if ($rounds->isEmpty() || $cells->isEmpty()) {
            return;
        }

        foreach ($rounds as $round) {
            // A report only ever comes from a row the round covers — the API
            // refuses anything else (CellVerificationService::report()), so the
            // seeded walk stays inside the same scope.
            $roundCells = $cells->whereIn('row_id', $round->rows->modelKeys());

            if ($roundCells->isEmpty()) {
                continue;
            }

            $reportCount = fake()->numberBetween(3, 8);
            $walkEndedAt = $round->completed_at ?? $round->created_at->addMinutes(45);

            foreach (range(1, $reportCount) as $i) {
                $reportedAt = $round->created_at->addSeconds((int) (
                    $round->created_at->diffInSeconds($walkEndedAt) * ($i / ($reportCount + 1))
                ));

                $this->report($round, $roundCells->random(), $reportedAt);
            }
        }
    }

    /**
     * Reports lean correct (75%) since that's the common case on a real
     * walk — a wrong one uses CellVerificationReportFactory::incorrect()'s
     * own randomized mismatch, while a correct one mirrors the cell's actual
     * current pallet so the admin view reads as a real, consistent snapshot.
     */
    private function report(CellVerificationRound $round, Cell $cell, CarbonInterface $reportedAt): void
    {
        if (fake()->boolean(75)) {
            $pallet = $cell->pallet;

            $report = CellVerificationReport::factory()->create([
                'cell_verification_round_id' => $round->id,
                'cell_id' => $cell->id,
                'user_id' => $round->user_id,
                'is_correct' => true,
                'expected_cell_state' => $cell->state,
                'expected_product_id' => $pallet?->product_id,
                'expected_boxes_count' => $pallet?->remaining_boxes,
                'expected_expiration_date' => $pallet?->expiration_date,
            ]);
        } else {
            $report = CellVerificationReport::factory()->incorrect()->create([
                'cell_verification_round_id' => $round->id,
                'cell_id' => $cell->id,
                'user_id' => $round->user_id,
            ]);
        }

        $report->forceFill(['created_at' => $reportedAt])->save();
    }
}

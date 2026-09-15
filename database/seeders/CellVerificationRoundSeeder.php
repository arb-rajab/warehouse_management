<?php

namespace Database\Seeders;

use App\Models\Cell;
use App\Models\CellVerificationRound;
use App\Models\Row;
use App\Models\User;
use Illuminate\Database\Seeder;

class CellVerificationRoundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Cell::query()->doesntExist()) {
            return;
        }

        $workers = User::query()->doesntHave('roles')->get();

        if ($workers->count() < 4) {
            $workers = $workers->merge(
                User::factory()->mobileUser()->count(4 - $workers->count())->create()
            );
        }

        $rowIds = Row::query()
            ->get(['id'])
            ->toBase()
            ->map(fn (Row $row): int => $row->id)
            ->all();

        foreach (range(1, 15) as $i) {
            // Completed rounds may overlap each other freely — a row being
            // re-counted next week is the normal case.
            $shuffled = $rowIds;
            shuffle($shuffled);

            $this->seedRound($workers->random(), completed: true, rowIds: array_slice($shuffled, 0, 2));
        }

        // Unfinished rounds hold their rows exclusively, so seeded data has to
        // satisfy the same rule startRound() enforces: each one takes rows no
        // other unfinished round already claims, and they stay narrow so most
        // of the warehouse is still actionable in a seeded environment.
        $unclaimed = $rowIds;
        shuffle($unclaimed);

        foreach (range(1, 4) as $i) {
            $claim = array_splice($unclaimed, 0, 2);

            if ($claim === []) {
                break;
            }

            $this->seedRound($workers->random(), completed: false, rowIds: $claim);
        }
    }

    /**
     * A round backdated to look like it happened at some point over the last
     * month — completed ones get a completed_at a realistic walk-length
     * (15-90 minutes) after they started — covering the given rows.
     *
     * @param  array<int, int>  $rowIds
     */
    private function seedRound(User $user, bool $completed, array $rowIds): void
    {
        $startedAt = now()
            ->subDays(fake()->numberBetween(0, 30))
            ->subMinutes(fake()->numberBetween(0, 600));

        $round = CellVerificationRound::factory()->create([
            'user_id' => $user->id,
            'completed_at' => $completed ? $startedAt->addMinutes(fake()->numberBetween(15, 90)) : null,
        ]);

        $round->rows()->attach($rowIds);

        $round->forceFill(['created_at' => $startedAt])->save();
    }
}

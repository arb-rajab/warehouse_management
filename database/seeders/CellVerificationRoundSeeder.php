<?php

namespace Database\Seeders;

use App\Models\Cell;
use App\Models\CellVerificationRound;
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

        $workers = User::factory()->mobileUser()->count(4)->create();

        foreach (range(1, 15) as $i) {
            $this->seedRound($workers->random(), completed: true);
        }

        foreach (range(1, 4) as $i) {
            $this->seedRound($workers->random(), completed: false);
        }
    }

    /**
     * A round backdated to look like it happened at some point over the last
     * month — completed ones get a completed_at a realistic walk-length
     * (15-90 minutes) after they started.
     */
    private function seedRound(User $user, bool $completed): void
    {
        $startedAt = now()
            ->subDays(fake()->numberBetween(0, 30))
            ->subMinutes(fake()->numberBetween(0, 600));

        $round = CellVerificationRound::factory()->create([
            'user_id' => $user->id,
            'completed_at' => $completed ? $startedAt->addMinutes(fake()->numberBetween(15, 90)) : null,
        ]);

        $round->forceFill(['created_at' => $startedAt])->save();
    }
}

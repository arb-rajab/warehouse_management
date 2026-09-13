<?php

namespace Database\Factories;

use App\Models\CellVerificationRound;
use App\Models\Row;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellVerificationRound>
 */
class CellVerificationRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => now(),
        ]);
    }

    /**
     * Claim the given rows for the round. Every round created through the API
     * covers rows, so a test that needs a realistic round — one that blocks
     * pallet actions, or that a report can be filed against — builds it with
     * this rather than attaching the pivot by hand at the call site.
     */
    public function covering(Row ...$rows): static
    {
        return $this->afterCreating(function (CellVerificationRound $round) use ($rows): void {
            $round->rows()->attach(array_map(fn (Row $row): int => $row->id, $rows));
        });
    }
}

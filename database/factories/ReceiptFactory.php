<?php

namespace Database\Factories;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * Define the model's default state. A receipt is a draft in the "Default"
     * warehouse unless the caller picks one, e.g.
     * `Receipt::factory()->for($warehouse)`, as {@see RowFactory} does for rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => fn (): int => Warehouse::firstOrCreateDefault()->id,
            'note' => fake()->optional()->sentence(),
            'status' => ReceiptStatus::Draft,
            'created_by' => User::factory(),
            'finalized_by' => null,
            'finalized_at' => null,
        ];
    }

    /**
     * A finalized receipt, by a (new) user now.
     */
    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'status' => ReceiptStatus::Finalized,
            'finalized_by' => User::factory(),
            'finalized_at' => now(),
        ]);
    }
}

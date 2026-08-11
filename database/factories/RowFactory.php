<?php

namespace Database\Factories;

use App\Models\Row;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Row>
 */
class RowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'letter' => Str::upper(fake()->unique()->lexify(str_repeat('?', fake()->numberBetween(1, 2)))),
            'cells_count' => fake()->numberBetween(5, 20),
            'flats_count' => fake()->numberBetween(1, 5),
        ];
    }
}

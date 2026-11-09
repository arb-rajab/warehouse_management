<?php

namespace Database\Factories;

use App\Models\Row;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Row>
 */
class RowFactory extends Factory
{
    /**
     * Define the model's default state. A row is put in the "Default"
     * warehouse (the one the warehouses migration creates) unless the caller
     * picks one, e.g. `Row::factory()->for($warehouse)`, so rows a test
     * creates share a warehouse as they did before warehouses existed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => fn (): int => Warehouse::firstOrCreateDefault()->id,
            'letter' => Str::upper(fake()->unique()->lexify(str_repeat('?', fake()->numberBetween(1, 2)))),
            'cells_count' => fake()->numberBetween(5, 20),
            'flats_count' => fake()->numberBetween(1, 5),
        ];
    }
}

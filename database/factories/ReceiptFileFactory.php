<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReceiptFile>
 */
class ReceiptFileFactory extends Factory
{
    /**
     * Define the model's default state. The row only records a path; no file
     * is written, so a test that needs the bytes stores them itself.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'path' => 'receipt-bills/'.Str::uuid().'.jpg',
            'original_name' => 'bill-'.fake()->unique()->numerify('####').'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
        ];
    }

    /**
     * A PDF bill instead of a photo.
     */
    public function pdf(): static
    {
        return $this->state(fn (): array => [
            'path' => 'receipt-bills/'.Str::uuid().'.pdf',
            'original_name' => 'bill-'.fake()->unique()->numerify('####').'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}

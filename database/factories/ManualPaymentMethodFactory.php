<?php

namespace Database\Factories;

use App\Models\ManualPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualPaymentMethod>
 */
class ManualPaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'custom_payment',
            'heading' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'bank_info' => null,
            'photo' => null,
        ];
    }
}

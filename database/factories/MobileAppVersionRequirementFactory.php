<?php

namespace Database\Factories;

use App\Models\MobileAppVersionRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileAppVersionRequirement>
 */
class MobileAppVersionRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'minimum_version' => '1.0.0',
        ];
    }
}

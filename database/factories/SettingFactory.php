<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qr_code_width' => Setting::DEFAULT_QR_CODE_WIDTH,
            'qr_code_height' => Setting::DEFAULT_QR_CODE_HEIGHT,
        ];
    }
}

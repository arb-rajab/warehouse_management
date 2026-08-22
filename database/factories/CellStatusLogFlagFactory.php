<?php

namespace Database\Factories;

use App\Enums\CellLogFlagReason;
use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellStatusLogFlag>
 */
class CellStatusLogFlagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cell_status_log_id' => CellStatusLog::factory(),
            'reason' => CellLogFlagReason::RapidActions,
        ];
    }
}

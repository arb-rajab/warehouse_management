<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * Define the model's default state: an SMTP save by a user who need not
     * exist (the table has no foreign key).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(1, 100000),
            'user_name' => fake()->name(),
            'user_email' => fake()->safeEmail(),
            'action' => AdminAuditLog::SMTP_SETTINGS_UPDATED,
            'changed_fields' => ['host' => 'changed'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
        ];
    }

    /** A refused current password: no field changed. */
    public function passwordFailed(): static
    {
        return $this->state(['action' => AdminAuditLog::SMTP_SETTINGS_PASSWORD_FAILED, 'changed_fields' => []]);
    }
}

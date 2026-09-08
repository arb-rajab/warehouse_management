<?php

namespace Database\Factories;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to the external-link shape so a factory-built product resolves
     * to a real image URL without any environment configuration — the
     * `file_name` path needs `store.asset_base_url`, which is unset in tests.
     * Use `stored()` to exercise that path.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_name' => null,
            'external_link' => fake()->imageUrl(),
        ];
    }

    /**
     * An upload held in the store app's own file storage, resolved through
     * `store.asset_base_url` rather than carrying its own absolute URL.
     */
    public function stored(?string $fileName = null): static
    {
        return $this->state(fn (): array => [
            'file_name' => $fileName ?? 'uploads/all/'.fake()->uuid().'.png',
            'external_link' => null,
        ]);
    }
}

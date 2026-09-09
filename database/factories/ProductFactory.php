<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->word().' '.fake()->word()),
            'thumbnail_img' => Upload::factory(),
        ];
    }

    /**
     * Configure the model factory.
     *
     * `boxes_count` lives in this app's own `wms_product_settings`, not on the
     * store-owned `products` table, so it cannot be passed to `create()` as an
     * attribute — every product gets a settings row here, and `boxesCount()`
     * overrides the count.
     */
    public function configure(): static
    {
        return parent::configure()->afterCreating(
            fn (Product $product) => $product->setting()->create([
                'boxes_count' => fake()->numberBetween(1, 50),
            ])
        );
    }

    /**
     * A product whose full pallet holds exactly this many boxes.
     */
    public function boxesCount(int $count): static
    {
        return $this->afterCreating(
            fn (Product $product) => $product->setting()->updateOrCreate([], ['boxes_count' => $count])
        );
    }

    /**
     * A product resolving to exactly this image URL, or to none.
     *
     * Backed by an upload's `external_link`, which `Upload::url()` returns
     * verbatim — the `file_name` path would depend on `store.asset_base_url`.
     */
    public function imageUrl(?string $url): static
    {
        return $this->state(fn (): array => [
            'thumbnail_img' => $url === null
                ? null
                : Upload::factory()->state(['external_link' => $url, 'file_name' => null]),
        ]);
    }

    /**
     * A product the store has added but this app has never configured — no
     * `wms_product_settings` row, so `boxes_count` falls back to its default.
     */
    public function unconfigured(): static
    {
        return $this->afterCreating(fn (Product $product) => $product->setting()->delete());
    }
}

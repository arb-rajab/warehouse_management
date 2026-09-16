<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the Otajer store's product feed and upserts a bounded subset of
 * columns (`name`, `ar_name`, `published`) into this app's `products` table.
 *
 * This app owns that table and this command is its only writer, so the feed is
 * the single source of truth for those three columns: the upsert is
 * unconditional and simply replaces whatever is there. See the "Product sync"
 * section of .ai/rules/shared-database.md before adding a second write path.
 */
#[Signature('products:sync')]
#[Description("Sync product name, Arabic name, and active status from the store's API into the products table")]
class SyncProductsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = config('store.products_sync_url');

        if (blank($url)) {
            $this->components->error('store.products_sync_url is not configured (set STORE_PRODUCTS_SYNC_URL).');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(30)->get($url);
        } catch (Throwable $e) {
            Log::warning('Product sync request failed.', ['exception' => $e->getMessage()]);
            $this->components->error("Product sync request failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful() || $response->json('Result') !== 'OK') {
            Log::warning('Product sync received an unexpected response.', [
                'status' => $response->status(),
                'result' => $response->json('Result'),
            ]);
            $this->components->error('Product sync received an unexpected response from the store API.');

            return self::FAILURE;
        }

        $rows = $this->mapProducts($response->json('Products', []));

        if ($rows === []) {
            $this->components->info('No products to sync.');

            return self::SUCCESS;
        }

        Product::query()->upsert($rows, ['id'], ['name', 'ar_name', 'published']);

        $this->components->info(count($rows).' product(s) synced.');

        return self::SUCCESS;
    }

    /**
     * Maps the feed's rows onto the columns this app writes.
     *
     * `Mat_ID` is assumed to be the store's own `products.id` — both systems
     * share the store's product identifier space. Every other store-owned
     * column in the payload (price tiers, tax, barcodes, unit/class ids, the
     * image) is deliberately left unmapped, matching this app's
     * column-minimalism convention; see .ai/rules/shared-database.md. A row
     * missing a usable `Mat_ID` or `enName` is skipped rather than written
     * with a guessed value.
     *
     * @return list<array{id: int, name: string, ar_name: string, published: int}>
     */
    private function mapProducts(mixed $products): array
    {
        if (! is_array($products)) {
            return [];
        }

        $rows = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $matId = $product['Mat_ID'] ?? null;
            $name = $product['enName'] ?? null;

            if (! is_numeric($matId) || blank($name)) {
                continue;
            }

            $rows[] = [
                'id' => (int) $matId,
                'name' => mb_substr((string) $name, 0, 200),
                'ar_name' => mb_substr((string) ($product['arName'] ?? ''), 0, 191),
                'published' => empty($product['Active']) ? 0 : 1,
            ];
        }

        return $rows;
    }
}

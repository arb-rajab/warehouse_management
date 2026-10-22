<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductColumnOwnership;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the Otajer store's product feed and upserts a bounded subset of
 * columns (`name`, `ar_name`, `published`) into this app's `products` table.
 *
 * The feed owns those columns only on feed-origin rows. A null synced_at marks
 * a store-created or unclassified row and is never overwritten, even when a
 * store insert races this command. See .ai/rules/shared-database.md.
 */
#[Signature('products:sync')]
#[Description("Sync product name, Arabic name, and active status from the store's API into the products table")]
class SyncProductsCommand extends Command
{
    /**
     * Row count per upsert statement, keeping bindings (7/row) well under
     * SQLite's SQLITE_MAX_VARIABLE_NUMBER (32766).
     */
    private const UPSERT_CHUNK_SIZE = 500;

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

        $conflicts = 0;
        foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
            $conflicts += $this->syncChunk($chunk);
        }

        $this->components->info((count($rows) - $conflicts).' product(s) synced.');
        if ($conflicts > 0) {
            $this->components->warn($conflicts.' product(s) skipped due to store-origin ID conflicts.');
        }

        return self::SUCCESS;
    }

    /**
     * Conditional assignments are essential: filtering before an unconditional
     * upsert would still overwrite a store row inserted between those queries.
     * The marker is assigned last because MySQL evaluates assignments in order.
     *
     * @param  list<array{id: int, name: string, ar_name: string, published: int}>  $chunk
     */
    private function syncChunk(array $chunk): int
    {
        $connection = DB::connection();
        $table = (new Product)->getTable();
        $mysql = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $updates = [];
        foreach ([...ProductColumnOwnership::SYNC_COLUMNS, 'synced_at'] as $column) {
            $incoming = $mysql && ! $connection->getConfig('use_upsert_alias')
                ? 'values('.$column.')'
                : ($mysql ? 'laravel_upsert_alias.' : 'excluded.').$column;
            $updates[$column] = new Expression('CASE WHEN products.synced_at IS NOT NULL THEN '.$incoming.' ELSE products.'.$column.' END');
        }
        $now = now()->toDateTimeString();
        $rows = array_map(fn (array $row): array => [...$row, 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk);

        $ids = $connection->transaction(function () use ($connection, $table, $chunk, $rows, $updates): array {
            $connection->table($table)->upsert($rows, ['id'], $updates);

            return $connection->table($table)->whereIn('id', array_column($chunk, 'id'))->whereNull('synced_at')->pluck('id')->all();
        });
        foreach ($ids as $id) {
            Log::warning('Product sync skipped a store-origin ID conflict.', ['id' => $id]);
            $this->components->warn('Product sync conflict: id '.$id.' is store-origin or unclassified.');
        }

        return count(array_filter($chunk, fn (array $row): bool => in_array($row['id'], $ids)));
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

<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductStock;
use App\Services\ProductFieldSourceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the Otajer store's product feed and upserts a bounded subset of
 * plain scalar columns into this app's `products` table: `name`, `ar_name`,
 * `published`, `collection_id`, `serial`, `unit_price`, `unit`, `api_unitId`,
 * `api_unit_name`, `tax`, `description` and `unit_equal`. Data that lives in
 * other tables or needs computation (barcodes, variations, the image,
 * categories, brands, translations) is not synced.
 *
 * The feed owns those columns only on feed-origin rows. A null synced_at marks
 * a store-created or unclassified row and is never overwritten, even when a
 * store insert races this command. See .ai/rules/shared-database.md.
 *
 * Only the Otajer-owned columns are written (ProductFieldSourceService): an
 * admin-owned column is never updated, and a product this command creates
 * gets that column's configured insert value instead of the feed's. See
 * .ai/rules/product-field-sources.md.
 *
 * A feed value that is missing or unusable for a column in KEEP_WHEN_MISSING
 * maps to null, and the update then keeps the product's current value
 * (COALESCE). While `unit_price` is Otajer-owned the same transaction also
 * re-prices the PerBox/PerPiece `product_stocks` rows of the feed-origin
 * products it just wrote (repriceStocks()).
 */
#[Signature('products:sync')]
#[Description("Sync product name, Arabic name, active status, margin collection, serial, price, unit, tax, description and unit conversion from the store's API into the products table")]
class SyncProductsCommand extends Command
{
    /**
     * Row count per upsert statement, keeping bindings (16/row: the 12
     * mapped columns, `id`, `synced_at`, `created_at` and `updated_at`; 8,000
     * per statement) well under SQLite's SQLITE_MAX_VARIABLE_NUMBER (32766).
     * The count stays modest because `description` is a LONGTEXT.
     */
    private const UPSERT_CHUNK_SIZE = 500;

    /**
     * The syncable columns mapProducts() fills. Only these may reach the
     * upsert's raw update expressions: a field registered in
     * config/product_field_sources.php needs a mapping here before the sync
     * can write it.
     *
     * @var list<literal-string>
     */
    private const array MAPPED_COLUMNS = [
        'name', 'ar_name', 'published', 'collection_id',
        'serial', 'unit_price', 'unit', 'api_unitId', 'api_unit_name', 'tax', 'description', 'unit_equal',
    ];

    /**
     * The mapped columns whose missing or unusable feed value maps to null and
     * leaves the product's current value in place. `name` (a row without one
     * is skipped), `ar_name` (blank clears it) and `published` (absent means
     * inactive) always carry a value.
     *
     * @var list<literal-string>
     */
    private const array KEEP_WHEN_MISSING = [
        'collection_id', 'serial', 'unit_price', 'unit', 'api_unitId', 'api_unit_name', 'tax', 'description', 'unit_equal',
    ];

    /**
     * The KEEP_WHEN_MISSING columns that are NOT NULL in `products`. MySQL
     * rejects an explicit NULL in the insert list even for a row that already
     * exists, so syncChunk() binds the current value (or, for a new product,
     * the registry's insert value) instead of null.
     *
     * @var list<literal-string>
     */
    private const array NOT_NULL_KEEP = ['unit_price'];

    /**
     * Execute the console command.
     */
    public function handle(ProductFieldSourceService $fieldSources): int
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

        $rows = $this->mapProducts($response->json('Products', []), $this->collectionIdsBySerial());

        if ($rows === []) {
            $this->components->info('No products to sync.');

            return self::SUCCESS;
        }

        // Read once per run, so every chunk applies the same ownership.
        $adminOwned = array_values(array_intersect(self::MAPPED_COLUMNS, $fieldSources->adminOwnedFields()));
        $otajerOwned = array_values(array_intersect(self::MAPPED_COLUMNS, $fieldSources->otajerOwnedFields()));
        $insertValues = array_combine($adminOwned, array_map($fieldSources->insertValueFor(...), $adminOwned));
        $notNullFallbacks = array_combine(self::NOT_NULL_KEEP, array_map($fieldSources->insertValueFor(...), self::NOT_NULL_KEEP));
        if ($adminOwned !== []) {
            $this->components->info('Admin-owned field(s) left to the admin panel: '.implode(', ', $adminOwned).'.');
        }

        $conflicts = 0;
        foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
            $conflicts += $this->syncChunk($chunk, $otajerOwned, $insertValues, $notNullFallbacks);
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
     * An admin-owned column is left out of the update list, so an existing
     * row keeps its value, and takes `$insertValues` on insert. A
     * KEEP_WHEN_MISSING column keeps its current value when the feed sent
     * none (null).
     *
     * @param  list<array<string, mixed>>  $chunk  rows from mapProducts()
     * @param  list<literal-string>  $otajerOwned  the syncable columns the feed may update
     * @param  array<string, string|int>  $insertValues  admin-owned column => value written on insert
     * @param  array<string, string|int>  $notNullFallbacks  NOT_NULL_KEEP column => value for a new product with no feed value
     */
    private function syncChunk(array $chunk, array $otajerOwned, array $insertValues, array $notNullFallbacks): int
    {
        $connection = DB::connection();
        $table = (new Product)->getTable();
        $mysql = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $updates = [];
        foreach ([...$otajerOwned, 'synced_at'] as $column) {
            $incoming = $mysql && ! $connection->getConfig('use_upsert_alias')
                ? 'values('.$column.')'
                : ($mysql ? 'laravel_upsert_alias.' : 'excluded.').$column;
            // A product whose feed value is missing (or names a collection
            // this app does not have yet) keeps what it has.
            $incoming = in_array($column, self::KEEP_WHEN_MISSING, true) ? 'COALESCE('.$incoming.', products.'.$column.')' : $incoming;
            $updates[$column] = new Expression('CASE WHEN products.synced_at IS NOT NULL THEN '.$incoming.' ELSE products.'.$column.' END');
        }
        $now = now()->toDateTimeString();
        $chunk = $this->bindNotNullColumns($chunk, $otajerOwned, $notNullFallbacks, $table);
        $rows = array_map(fn (array $row): array => [...$row, ...$insertValues, 'synced_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk);
        $repriceStocks = in_array('unit_price', $otajerOwned, true);

        $ids = $connection->transaction(function () use ($connection, $table, $chunk, $rows, $updates, $repriceStocks, $now): array {
            $connection->table($table)->upsert($rows, ['id'], $updates);
            if ($repriceStocks) {
                $this->repriceStocks($connection, $table, array_column($chunk, 'id'), $now);
            }

            return $connection->table($table)->whereIn('id', array_column($chunk, 'id'))->whereNull('synced_at')->pluck('id')->all();
        });
        foreach ($ids as $id) {
            Log::warning('Product sync skipped a store-origin ID conflict.', ['id' => $id]);
            $this->components->warn('Product sync conflict: id '.$id.' is store-origin or unclassified.');
        }

        return count(array_filter($chunk, fn (array $row): bool => in_array($row['id'], $ids)));
    }

    /**
     * Replaces a null in a NOT_NULL_KEEP column the feed owns with that
     * product's current value, or the fallback for a product that does not
     * exist yet, so the upsert's COALESCE keeps the value and the insert list
     * never carries a NULL. Admin-owned columns are skipped: their insert
     * value replaces the row's value anyway.
     *
     * @param  list<array<string, mixed>>  $chunk
     * @param  list<literal-string>  $otajerOwned
     * @param  array<string, string|int>  $fallbacks
     * @return list<array<string, mixed>>
     */
    private function bindNotNullColumns(array $chunk, array $otajerOwned, array $fallbacks, string $table): array
    {
        foreach (array_intersect(self::NOT_NULL_KEEP, $otajerOwned) as $column) {
            $blank = array_column(array_filter($chunk, fn (array $row): bool => $row[$column] === null), 'id');
            if ($blank === []) {
                continue;
            }
            $current = DB::table($table)->whereIn('id', $blank)->pluck($column, 'id');
            $chunk = array_map(
                fn (array $row): array => $row[$column] === null ? [...$row, $column => $current->get($row['id']) ?? $fallbacks[$column]] : $row,
                $chunk,
            );
        }

        return $chunk;
    }

    /**
     * Mirrors the legacy import's stock pricing for the products just synced:
     * a PerBox stock row costs the product's `unit_price`, a PerPiece row that
     * price divided by `unit_equal`. Only rows named PerBox/PerPiece are
     * touched (a custom variant keeps its price), and a PerPiece row waits for
     * a positive `unit_equal`. Prices come from the products row after the
     * upsert, so a kept or admin-owned `unit_equal` is honoured.
     *
     * Runs inside the upsert's transaction, and each statement requires
     * `products.synced_at IS NOT NULL` in the database itself: a store-origin
     * product (the id conflicts the upsert skipped, or one inserted since)
     * never has its stock rows touched.
     *
     * @param  list<int>  $ids  the products of the chunk
     */
    private function repriceStocks(Connection $connection, string $table, array $ids, string $now): void
    {
        $stocks = (new ProductStock)->getTable();
        $feedProduct = fn (Builder $query): Builder => $query->select(DB::raw('1'))->from($table)
            ->whereColumn($table.'.id', $stocks.'.product_id')->whereNotNull($table.'.synced_at');

        $perBox = fn (Builder $query): Builder => $query->selectRaw('unit_price')->from($table)
            ->whereColumn($table.'.id', $stocks.'.product_id');
        // `* 1.0` keeps SQLite from dividing whole numbers as integers.
        $perPiece = fn (Builder $query): Builder => $query->selectRaw('ROUND(unit_price * 1.0 / unit_equal, 2)')->from($table)
            ->whereColumn($table.'.id', $stocks.'.product_id');

        $connection->table($stocks)->whereIn('product_id', $ids)->where('variant', 'like', '%PerBox%')
            ->whereExists($feedProduct)
            ->update(['price' => $perBox($connection->query()), 'updated_at' => $now]);
        $connection->table($stocks)->whereIn('product_id', $ids)->where('variant', 'like', '%PerPiece%')
            ->whereExists(fn (Builder $query): Builder => $feedProduct($query)->where($table.'.unit_equal', '>', 0))
            ->update(['price' => $perPiece($connection->query()), 'updated_at' => $now]);
    }

    /**
     * @return array<string, int>
     */
    private function collectionIdsBySerial(): array
    {
        return ProductCollection::query()->pluck('id', 'serial')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Maps the feed's rows onto the columns this app writes.
     *
     * `Mat_ID` is assumed to be the store's own `products.id` — both systems
     * share the store's product identifier space. The other properties that
     * fit a plain scalar column are mapped (`Serial`, `Price`, `UnitEnName`
     * twice, `UnitID`, `TaxPercent`, `Desc`, `Unit_Equal`), cut or cast to the
     * column; barcodes, variations, the image, categories, brands and unit
     * translations live elsewhere or need computation and are deliberately
     * left unmapped (.ai/rules/shared-database.md). A row missing a usable
     * `Mat_ID` or `enName` is skipped rather than written with a guessed
     * value; a missing or unusable value for a KEEP_WHEN_MISSING column maps
     * to null, which keeps the product's current value.
     *
     * `Mat_Class2_ID` names the product's margin collection, which
     * `import:collections` stores as `product_collections.serial`; one this
     * app has no collection for maps to null, and the update keeps the
     * product's current collection.
     *
     * @param  array<string, int>  $collectionIds  collection id by serial
     * @return list<array{id: int, name: string, ar_name: string, published: int, collection_id: int|null, serial: string|null, unit_price: float|null, unit: string|null, api_unitId: string|null, api_unit_name: string|null, tax: float|null, description: string|null, unit_equal: int|null}>
     */
    private function mapProducts(mixed $products, array $collectionIds = []): array
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
                'collection_id' => is_scalar($product['Mat_Class2_ID'] ?? null) ? ($collectionIds[(string) $product['Mat_Class2_ID']] ?? null) : null,
                'serial' => $this->text($product['Serial'] ?? null, 255),
                'unit_price' => $this->amount($product['Price'] ?? null),
                'unit' => $this->text($product['UnitEnName'] ?? null, 20),
                'api_unitId' => $this->text($product['UnitID'] ?? null, 255),
                'api_unit_name' => $this->text($product['UnitEnName'] ?? null, 255),
                'tax' => $this->amount($product['TaxPercent'] ?? null),
                'description' => $this->html($product['Desc'] ?? null),
                'unit_equal' => $this->wholeNumber($product['Unit_Equal'] ?? null),
            ];
        }

        return $rows;
    }

    /** A trimmed string cut to the column length, or null when blank or not a string or number. */
    private function text(mixed $value, int $length): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    /** A non-negative amount for a DECIMAL(20,2) column, or null when not numeric or out of range. */
    private function amount(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }
        $amount = round((float) $value, 2);

        return is_finite($amount) && $amount >= 0 && $amount < 1e18 ? $amount : null;
    }

    /** A whole number from 1 up to the INT maximum, or null (the legacy imports divide by it). */
    private function wholeNumber(mixed $value): ?int
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }
        $number = (float) $value;

        return $number >= 1 && $number <= 2147483647 && floor($number) === $number ? (int) $number : null;
    }

    /** The description cleaned the way the admin form cleans it, or null when nothing is left. */
    private function html(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $clean = clean_html($value);

        return trim($clean) === '' ? null : $clean;
    }
}

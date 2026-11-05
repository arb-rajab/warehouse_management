<?php

use App\Models\DynamicPopup;
use App\Models\Review;
use App\Models\Tax;
use App\Support\NewestFirst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/*
 * Every store back office list defaults to newest first: created_at descending,
 * then id descending. A sort the user picks wins. See
 * .ai/rules/store-list-default-order.md.
 */

/**
 * Lists with no sort control of their own: URL, permission, prop, table and a row's columns.
 *
 * @return array<string, array{string, string, string, string, array<string, mixed>}>
 */
function newestFirstLists(): array
{
    return [
        'taxes' => ['/temp/admin/tax', 'vat_&_tax_setup', 'taxes', 'taxes', ['tax_status' => 1]],
        'zones' => ['/temp/admin/zones', 'manage_zones', 'zones', 'zones', ['status' => 1]],
        'colors' => ['/temp/admin/colors', 'view_colors', 'colors', 'colors', ['code' => fn (int $index): string => sprintf('#%06x', $index)]],
    ];
}

/**
 * Insert rows with explicit creation timestamps and return their ids in insertion order.
 *
 * @param  list<string>  $createdAt
 * @param  array<string, mixed>  $columns  a Closure value is called with the row's index
 * @return list<int>
 */
function insertListRows(string $table, array $createdAt, array $columns): array
{
    return array_map(
        fn (string $timestamp, int $index): int => DB::table($table)->insertGetId([
            'name' => "Row $index",
            ...array_map(fn (mixed $value): mixed => $value instanceof Closure ? $value($index) : $value, $columns),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]),
        $createdAt,
        array_keys($createdAt),
    );
}

test('a list shows the newest record first, by creation time rather than by id', function (string $url, string $permission, string $prop, string $table, array $columns) {
    actingAsStoreUser(createStoreStaff($permission));
    [$march, $january, $february] = insertListRows($table, ['2026-03-01 10:00:00', '2026-01-01 10:00:00', '2026-02-01 10:00:00'], $columns);

    $ids = array_column($this->get($url)->assertOk()->inertiaProps($prop.'.data'), 'id');

    expect($ids)->toBe([$march, $february, $january]);
})->with(newestFirstLists());

test('records created at the same moment keep a stable newest-first order across pages', function (string $url, string $permission, string $prop, string $table, array $columns) {
    actingAsStoreUser(createStoreStaff($permission));
    $ids = insertListRows($table, array_fill(0, 25, '2026-02-01 10:00:00'), $columns);

    $first = array_column($this->get($url.'?page=1')->assertOk()->inertiaProps($prop.'.data'), 'id');
    $second = array_column($this->get($url.'?page=2')->assertOk()->inertiaProps($prop.'.data'), 'id');

    expect([...$first, ...$second])->toBe(array_reverse($ids))
        ->and($first)->toHaveCount(20)
        ->and($second)->toHaveCount(5);
})->with(newestFirstLists());

test('the rating sort of the reviews list wins, and newest first breaks its ties', function () {
    actingAsStoreUser(createStoreStaff('view_product_reviews'));
    $product = createStoreProduct(['name' => 'Dates', 'slug' => 'dates', 'added_by' => 'admin']);
    $customer = createStoreUser('customer');
    $review = fn (int $rating, string $createdAt): int => Review::forceCreate([
        'product_id' => $product->id, 'user_id' => $customer->id, 'rating' => $rating,
        'comment' => "Rated $rating", 'status' => 1, 'created_at' => $createdAt,
    ])->id;
    $oldFive = $review(5, '2026-01-01 00:00:00');
    $newOne = $review(1, '2026-03-01 00:00:00');
    $newFive = $review(5, '2026-02-01 00:00:00');
    $ids = fn (array $query = []): array => array_column($this->get('/temp/admin/reviews?'.http_build_query($query))->assertOk()->inertiaProps('reviews.data'), 'id');

    expect($ids())->toBe([$newOne, $newFive, $oldFive])
        ->and($ids(['rating' => 'rating,desc']))->toBe([$newFive, $oldFive, $newOne])
        ->and($ids(['rating' => 'rating,asc']))->toBe([$newOne, $newFive, $oldFive]);
});

test('the all-products list defaults to newest first and the legacy sort still wins', function () {
    actingAsStoreUser(createStoreStaff('show_all_products'));
    $old = createStoreProduct(['name' => 'Old oil', 'unit_price' => 1, 'thumbnail_img' => null]);
    $new = createStoreProduct(['name' => 'New oil', 'unit_price' => 5, 'thumbnail_img' => null]);
    $mid = createStoreProduct(['name' => 'Mid oil', 'unit_price' => 3, 'thumbnail_img' => null]);
    backdate($old, '2026-01-01 00:00:00');
    backdate($new, '2026-03-01 00:00:00');
    backdate($mid, '2026-02-01 00:00:00');
    $ids = fn (array $query = []): array => array_column($this->get(route('products.all', $query))->assertOk()->inertiaProps('products.data'), 'id');

    expect($ids())->toBe([$new->id, $mid->id, $old->id])
        ->and($ids(['type' => 'unit_price,asc']))->toBe([$old->id, $mid->id, $new->id]);
});

describe('NewestFirst', function () {
    test('orders by created_at then id, both descending, and only appends to an existing sort', function () {
        // The quoting differs by driver (backticks on MySQL, double quotes on SQLite).
        $sql = fn ($query): string => str_replace(['`', '"'], '', $query->toRawSql());

        expect($sql(Tax::query()->tap(NewestFirst::apply(...))))->toContain('order by taxes.created_at desc, taxes.id desc')
            ->and($sql(Tax::query()->orderBy('name')->tap(NewestFirst::apply(...))))->toContain('order by name asc, taxes.created_at desc, taxes.id desc')
            // A column the query is already ordered by is not added twice.
            ->and($sql(Tax::query()->orderBy('created_at')->tap(NewestFirst::apply(...))))->toContain('order by created_at asc, taxes.id desc');
    });

    test('qualifies the columns with the model table on a joined query', function () {
        $sql = DynamicPopup::query()->leftJoin('uploads', 'uploads.id', '=', 'dynamic_popups.banner')
            ->tap(NewestFirst::apply(...))->toRawSql();

        expect(str_replace(['`', '"'], '', $sql))->toContain('order by dynamic_popups.created_at desc, dynamic_popups.id desc');
    });

    test('orders a table without a creation timestamp by its key alone', function () {
        $model = new class extends Model
        {
            protected $table = 'legacy_rows';

            public $timestamps = false;
        };

        $sql = str_replace(['`', '"'], '', $model->newQuery()->tap(NewestFirst::apply(...))->toRawSql());

        expect($sql)->toContain('order by legacy_rows.id desc')->not->toContain('created_at');
    });
});

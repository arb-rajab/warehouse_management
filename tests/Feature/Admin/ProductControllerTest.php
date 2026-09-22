<?php

use App\Enums\CellLogAction;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the products index with every property the table renders', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(24)->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $cell->id,
        'expiration_date' => '2027-06-01',
    ]);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Products/Index')
            ->has('products.data', 1)
            ->has('products.data.0', fn (Assert $productProp) => $productProp
                ->where('id', $product->id)
                ->where('name', 'Widgets')
                ->where('ar_name', 'ودجات')
                ->where('image_url', 'https://cdn.example.com/widgets.png')
                ->where('active', true)
                ->where('boxes_count', 24)
                ->where('full_cells_count', 1)
                ->where('opened_cells_count', 0)
                ->where('expired_cells_count', 0)
                ->where('expiring_soon_count', 0)
                ->where('activity_today_count', 0)
                ->where('activity_week_count', 0)
            )
            ->where('today', '2026-08-13')
            ->where('weekStart', '2026-08-10')
            ->where('expiringSoonDays', 45)
            ->has('filterOptions.rows', 1)
            ->has('filterOptions.actions', 8)
    );

    Carbon::setTestNow();
});

test('the products index ships both raw name columns under the Arabic panel locale too', function () {
    // The Inertia payload no longer varies by locale: `lib/productName.ts`
    // picks the label client-side. See .ai/rules/shared-database.md.
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(24)->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $cell->id,
        'expiration_date' => '2027-06-01',
    ]);

    $response = $this->withSession(['locale' => 'ar'])->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Products/Index')
            ->has('products.data', 1)
            ->has('products.data.0', fn (Assert $productProp) => $productProp
                ->where('id', $product->id)
                ->where('name', 'Widgets')
                ->where('ar_name', 'ودجات')
                ->where('image_url', 'https://cdn.example.com/widgets.png')
                ->where('active', true)
                ->where('boxes_count', 24)
                ->where('full_cells_count', 1)
                ->where('opened_cells_count', 0)
                ->where('expired_cells_count', 0)
                ->where('expiring_soon_count', 0)
                ->where('activity_today_count', 0)
                ->where('activity_week_count', 0)
            )
    );

    Carbon::setTestNow();
});

test('the products index ships an empty ar_name for a product the store never translated', function () {
    // NOT NULL upstream, so an untranslated product carries an empty string —
    // that is what the frontend resolver falls back on, and it must reach it.
    actingAsAdmin();
    Product::factory()->create(['name' => 'Widgets', 'ar_name' => '']);
    // Noise: a translated sibling proves the empty value is per product, not a
    // whole-listing decision.
    Product::factory()->create(['name' => 'Aardvarks', 'ar_name' => 'حيوانات']);

    $response = $this->withSession(['locale' => 'ar'])->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)
            // Ordering deliberately stays on the base `name` column in both
            // locales, so "Aardvarks" still sorts first.
            ->where('products.data.0.name', 'Aardvarks')
            ->where('products.data.0.ar_name', 'حيوانات')
            ->where('products.data.1.name', 'Widgets')
            ->where('products.data.1.ar_name', '')
    );
});

test('the products index hydrates the product filter chips with both raw name columns', function () {
    actingAsAdmin();
    $selected = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unselected Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/products?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0', ['id' => $selected->id, 'name' => 'Widgets', 'ar_name' => 'ودجات'])
    );
});

test('the products index paginates instead of returning every product at once', function () {
    actingAsAdmin();

    Product::factory()->count(30)->create();

    $response = $this->get('/admin/products');

    assertInertiaPaginates($response, 'products', 20, 30);
});

test('the products index respects a per_page query parameter', function () {
    actingAsAdmin();

    Product::factory()->count(30)->create();

    $response = $this->get('/admin/products?per_page=10');

    assertInertiaPaginates($response, 'products', 10, 30);
    $response->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10));
});

test('the products index rejects a per_page value outside the allowed options', function () {
    actingAsAdmin();

    $response = $this->get('/admin/products?per_page=999');

    $response->assertSessionHasErrors('per_page');
});

test('the products index hydrates only the selected product ids for the product filter, not every product', function () {
    actingAsAdmin();
    $selected = Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Unselected Gadgets']);

    $response = $this->get("/admin/products?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0.id', $selected->id)
    );
});

test('the products index can be filtered by product, excluding other products from the list', function () {
    actingAsAdmin();
    $matching = Product::factory()->create();
    Product::factory()->create();

    $response = $this->get("/admin/products?product_id[]={$matching->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );
});

test('the products index splits the occupancy count into full and opened columns', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id]);
    Pallet::factory()->opened()->create(['product_id' => $product->id]);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.full_cells_count', 1)
            ->where('products.data.0.opened_cells_count', 1)
    );
});

test('the products index counts only cells in the filtered row toward full/opened counts, not the product\'s cells elsewhere', function () {
    actingAsAdmin();
    $rowA = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $rowA->cells()->first()->id]);
    Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $rowB->cells()->first()->id]);

    $response = $this->get("/admin/products?row_id={$rowA->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.full_cells_count', 1)
    );
});

test('the products index counts only cells in the filtered column toward full/opened counts', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $columnOneCell = $row->cells()->where('cell_number', 1)->first();
    $columnTwoCell = $row->cells()->where('cell_number', 2)->first();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $columnOneCell->id]);
    Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $columnTwoCell->id]);

    $response = $this->get('/admin/products?column_number=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.full_cells_count', 1)
    );
});

test('the state filter zeroes out the opened count while leaving the full count intact', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id]);
    Pallet::factory()->opened()->create(['product_id' => $product->id]);

    $response = $this->get('/admin/products?state=full');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.full_cells_count', 1)
            ->where('products.data.0.opened_cells_count', 0)
    );
});

test('the products index always shows the expired count regardless of filters', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-08-01']);
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-09-01']);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.expired_cells_count', 1)
    );

    Carbon::setTestNow();
});

test('a pallet with no expiration date is excluded from the expired and expiring-soon counts, without throwing', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => null]);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.expired_cells_count', 0)
            ->where('products.data.0.expiring_soon_count', 0)
    );

    Carbon::setTestNow();
});

test('the expired filter narrows the full/opened/expiring-soon counts to already-expired pallets', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-08-01']);
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-09-01']);

    $response = $this->get('/admin/products?expired=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.full_cells_count', 1)
            ->where('products.data.0.expired_cells_count', 1)
    );

    Carbon::setTestNow();
});

test('the expired/expiring-soon pallet subqueries compare expiration_date directly, without wrapping it in a date() function', function () {
    // expiration_date is already a DATE column — date()/strftime() around it
    // makes the comparison a function of the column, which the index added
    // for it cannot satisfy on MySQL. See .ai/rules/shared-database.md.
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-08-01']);

    DB::enableQueryLog();
    $this->get('/admin/products?expired=true&expires_within_days=7')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
    DB::disableQueryLog();

    expect($queries)->toContain('"expiration_date"')
        ->and($queries)->not->toContain('date("expiration_date")')
        ->and($queries)->not->toContain("strftime('Date', \"expiration_date\")");

    Carbon::setTestNow();
});

test('the inactive filter narrows the products index to the store admin\'s deactivated products, regardless of cell occupancy', function () {
    actingAsAdmin();

    // Deactivated but still occupying a cell — proves `inactive` reads
    // `published`, not occupancy (see .ai/rules/shared-database.md).
    $inactive = Product::factory()->inactive()->create();
    Pallet::factory()->create(['product_id' => $inactive->id]);

    // Active but with zero pallets — would look "inactive" under an
    // occupancy-based definition, and must NOT be matched.
    $activeNoPallets = Product::factory()->create();

    // Noise: another active product that also occupies a cell.
    $activeWithPallet = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $activeWithPallet->id]);

    $response = $this->get('/admin/products?inactive=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $inactive->id)
            ->where('products.data.0.active', false)
    );
});

test('the inactive filter is omitted (all products shown) when not sent', function () {
    actingAsAdmin();

    Product::factory()->inactive()->create();
    Product::factory()->create();

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)
    );
});

test('the expiring-soon count defaults to a 45-day window when expires_within_days is not filled in', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-09-20']);
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-11-01']);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.expiring_soon_count', 1)
            ->where('expiringSoonDays', 45)
    );

    Carbon::setTestNow();
});

test('the expiring-soon count and window use expires_within_days when it is filled in', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-08-20']);
    Pallet::factory()->create(['product_id' => $product->id, 'expiration_date' => '2026-09-01']);

    $response = $this->get('/admin/products?expires_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.expiring_soon_count', 1)
            ->where('expiringSoonDays', 7)
    );

    Carbon::setTestNow();
});

test('the products index counts today\'s and this week\'s activity separately', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    backdate(CellStatusLog::factory()->create(['product_id' => $product->id]), '2026-08-13 09:00:00');
    backdate(CellStatusLog::factory()->create(['product_id' => $product->id]), '2026-08-11 09:00:00');
    backdate(CellStatusLog::factory()->create(['product_id' => $product->id]), '2026-07-01 09:00:00');

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.activity_today_count', 1)
            ->where('products.data.0.activity_week_count', 2)
    );

    Carbon::setTestNow();
});

test('the action filter narrows today\'s and this week\'s activity counts', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    backdate(CellStatusLog::factory()->create(['product_id' => $product->id, 'action' => CellLogAction::Opened]), '2026-08-13 09:00:00');
    backdate(CellStatusLog::factory()->create(['product_id' => $product->id, 'action' => CellLogAction::Emptied]), '2026-08-13 08:00:00');

    $response = $this->get('/admin/products?action[]=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.activity_today_count', 1)
            ->where('products.data.0.activity_week_count', 1)
    );

    Carbon::setTestNow();
});

test('the state filter alone narrows today\'s and this week\'s activity counts, without needing a row/column filter too', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $product = Product::factory()->create();
    $fullPallet = Pallet::factory()->create(['product_id' => $product->id]);
    $openedPallet = Pallet::factory()->opened()->create(['product_id' => $product->id]);

    backdate(CellStatusLog::factory()->create(['product_id' => $product->id, 'cell_id' => $fullPallet->cell_id]), '2026-08-13 09:00:00');
    backdate(CellStatusLog::factory()->create(['product_id' => $product->id, 'cell_id' => $openedPallet->cell_id]), '2026-08-13 08:00:00');

    $response = $this->get('/admin/products?state=full');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.activity_today_count', 1)
            ->where('products.data.0.activity_week_count', 1)
    );

    Carbon::setTestNow();
});

test('filtering the products index by state=empty is rejected, since an empty cell never holds a product', function () {
    actingAsAdmin();

    $response = $this->get('/admin/products?state=empty');

    $response->assertSessionHasErrors('state');
});

test('filtering the products index by expires_within_days=0 is rejected in favor of the expired filter', function () {
    actingAsAdmin();

    $response = $this->get('/admin/products?expires_within_days=0');

    $response->assertSessionHasErrors('expires_within_days');
});

test('once a history filter is active, a product with no matching log entry is excluded entirely', function () {
    actingAsAdmin();
    $mover = User::factory()->create();
    $otherMover = User::factory()->create();

    $matching = Product::factory()->create();
    CellStatusLog::factory()->create(['product_id' => $matching->id, 'user_id' => $mover->id]);

    $excluded = Product::factory()->create();
    CellStatusLog::factory()->create(['product_id' => $excluded->id, 'user_id' => $otherMover->id]);

    $response = $this->get("/admin/products?user_id[]={$mover->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );
});

test('the products index can be filtered by status change action, excluding products with no matching action', function () {
    actingAsAdmin();
    $matching = Product::factory()->create();
    CellStatusLog::factory()->create(['product_id' => $matching->id, 'action' => CellLogAction::Opened]);

    $excluded = Product::factory()->create();
    CellStatusLog::factory()->create(['product_id' => $excluded->id, 'action' => CellLogAction::Emptied]);

    $response = $this->get('/admin/products?action[]=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );
});

test('the products index can be filtered by a date range, excluding products with only out-of-range activity', function () {
    actingAsAdmin();
    $matching = Product::factory()->create();
    backdate(CellStatusLog::factory()->create(['product_id' => $matching->id]), '2026-06-15 00:00:00');

    $excluded = Product::factory()->create();
    backdate(CellStatusLog::factory()->create(['product_id' => $excluded->id]), '2026-01-01 00:00:00');

    $response = $this->get('/admin/products?date_from=2026-06-01&date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );
});

test('the products index can be filtered by created_within_days, excluding products whose only activity is older', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $matching = Product::factory()->create();
    backdate(CellStatusLog::factory()->create(['product_id' => $matching->id]), '2026-08-10 00:00:00');

    $excluded = Product::factory()->create();
    backdate(CellStatusLog::factory()->create(['product_id' => $excluded->id]), '2026-08-01 00:00:00');

    $response = $this->get('/admin/products?created_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );

    Carbon::setTestNow();
});

test('filtering the products index by created_within_days together with a date range is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/products?created_within_days=7&date_from=2026-06-01');

    $response->assertSessionHasErrors('created_within_days');
});

test('the products index can be sorted by the full cells count', function () {
    actingAsAdmin();
    $fewer = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $fewer->id]);

    $more = Product::factory()->create();
    Pallet::factory()->count(2)->create(['product_id' => $more->id]);

    $ascending = $this->get('/admin/products?sort_by=full_cells_count&sort_direction=asc');

    $ascending->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.id', $fewer->id)
            ->where('products.data.1.id', $more->id)
    );

    $descending = $this->get('/admin/products?sort_by=full_cells_count&sort_direction=desc');

    $descending->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.id', $more->id)
            ->where('products.data.1.id', $fewer->id)
    );
});

test('a mobile app user cannot view the products index', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/products');

    $response->assertForbidden();
});

test('an unauthenticated caller cannot view the products index', function () {
    $response = $this->get('/admin/products');

    $response->assertRedirect(route('login'));
});

test('an authenticated admin can search products with every property the filter reads', function () {
    actingAsAdmin();

    $product = Product::factory()->imageUrl('https://cdn.example.com/widget.png')->create([
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    $otherProduct = Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson('/admin/products/search');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
});

test('the product search excludes deactivated products', function () {
    actingAsAdmin();

    $active = Product::factory()->create(['name' => 'Widget Blue']);
    // Noise: a deactivated product matching the same search term must not be returned.
    $inactive = Product::factory()->inactive()->create(['name' => 'Widget Red']);

    $response = $this->getJson('/admin/products/search?q=Widget');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))
        ->toContain($active->id)
        ->not->toContain($inactive->id);
});

test('the product search options carry both raw name columns under the Arabic panel locale too', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widget', 'ar_name' => 'ودجة']);
    // Noise: another product's names must not be the ones returned.
    Product::factory()->create(['name' => 'Gadget', 'ar_name' => 'أداة']);

    $response = $this->withSession(['locale' => 'ar'])->getJson('/admin/products/search');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => 'ودجة',
    ]);
});

test('a searched product the store never translated carries an empty ar_name, not a null one', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widget', 'ar_name' => '']);

    $response = $this->withSession(['locale' => 'ar'])->getJson('/admin/products/search');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
        'ar_name' => '',
    ]);
});

test('the product search paginates instead of returning every product at once', function () {
    actingAsAdmin();

    Product::factory()->count(25)->create();

    $response = $this->getJson('/admin/products/search');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
});

test('the product search filters by name, excluding a non-matching product', function () {
    actingAsAdmin();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/admin/products/search?q=Widg');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product search filters by the store\'s Arabic name, excluding a non-matching product', function () {
    actingAsAdmin();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/admin/products/search?q='.urlencode('ودجات'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    // Searching matches either column, and the option carries both raw — the
    // locale only decides which of them the frontend renders.
    expect($response->json('data.0'))->toEqual(['id' => $matching->id, 'name' => 'Widgets', 'ar_name' => 'ودجات']);
});

test('the product search matches a multi-word term split across the two name columns', function () {
    actingAsAdmin();

    $matching = Product::factory()->create(['name' => 'Large Blue Widget', 'ar_name' => 'ودجة زرقاء كبيرة']);
    // Noise: matches only the English half of the term.
    Product::factory()->create(['name' => 'Small Widget', 'ar_name' => 'ودجة صغيرة']);

    $response = $this->getJson('/admin/products/search?q='.urlencode('Widget زرقاء'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
});

test('the product search returns every product for a blank term', function () {
    actingAsAdmin();

    Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->getJson('/admin/products/search?q=');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('a non-admin user cannot search products', function () {
    actingAsMobilePanelUser();

    $response = $this->getJson('/admin/products/search');

    $response->assertForbidden();
});

test('an unauthenticated caller cannot search products', function () {
    $response = $this->getJson('/admin/products/search');

    $response->assertUnauthorized();
});

test('an authenticated admin can set how many boxes a pallet of a product holds', function () {
    actingAsAdmin();

    $product = Product::factory()->boxesCount(6)->create();
    $otherProduct = Product::factory()->boxesCount(9)->create();

    $response = $this->patch("/admin/products/{$product->id}/box-count", ['boxes_count' => 30]);

    $response->assertRedirect(route('admin.products.index'));
    $this->assertDatabaseHas('wms_product_settings', [
        'product_id' => $product->id,
        'boxes_count' => 30,
    ]);
    expect($otherProduct->fresh()->boxes_count)->toBe(9);
});

test('setting a box count creates the settings row for a product that has never had one', function () {
    actingAsAdmin();

    // The store can add a product at any time without this app knowing.
    $product = Product::factory()->unconfigured()->create();
    expect($product->fresh()->boxes_count)->toBe(Product::DEFAULT_BOXES_COUNT);

    $this->patch("/admin/products/{$product->id}/box-count", ['boxes_count' => 15]);

    expect($product->fresh()->boxes_count)->toBe(15);
    $this->assertDatabaseCount('wms_product_settings', 1);
});

test('the box count must be a whole number of at least one', function () {
    actingAsAdmin();

    $product = Product::factory()->boxesCount(6)->create();

    foreach ([0, -3, 'many'] as $invalid) {
        $response = $this->patch("/admin/products/{$product->id}/box-count", ['boxes_count' => $invalid]);

        $response->assertSessionHasErrors('boxes_count');
    }

    $response = $this->patch("/admin/products/{$product->id}/box-count", []);
    $response->assertSessionHasErrors('boxes_count');

    expect($product->fresh()->boxes_count)->toBe(6);
});

test('a mobile app user cannot set a box count', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->boxesCount(6)->create();

    $response = $this->patch("/admin/products/{$product->id}/box-count", ['boxes_count' => 30]);

    $response->assertForbidden();
    expect($product->fresh()->boxes_count)->toBe(6);
});

test('an unauthenticated caller cannot set a box count', function () {
    $product = Product::factory()->boxesCount(6)->create();

    $response = $this->patch("/admin/products/{$product->id}/box-count", ['boxes_count' => 30]);

    $response->assertRedirect(route('login'));
    expect($product->fresh()->boxes_count)->toBe(6);
});

test('setting a box count for a non-existent product returns a 404', function () {
    actingAsAdmin();

    $response = $this->patch('/admin/products/999999/box-count', ['boxes_count' => 30]);

    $response->assertNotFound();
});

test('an authenticated admin can export a QR code image for a product, with both its names', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml');

    $svg = $response->getContent();
    // A real QR is embedded as a base64 SVG data URI — regression guard for the
    // QR silently failing to render rather than just the surrounding text.
    expect($svg)->toContain('data:image/svg+xml;base64,');
    expect($svg)->toContain('Widgets');
    expect($svg)->toContain('ودجات');
    // Regression guard: a short name must still render as a single line per
    // field (one <text> for the name, one for the Arabic name), not wrapped.
    expect(substr_count($svg, '<text'))->toBe(2);
});

test('a product QR export uses the configured QR code size instead of the default', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 400, 'qr_code_height' => 500]);
    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    $svg = $response->getContent();
    // The label canvas width is derived from the configured QR width
    // (qrWidth + 2*padding, padding=20 — see BuildsQrLabels::qrLabelImage()),
    // and the embedded <image> is placed at the exact configured size.
    expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="440"')
        ->and($svg)->toContain('width="400" height="500"/>');
});

test('a product with a long name has its QR code label text wrapped instead of clipped', function () {
    actingAsAdmin();

    $longName = trim(str_repeat('Widget Component ', 11)); // 187 chars, under the 191-char column limit
    $product = Product::factory()->create(['name' => $longName, 'ar_name' => '']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();

    $svg = $response->getContent();
    // The name wraps across several <text> lines rather than one, so the full
    // name doesn't appear as one contiguous string — instead assert none of
    // its words were dropped (a clipped label would lose the tail end) and
    // that more than one line was actually rendered.
    expect(substr_count($svg, 'Widget'))->toBe(11);
    expect(substr_count($svg, 'Component'))->toBe(11);
    expect(substr_count($svg, '<text'))->toBeGreaterThan(2);
});

test('a product with no Arabic name ships a QR code image with only the English name', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => '']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    expect($response->getContent())->toContain('Widgets');
});

test('a mobile app user cannot export a products QR code', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->create();

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertForbidden();
});

test('an unauthenticated caller cannot export a products QR code', function () {
    $product = Product::factory()->create();

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertRedirect(route('login'));
});

test('exporting a QR code for a non-existent product returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/products/999999/export-qr');

    $response->assertNotFound();
});

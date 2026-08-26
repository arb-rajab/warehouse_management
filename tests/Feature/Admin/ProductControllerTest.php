<?php

use App\Enums\CellLogAction;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the products index with every property the table renders', function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
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
                ->where('image_url', 'https://cdn.example.com/widgets.png')
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
            ->has('filterOptions.actions', 5)
    );

    Carbon::setTestNow();
});

test('the products index paginates instead of returning every product at once', function () {
    actingAsAdmin();

    Product::factory()->count(30)->create();

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 25)
            ->where('products.meta.total', 30)
    );
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

    $product = Product::factory()->create([
        'name' => 'Widget',
        'image_url' => 'https://cdn.example.com/widget.png',
    ]);
    $otherProduct = Product::factory()->create(['name' => 'Gadget']);

    $response = $this->getJson('/admin/products/search');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $product->id))->toEqual([
        'id' => $product->id,
        'name' => 'Widget',
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
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

    $matching = Product::factory()->create(['name' => 'Widgets']);
    Product::factory()->create(['name' => 'Unrelated Gadgets']);

    $response = $this->getJson('/admin/products/search?q=Widg');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($matching->id);
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

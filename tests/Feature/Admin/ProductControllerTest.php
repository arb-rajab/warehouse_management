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

    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(24)->minimumPallets(5)->create([
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
                ->where('minimum_pallets', 5)
                ->where('pallets_count', 1)
                ->where('full_cells_count', 1)
                ->where('opened_cells_count', 0)
                ->where('expired_cells_count', 0)
                ->where('expiring_soon_count', 0)
            )
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

    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->boxesCount(24)->minimumPallets(5)->create([
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
                ->where('minimum_pallets', 5)
                ->where('pallets_count', 1)
                ->where('full_cells_count', 1)
                ->where('opened_cells_count', 0)
                ->where('expired_cells_count', 0)
                ->where('expiring_soon_count', 0)
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

test('the state filter excludes a product with no pallet in that state, instead of just zeroing its count', function () {
    actingAsAdmin();
    $matching = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $matching->id]);

    // Noise: only has an opened pallet, so it must not appear under state=full.
    $excluded = Product::factory()->create();
    Pallet::factory()->opened()->create(['product_id' => $excluded->id]);

    $response = $this->get('/admin/products?state=full');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );
});

test('the expired filter excludes a product with no expired pallet', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $matching = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $matching->id, 'expiration_date' => '2026-08-01']);

    // Noise: only has a not-yet-expired pallet.
    $excluded = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $excluded->id, 'expiration_date' => '2026-09-01']);

    $response = $this->get('/admin/products?expired=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );

    Carbon::setTestNow();
});

test('the expires_within_days filter excludes a product with no pallet expiring within that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $matching = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $matching->id, 'expiration_date' => '2026-08-20']);

    // Noise: only has a pallet expiring well outside the requested window.
    $excluded = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $excluded->id, 'expiration_date' => '2026-12-01']);

    $response = $this->get('/admin/products?expires_within_days=7');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
    );

    Carbon::setTestNow();
});

test('a product with zero pallets is excluded once any occupancy filter is active', function () {
    actingAsAdmin();
    $matching = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $matching->id]);

    // Noise: no pallets at all, so it must not appear under any occupancy filter.
    Product::factory()->create();

    $response = $this->get('/admin/products?state=full');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
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

test('pallets_count counts every pallet of the product regardless of cell state, unlike the occupancy columns', function () {
    actingAsAdmin();
    // Fixed names pin the default name-ascending order, so products.data.0 is
    // always $product — random factory names let $other sort first about
    // half the time.
    $product = Product::factory()->create(['name' => 'Alpha Widgets']);
    Pallet::factory()->create(['product_id' => $product->id]);
    Pallet::factory()->opened()->create(['product_id' => $product->id]);

    // Noise: another product's pallets must not be counted here.
    $other = Product::factory()->create(['name' => 'Zulu Widgets']);
    Pallet::factory()->create(['product_id' => $other->id]);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.pallets_count', 2)
    );
});

test('minimum_pallets is null in the index response for a product nobody has configured a threshold for', function () {
    actingAsAdmin();
    Product::factory()->create();

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.minimum_pallets', null)
    );
});

test('the low_stock filter narrows the products index to products below their configured minimum, excluding a fully-stocked product', function () {
    actingAsAdmin();

    $low = Product::factory()->minimumPallets(5)->create();
    Pallet::factory()->create(['product_id' => $low->id]);

    // Noise: at (not below) its minimum, so it must not be matched.
    $atMinimum = Product::factory()->minimumPallets(2)->create();
    Pallet::factory()->count(2)->create(['product_id' => $atMinimum->id]);

    // Noise: no threshold configured at all, however few pallets it has.
    Product::factory()->create();

    $response = $this->get('/admin/products?low_stock=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $low->id)
    );
});

test('the low_stock filter is omitted (all products shown) when not sent', function () {
    actingAsAdmin();

    Product::factory()->minimumPallets(5)->create();
    Product::factory()->create();

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)
    );
});

test('an authenticated admin can set a minimum pallets threshold for a product', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $otherProduct = Product::factory()->minimumPallets(9)->create();

    $response = $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 10]);

    $response->assertRedirect(route('admin.products.index'));
    $this->assertDatabaseHas('wms_product_settings', [
        'product_id' => $product->id,
        'minimum_pallets' => 10,
    ]);
    expect($otherProduct->fresh()->minimum_pallets)->toBe(9);
});

test('an authenticated admin can clear a product\'s minimum pallets threshold by sending null', function () {
    actingAsAdmin();

    $product = Product::factory()->minimumPallets(10)->create();

    $response = $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => null]);

    $response->assertRedirect(route('admin.products.index'));
    expect($product->fresh()->minimum_pallets)->toBeNull();
});

test('setting a minimum pallets threshold redirects back with the current page and filters preserved', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->patch("/admin/products/{$product->id}/minimum-pallets?page=2&inactive=1", ['minimum_pallets' => 10]);

    $response->assertRedirect(route('admin.products.index', ['page' => 2, 'inactive' => 1]));
});

test('setting a minimum pallets threshold creates the settings row for a product that has never had one, without dropping its box count to the database default', function () {
    actingAsAdmin();

    // The store can add a product at any time without this app knowing.
    $product = Product::factory()->unconfigured()->create();
    expect($product->fresh()->boxes_count)->toBe(Product::DEFAULT_BOXES_COUNT);

    $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 15]);

    // Regression: wms_product_settings.boxes_count has its own DB-level
    // default of 1, divorced from Product::DEFAULT_BOXES_COUNT (50) — an
    // insert that omits boxes_count would silently show 1 instead of the 50
    // the product displayed before this row existed.
    expect($product->fresh())
        ->minimum_pallets->toBe(15)
        ->boxes_count->toBe(Product::DEFAULT_BOXES_COUNT);
    $this->assertDatabaseCount('wms_product_settings', 1);
});

test('setting a minimum pallets threshold on a product with an already-configured box count leaves that box count untouched', function () {
    actingAsAdmin();

    $product = Product::factory()->boxesCount(24)->create();

    $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 15]);

    expect($product->fresh())
        ->minimum_pallets->toBe(15)
        ->boxes_count->toBe(24);
});

test('the minimum pallets threshold, when sent, must be a whole number of at least one', function () {
    actingAsAdmin();

    $product = Product::factory()->minimumPallets(6)->create();

    foreach ([0, -3, 'many'] as $invalid) {
        $response = $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => $invalid]);

        $response->assertSessionHasErrors('minimum_pallets');
    }

    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('omitting the minimum_pallets field entirely is rejected, unlike sending an explicit null', function () {
    actingAsAdmin();

    $product = Product::factory()->minimumPallets(6)->create();

    $response = $this->patch("/admin/products/{$product->id}/minimum-pallets", []);

    $response->assertSessionHasErrors('minimum_pallets');
    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('a mobile app user cannot set a minimum pallets threshold', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->minimumPallets(6)->create();

    $response = $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 10]);

    $response->assertForbidden();
    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('an unauthenticated caller cannot set a minimum pallets threshold', function () {
    $product = Product::factory()->minimumPallets(6)->create();

    $response = $this->patch("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 10]);

    $response->assertRedirect(route('login'));
    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('setting a minimum pallets threshold for a non-existent product returns a 404', function () {
    actingAsAdmin();

    $response = $this->patch('/admin/products/999999/minimum-pallets', ['minimum_pallets' => 10]);

    $response->assertNotFound();
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

test('setting a box count redirects back with the current page and filters preserved', function () {
    actingAsAdmin();

    $product = Product::factory()->boxesCount(6)->create();

    $response = $this->patch("/admin/products/{$product->id}/box-count?page=2&inactive=1", ['boxes_count' => 30]);

    $response->assertRedirect(route('admin.products.index', ['page' => 2, 'inactive' => 1]));
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
    // A real QR is spliced in as inline SVG markup rather than a nested
    // `<image>` reference, which dompdf silently skips (see
    // BuildsQrLabels::qrSvgInnerMarkup()) — regression guard for the QR
    // failing to render rather than just the surrounding text.
    expect($svg)->toContain('<g transform="translate(20,20)"><rect x="0" y="0"')
        ->and($svg)->toContain('<path fill-rule="evenodd"')
        ->and($svg)->not->toContain('<image');
    expect($svg)->toContain('Widgets');
    expect($svg)->toContain('ودجات');
    expect($svg)->toContain("ID: {$product->id}");
    // Regression guard: a short name must still render as a single line per
    // field (one <text> for the id caption, one for the name, one for the
    // Arabic name), not wrapped.
    expect(substr_count($svg, '<text'))->toBe(3);
});

test('a product QR export uses the configured QR code size instead of the default', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 400, 'qr_code_height' => 500]);
    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    $svg = $response->getContent();
    // The label canvas width is exactly the configured total-box width, and
    // the embedded QR square is that width minus padding on both sides
    // (padding=20 — see BuildsQrLabels::qrLabelImage()); qr_code_height is a
    // ceiling on the whole label including text, not the QR's own size.
    expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="400"')
        ->and($svg)->toContain('<g transform="translate(20,20)"><rect x="0" y="0" width="360" height="360"');
});

test('a product with a long name has its QR code label text wrapped across multiple lines, up to the configured height', function () {
    actingAsAdmin();
    // Pinned to a box this specific name is known to overflow, rather than
    // relying on whatever Setting::DEFAULT_QR_CODE_WIDTH/HEIGHT happens to be
    // — the current defaults are sized generously (for cell QR scan distance,
    // see Setting::DEFAULT_QR_CODE_WIDTH's docblock), large enough that even
    // this column's 200-char max name never needs to wrap, let alone
    // truncate. This test needs a genuinely tight box to exercise
    // clampLinesToHeight()'s truncation path at all.
    Setting::factory()->create(['qr_code_width' => 280, 'qr_code_height' => 380]);

    $longName = trim(str_repeat('Widget Component ', 11)); // 187 chars, under the 191-char column limit
    $product = Product::factory()->create(['name' => $longName, 'ar_name' => '']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();

    $svg = $response->getContent();
    // The name wraps across several <text> lines rather than one, so the full
    // name doesn't appear as one contiguous string — but qr_code_height is a
    // ceiling on the whole label (see BuildsQrLabels::qrLabelImage()), so a
    // name that would need more lines than fit within it is truncated with
    // an ellipsis instead of growing the label past the configured height.
    expect(substr_count($svg, '<text'))->toBeGreaterThan(1);
    expect($svg)->toContain('…');
    // The id caption is drawn and budget-clamped before the primary text, so
    // truncating a long name never costs the id its own line.
    expect($svg)->toContain("ID: {$product->id}");

    preg_match('/<svg[^>]*height="(\d+)"/', $svg, $matches);
    expect((int) $matches[1])->toBeLessThanOrEqual(380);
});

test('a QR label never grows past the configured height, even with three long/mandatory text fields', function () {
    actingAsAdmin();
    // 260 was tight enough to fit exactly one primary line before the id
    // caption existed; now the mandatory id line eats into that same budget,
    // so the height is raised just enough to keep both the id and one
    // truncated primary line while still forcing the Arabic name out.
    Setting::factory()->create(['qr_code_width' => 240, 'qr_code_height' => 300]);

    $longName = trim(str_repeat('Widget Component ', 11));
    $longArName = trim(str_repeat('منتج تجريبي ', 11));
    $product = Product::factory()->create(['name' => $longName, 'ar_name' => $longArName]);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    $svg = $response->getContent();

    preg_match('/<svg[^>]*height="(\d+)"/', $svg, $matches);
    expect((int) $matches[1])->toBeLessThanOrEqual(300);
    expect($svg)->toContain("ID: {$product->id}");
    expect($svg)->toContain('…');
    // The id caption plus the name alone fill the entire text budget at this
    // height, so the Arabic name is dropped rather than the label growing
    // past the cap — never shrinking the QR itself to squeeze all fields in.
    expect($svg)->not->toContain('منتج');
});

test('a product QR export label renders the product id as its own visible text line', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    $svg = $response->getContent();

    // Not just encoded somewhere inside the QR's own markup —
    // asserted as its own readable <text> element a person could read off
    // the printed sticker.
    expect($svg)->toMatch('/<text[^>]*>ID: '.$product->id.'<\/text>/');
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

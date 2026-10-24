<?php

use App\Enums\CellLogAction;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductBarcode;
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
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1234567890123']);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Products/Index')
            ->has('products.data', 1)
            ->has('products.data.0', fn (Assert $productProp) => $productProp
                ->where('id', $product->id)
                ->where('name', 'Widgets')
                ->where('ar_name', 'ودجات')
                ->where('image_url', 'https://cdn.example.com/widgets.png')
                ->where('image_verification', 'needs_review')
                ->where('active', true)
                ->where('boxes_count', 24)
                ->where('minimum_pallets', 5)
                ->where('pallets_count', 1)
                ->where('full_cells_count', 1)
                ->where('opened_cells_count', 0)
                ->where('expired_cells_count', 0)
                ->where('expiring_soon_count', 0)
                ->where('barcodes', [['id' => $barcode->id, 'barcode' => '1234567890123']])
            )
            ->where('expiringSoonDays', 45)
            ->has('filterOptions.rows', 1)
            ->has('filterOptions.actions', 9)
    );

    Carbon::setTestNow();
});

test('the products index ships an empty barcodes list for a product nobody has added one to', function () {
    actingAsAdmin();
    Product::factory()->create();

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.barcodes', [])
    );
});

test('the products index orders a product\'s barcodes by id, oldest first', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    // Inserted in this order so a plain alphabetical/numeric sort of the
    // barcode text itself would produce the opposite order — proving the
    // ordering is by id (insertion order), not by the barcode value.
    $olderBarcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '2222222222222']);
    $newerBarcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1111111111111']);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.barcodes', [
            ['id' => $olderBarcode->id, 'barcode' => '2222222222222'],
            ['id' => $newerBarcode->id, 'barcode' => '1111111111111'],
        ])
    );
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
                ->where('image_verification', 'needs_review')
                ->where('active', true)
                ->where('boxes_count', 24)
                ->where('minimum_pallets', 5)
                ->where('pallets_count', 1)
                ->where('full_cells_count', 1)
                ->where('opened_cells_count', 0)
                ->where('expired_cells_count', 0)
                ->where('expiring_soon_count', 0)
                ->where('barcodes', [])
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
            // Sort by the displayed name: the untranslated English label
            // precedes the translated Arabic label in ascending order.
            ->where('products.data.0.name', 'Widgets')
            ->where('products.data.0.ar_name', '')
            ->where('products.data.1.name', 'Aardvarks')
            ->where('products.data.1.ar_name', 'حيوانات')
    );
});

test('the products index hydrates the product filter chips with both raw name columns', function () {
    actingAsAdmin();
    $selected = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    Product::factory()->create(['name' => 'Unselected Gadgets', 'ar_name' => 'أدوات']);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/products?product_id[]={$selected->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 1)
            ->where('filterOptions.products.0', ['id' => $selected->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'active' => true])
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

test('expired=false applies no expired filter, the same as the cells map', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    actingAsAdmin();

    $expired = Product::factory()->create(['name' => 'A expired']);
    Pallet::factory()->create(['product_id' => $expired->id, 'expiration_date' => '2026-08-01']);

    $notExpired = Product::factory()->create(['name' => 'B not expired']);
    Pallet::factory()->create(['product_id' => $notExpired->id, 'expiration_date' => '2026-09-01']);

    $response = $this->get('/admin/products?expired=false');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)
            ->where('products.data.0.id', $expired->id)
            ->where('products.data.0.full_cells_count', 1)
            ->where('products.data.1.id', $notExpired->id)
            ->where('products.data.1.full_cells_count', 1)
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

test('the inactive filter narrows the products index to the store admin\'s deactivated products that still occupy the warehouse, excluding a deactivated product with no pallets', function () {
    actingAsAdmin();

    // Deactivated and still occupying a cell — the only case the filter matches.
    $inactiveInWarehouse = Product::factory()->inactive()->create();
    Pallet::factory()->create(['product_id' => $inactiveInWarehouse->id]);

    // Noise: deactivated but never stocked — must NOT be matched, since it
    // isn't actionable warehouse-side (nothing there to pull or reconcile).
    Product::factory()->inactive()->create();

    // Noise: active but with zero pallets.
    Product::factory()->create();

    // Noise: active product that occupies a cell.
    $activeWithPallet = Product::factory()->create();
    Pallet::factory()->create(['product_id' => $activeWithPallet->id]);

    $response = $this->get('/admin/products?inactive=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $inactiveInWarehouse->id)
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

test('the products index includes inactive products by default, flagging each row\'s active state', function () {
    actingAsAdmin();

    $active = Product::factory()->create(['name' => 'Alpha Widgets']);
    $inactive = Product::factory()->inactive()->create(['name' => 'Zulu Widgets']);

    $response = $this->get('/admin/products');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)
            ->where('products.data.0.id', $active->id)
            ->where('products.data.0.active', true)
            ->where('products.data.1.id', $inactive->id)
            ->where('products.data.1.active', false)
    );
});

test('the hide_inactive filter excludes inactive products, keeping active ones, and is echoed back in the filters', function () {
    actingAsAdmin();

    $active = Product::factory()->create();
    Product::factory()->inactive()->count(2)->create();

    $response = $this->get('/admin/products?hide_inactive=true');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $active->id)
            ->where('filters.hide_inactive', true)
    );
});

test('the hide_inactive filter rejects a non-boolean value', function () {
    actingAsAdmin();

    $this->get('/admin/products?hide_inactive=maybe')->assertSessionHasErrors('hide_inactive');
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

    $response = $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 10]);

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

    $response = $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => null]);

    $response->assertRedirect(route('admin.products.index'));
    expect($product->fresh()->minimum_pallets)->toBeNull();
});

test('setting a minimum pallets threshold redirects back with the current page and filters preserved', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->put("/admin/products/{$product->id}/minimum-pallets?page=2&inactive=1", ['minimum_pallets' => 10]);

    $response->assertRedirect(route('admin.products.index', ['page' => 2, 'inactive' => 1]));
});

test('setting a minimum pallets threshold creates the settings row for a product that has never had one, without dropping its box count to the database default', function () {
    actingAsAdmin();

    // The store can add a product at any time without this app knowing.
    $product = Product::factory()->unconfigured()->create();
    expect($product->fresh()->boxes_count)->toBe(Product::DEFAULT_BOXES_COUNT);

    $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 15]);

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

    $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 15]);

    expect($product->fresh())
        ->minimum_pallets->toBe(15)
        ->boxes_count->toBe(24);
});

test('the minimum pallets threshold, when sent, must be a whole number of at least one', function () {
    actingAsAdmin();

    $product = Product::factory()->minimumPallets(6)->create();

    foreach ([0, -3, 'many'] as $invalid) {
        $response = $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => $invalid]);

        $response->assertSessionHasErrors('minimum_pallets');
    }

    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('omitting the minimum_pallets field entirely is rejected, unlike sending an explicit null', function () {
    actingAsAdmin();

    $product = Product::factory()->minimumPallets(6)->create();

    $response = $this->put("/admin/products/{$product->id}/minimum-pallets", []);

    $response->assertSessionHasErrors('minimum_pallets');
    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('a mobile app user cannot set a minimum pallets threshold', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->minimumPallets(6)->create();

    $response = $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 10]);

    $response->assertForbidden();
    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('an unauthenticated caller cannot set a minimum pallets threshold', function () {
    $product = Product::factory()->minimumPallets(6)->create();

    $response = $this->put("/admin/products/{$product->id}/minimum-pallets", ['minimum_pallets' => 10]);

    $response->assertRedirect(route('login'));
    expect($product->fresh()->minimum_pallets)->toBe(6);
});

test('setting a minimum pallets threshold for a non-existent product returns a 404', function () {
    actingAsAdmin();

    $response = $this->put('/admin/products/999999/minimum-pallets', ['minimum_pallets' => 10]);

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

test('product name and material id sorting applies to the full filtered result set across pages', function (string $sortBy, string $direction) {
    actingAsAdmin();

    $products = collect(range(1, 25))->map(fn (int $index) => Product::factory()->create([
        'name' => sprintf('Widget %02d', 26 - $index),
    ]));
    $excluded = Product::factory()->inactive()->create(['name' => 'AAA excluded']);
    $expected = $direction === 'asc'
        ? $products->sortBy($sortBy)->values()
        : $products->sortByDesc($sortBy)->values();

    foreach ([1, 2, 3] as $pageNumber) {
        $this->get(route('admin.products.index', [
            'sort_by' => $sortBy, 'sort_direction' => $direction,
            'hide_inactive' => true, 'per_page' => 10, 'page' => $pageNumber,
        ]))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('products.meta.total', 25)
                ->where('products.meta.current_page', $pageNumber)
                ->where('products.data', fn ($rows) => collect($rows)->pluck('id')->all() === $expected->slice(($pageNumber - 1) * 10, 10)->pluck('id')->all())
                ->where('filters.sort_by', $sortBy)
                ->where('filters.sort_direction', $direction)
                ->where('filters.hide_inactive', true)
        );
    }

    expect($expected->pluck('id'))->not->toContain($excluded->id);
})->with([
    ['name', 'asc'], ['name', 'desc'], ['id', 'asc'], ['id', 'desc'],
]);

test('product name sorting follows the panel language and falls back to the displayed English name', function (string $locale, string $direction) {
    actingAsAdmin();
    $apple = Product::factory()->create(['name' => 'Apple', 'ar_name' => 'تفاح']);
    $orange = Product::factory()->create(['name' => 'Orange', 'ar_name' => 'برتقال']);
    $untranslated = Product::factory()->create(['name' => 'Zebra', 'ar_name' => '']);
    $ids = $locale === 'ar'
        ? [$untranslated->id, $orange->id, $apple->id]
        : [$apple->id, $orange->id, $untranslated->id];
    if ($direction === 'desc') {
        $ids = array_reverse($ids);
    }

    $this->withSession(['locale' => $locale])
        ->get('/admin/products?sort_by=name&sort_direction='.$direction)
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('products.data', fn ($rows) => collect($rows)->pluck('id')->all() === $ids)
        ->where('filters.sort_by', 'name'));

    if ($direction === 'asc') {
        $this->get('/admin/products')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data', fn ($rows) => collect($rows)->pluck('id')->all() === $ids));
    }
})->with([
    ['en', 'asc'], ['en', 'desc'], ['ar', 'asc'], ['ar', 'desc'],
]);

test('product name sorting uses material id to break ties consistently', function (string $direction) {
    actingAsAdmin();
    $first = Product::factory()->create(['name' => 'Duplicate']);
    $second = Product::factory()->create(['name' => 'Duplicate']);
    Product::factory()->create(['name' => $direction === 'asc' ? 'Zulu' : 'Alpha']);
    $ids = $direction === 'asc' ? [$first->id, $second->id] : [$second->id, $first->id];

    $this->get('/admin/products?sort_by=name&sort_direction='.$direction)
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('products.data.0.id', $ids[0])
        ->where('products.data.1.id', $ids[1]));
})->with(['asc', 'desc']);

test('product sorting rejects unsupported columns and directions', function () {
    actingAsAdmin();
    $this->getJson('/admin/products?sort_by=published&sort_direction=sideways')
        ->assertUnprocessable()->assertJsonValidationErrors(['sort_by', 'sort_direction']);
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
        'active' => true,
    ]);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Gadget');
    expect($otherProduct->id)->not->toBeNull();
});

test('the product search includes deactivated products the warehouse still holds and flags their active state', function () {
    actingAsAdmin();

    $active = Product::factory()->create(['name' => 'Widget Blue']);
    $inactive = Product::factory()->inactive()->create(['name' => 'Widget Red']);
    Pallet::factory()->create(['product_id' => $inactive->id]);
    // Noise: a product that doesn't match the search term must not be returned.
    $unrelated = Product::factory()->inactive()->create(['name' => 'Gadget']);
    Pallet::factory()->create(['product_id' => $unrelated->id]);

    $response = $this->getJson('/admin/products/search?q=Widget');

    $response->assertOk();
    $rows = collect($response->json('data'));
    expect($rows->pluck('id')->all())->toEqualCanonicalizing([$active->id, $inactive->id]);
    expect($rows->firstWhere('id', $active->id)['active'])->toBeTrue();
    expect($rows->firstWhere('id', $inactive->id)['active'])->toBeFalse();
    expect($rows->pluck('id'))->not->toContain($unrelated->id);
});

test('the product search excludes a deactivated product the warehouse no longer holds, keeping active products with or without pallets', function () {
    actingAsAdmin();

    $activeWithoutPallets = Product::factory()->create(['name' => 'Widget Blue']);
    $activeWithPallet = Product::factory()->create(['name' => 'Widget Green']);
    Pallet::factory()->create(['product_id' => $activeWithPallet->id]);
    $inactiveInWarehouse = Product::factory()->inactive()->create(['name' => 'Widget Red']);
    Pallet::factory()->create(['product_id' => $inactiveInWarehouse->id]);
    $inactiveElsewhere = Product::factory()->inactive()->create(['name' => 'Widget Grey']);

    $response = $this->getJson('/admin/products/search');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toEqualCanonicalizing([$activeWithoutPallets->id, $activeWithPallet->id, $inactiveInWarehouse->id])
        ->not->toContain($inactiveElsewhere->id);
    expect($response->json('meta.total'))->toBe(3);
});

test('the product search spans every deactivated product too when asked to include inactive ones', function () {
    actingAsAdmin();

    $active = Product::factory()->create(['name' => 'Widget Blue']);
    $inactiveInWarehouse = Product::factory()->inactive()->create(['name' => 'Widget Red']);
    Pallet::factory()->create(['product_id' => $inactiveInWarehouse->id]);
    $inactiveElsewhere = Product::factory()->inactive()->create(['name' => 'Widget Grey']);
    // Noise: a product that doesn't match the search term.
    Product::factory()->inactive()->create(['name' => 'Gadget']);

    $response = $this->getJson('/admin/products/search?q=Widget&include_inactive=1');

    $response->assertOk();
    $rows = collect($response->json('data'));
    expect($rows->pluck('id')->all())
        ->toEqualCanonicalizing([$active->id, $inactiveInWarehouse->id, $inactiveElsewhere->id]);
    expect($rows->firstWhere('id', $inactiveElsewhere->id)['active'])->toBeFalse();
});

test('the product search rejects a non-boolean include_inactive', function () {
    actingAsAdmin();

    $this->getJson('/admin/products/search?include_inactive=maybe')->assertUnprocessable();
});

test('the products index still lists a deactivated product with no pallets that the product search hides', function () {
    actingAsAdmin();

    $inactiveElsewhere = Product::factory()->inactive()->create(['name' => 'Widget Grey']);
    // Noise: an active product both endpoints return.
    $active = Product::factory()->create(['name' => 'Widget Blue']);

    $this->get('/admin/products')->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('products.data', 2)
            ->where('products.data.0.id', $active->id)
            ->where('products.data.1.id', $inactiveElsewhere->id)
            ->where('products.data.1.active', false)
    );

    expect(collect($this->getJson('/admin/products/search')->json('data'))->pluck('id')->all())
        ->toBe([$active->id]);
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
        'active' => true,
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
        'active' => true,
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

test('the product search keeps the caller\'s query string on its pagination links', function () {
    actingAsAdmin();

    Product::factory()->count(25)->sequence(fn ($sequence) => ['name' => "Widget {$sequence->index}"])->create();
    // Noise: a product the search excludes must not count towards the second page.
    Product::factory()->create(['name' => 'Unrelated Gadgets']);

    assertNextLinkKeepsQuery($this->getJson('/admin/products/search?q=Widget'), 'q=Widget');
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
    expect($response->json('data.0'))->toEqual(['id' => $matching->id, 'name' => 'Widgets', 'ar_name' => 'ودجات', 'active' => true]);
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

test('the product search matches a product by its numeric id, excluding a non-matching product', function () {
    actingAsAdmin();

    $matching = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات', 'published' => true]);
    Product::factory()->create(['name' => 'Unrelated Gadgets', 'ar_name' => 'أدوات', 'published' => true]);

    $response = $this->getJson('/admin/products/search?q='.$matching->id);

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

    $response = $this->put("/admin/products/{$product->id}/box-count", ['boxes_count' => 30]);

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

    $response = $this->put("/admin/products/{$product->id}/box-count?page=2&inactive=1", ['boxes_count' => 30]);

    $response->assertRedirect(route('admin.products.index', ['page' => 2, 'inactive' => 1]));
});

test('setting a box count creates the settings row for a product that has never had one', function () {
    actingAsAdmin();

    // The store can add a product at any time without this app knowing.
    $product = Product::factory()->unconfigured()->create();
    expect($product->fresh()->boxes_count)->toBe(Product::DEFAULT_BOXES_COUNT);

    $this->put("/admin/products/{$product->id}/box-count", ['boxes_count' => 15]);

    expect($product->fresh()->boxes_count)->toBe(15);
    $this->assertDatabaseCount('wms_product_settings', 1);
});

test('the box count must be a whole number of at least one', function () {
    actingAsAdmin();

    $product = Product::factory()->boxesCount(6)->create();

    foreach ([0, -3, 'many'] as $invalid) {
        $response = $this->put("/admin/products/{$product->id}/box-count", ['boxes_count' => $invalid]);

        $response->assertSessionHasErrors('boxes_count');
    }

    $response = $this->put("/admin/products/{$product->id}/box-count", []);
    $response->assertSessionHasErrors('boxes_count');

    expect($product->fresh()->boxes_count)->toBe(6);
});

test('a mobile app user cannot set a box count', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->boxesCount(6)->create();

    $response = $this->put("/admin/products/{$product->id}/box-count", ['boxes_count' => 30]);

    $response->assertForbidden();
    expect($product->fresh()->boxes_count)->toBe(6);
});

test('an unauthenticated caller cannot set a box count', function () {
    $product = Product::factory()->boxesCount(6)->create();

    $response = $this->put("/admin/products/{$product->id}/box-count", ['boxes_count' => 30]);

    $response->assertRedirect(route('login'));
    expect($product->fresh()->boxes_count)->toBe(6);
});

test('setting a box count for a non-existent product returns a 404', function () {
    actingAsAdmin();

    $response = $this->put('/admin/products/999999/box-count', ['boxes_count' => 30]);

    $response->assertNotFound();
});

test('an authenticated admin can add a barcode to a product', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '1234567890123']);

    $response->assertRedirect(route('admin.products.index'));
    $this->assertDatabaseHas('wms_product_barcodes', [
        'product_id' => $product->id,
        'barcode' => '1234567890123',
    ]);
});

test('adding a barcode trims surrounding whitespace', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '  1234567890123  ']);

    $this->assertDatabaseHas('wms_product_barcodes', [
        'product_id' => $product->id,
        'barcode' => '1234567890123',
    ]);
});

test('adding a barcode redirects back with the current page and filters preserved', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes?page=2&inactive=1", ['barcode' => '1234567890123']);

    $response->assertRedirect(route('admin.products.index', ['page' => 2, 'inactive' => 1]));
});

test('a product can have more than one barcode', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '1234567890123']);
    $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '9876543210987']);

    expect($product->fresh()->barcodes()->pluck('barcode')->sort()->values()->all())
        ->toBe(['1234567890123', '9876543210987']);
});

test('a barcode is required and cannot be blank', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '']);

    $response->assertSessionHasErrors('barcode');
    $this->assertDatabaseCount('wms_product_barcodes', 0);
});

test('a barcode is checked for uniqueness after trimming surrounding whitespace', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1234567890123']);

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '  1234567890123 ']);

    $response->assertSessionHasErrors('barcode');
    $this->assertDatabaseCount('wms_product_barcodes', 1);
});

test('a barcode must be unique across every product, not just the one it is being added to', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $otherProduct->id, 'barcode' => '1234567890123']);

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '1234567890123']);

    $response->assertSessionHasErrors('barcode');
    $this->assertDatabaseCount('wms_product_barcodes', 1);
});

test('a barcode cannot equal the id of another product', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => (string) $otherProduct->id]);

    $response->assertSessionHasErrors(['barcode' => __('messages.barcode_is_product_id')]);
    $this->assertDatabaseCount('wms_product_barcodes', 0);
});

test('a barcode cannot equal the product\'s own id either, since that is its default barcode', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => " {$product->id} "]);

    $response->assertSessionHasErrors('barcode');
    $this->assertDatabaseCount('wms_product_barcodes', 0);
});

test('a numeric barcode that is not the id of any product can be added', function (string $barcode) {
    actingAsAdmin();

    $product = Product::factory()->create();

    $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => $barcode])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('wms_product_barcodes', ['product_id' => $product->id, 'barcode' => $barcode]);
})->with([
    'no product has this id' => ['99999999'],
    'leading zeros are not the canonical id' => ['00001'],
    'a longer code that merely starts with an id' => ['1234567890123'],
]);

test('a mobile app user cannot add a barcode', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '1234567890123']);

    $response->assertForbidden();
    $this->assertDatabaseCount('wms_product_barcodes', 0);
});

test('an unauthenticated caller cannot add a barcode', function () {
    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => '1234567890123']);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('wms_product_barcodes', 0);
});

test('adding a barcode to a non-existent product returns a 404', function () {
    actingAsAdmin();

    $response = $this->post('/admin/products/999999/barcodes', ['barcode' => '1234567890123']);

    $response->assertNotFound();
});

test('an authenticated admin can remove a barcode from a product', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $response = $this->delete("/admin/products/{$product->id}/barcodes/{$barcode->id}");

    $response->assertRedirect(route('admin.products.index'));
    $this->assertDatabaseMissing('wms_product_barcodes', ['id' => $barcode->id]);
});

test('removing a barcode redirects back with the current page and filters preserved', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $response = $this->delete("/admin/products/{$product->id}/barcodes/{$barcode->id}?page=2&inactive=1");

    $response->assertRedirect(route('admin.products.index', ['page' => 2, 'inactive' => 1]));
});

test('removing a barcode that belongs to a different product returns a 404, leaving it intact', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $otherProduct->id]);

    $response = $this->delete("/admin/products/{$product->id}/barcodes/{$barcode->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('wms_product_barcodes', ['id' => $barcode->id, 'product_id' => $otherProduct->id]);
});

test('a mobile app user cannot remove a barcode', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $response = $this->delete("/admin/products/{$product->id}/barcodes/{$barcode->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('wms_product_barcodes', ['id' => $barcode->id]);
});

test('an unauthenticated caller cannot remove a barcode', function () {
    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $response = $this->delete("/admin/products/{$product->id}/barcodes/{$barcode->id}");

    $response->assertRedirect(route('login'));
    $this->assertDatabaseHas('wms_product_barcodes', ['id' => $barcode->id]);
});

test('removing a non-existent barcode returns a 404', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->delete("/admin/products/{$product->id}/barcodes/999999");

    $response->assertNotFound();
});

test('an authenticated admin can export a Code 128 barcode label for a product, with only the active locale name', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['id' => 1, 'name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->get("/admin/products/{$product->id}/export-qr");

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml');
    $response->assertHeader('content-disposition', "attachment; filename=\"product-{$product->id}-barcode.svg\"");

    $svg = $response->getContent();
    // Code 128-B of "1": start B (211214), '1' (123122), checksum
    // (104 + 17) % 103 = 18 (223211), stop (2331112) — 13 bars in total —
    // drawn as one path of 13 subpaths, with no QR matrix left behind.
    expect(substr_count($svg, 'M'))->toBe(13)
        ->and($svg)->toContain('<path fill="#000000" d="M')
        ->and($svg)->not->toContain('fill-rule="evenodd"')
        ->and($svg)->not->toContain('<image');
    expect($svg)->toContain('Widgets');
    expect($svg)->not->toContain('ودجات');
    expect($svg)->toContain(">{$product->id}</text>")
        ->and($svg)->not->toContain('ID:')
        ->and($svg)->not->toContain('المعرف');
    // One <text> for the id, one for the (short, unwrapped) name.
    expect(substr_count($svg, '<text'))->toBe(2);
});

test('a product barcode label shows the Arabic name only when the locale is Arabic', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/products/{$product->id}/export-qr");

    $svg = $response->getContent();
    expect($svg)->toContain('ودجات')
        ->and($svg)->not->toContain('Widgets')
        ->and($svg)->toContain('direction="rtl"');
});

test('a product barcode label falls back to the base name when the Arabic locale has no translation', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => '']);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/products/{$product->id}/export-qr");

    expect($response->getContent())->toContain('Widgets');
});

test('a product barcode label draws the id and name in enlarged type', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => '']);

    $svg = $this->get("/admin/products/{$product->id}/export-qr")->getContent();

    expect($svg)->toMatch('/<text[^>]*font-size="34"[^>]*>'.$product->id.'<\/text>/')
        ->and($svg)->toMatch('/<text[^>]*font-size="28"[^>]*>Widgets<\/text>/');
});

test('a product barcode export uses the configured width instead of the default', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 400, 'qr_code_height' => 500]);
    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => '']);

    $svg = $this->get("/admin/products/{$product->id}/export-qr")->getContent();

    expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="400"')
        ->and($svg)->toContain('<g transform="translate(20,20)">');
});

test('a product with a long name has its barcode label text wrapped, up to the configured height', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 280, 'qr_code_height' => 380]);

    $longName = trim(str_repeat('Widget Component ', 11));
    $product = Product::factory()->create(['name' => $longName, 'ar_name' => '']);

    $svg = $this->get("/admin/products/{$product->id}/export-qr")->getContent();

    expect(substr_count($svg, '<text'))->toBeGreaterThan(2);
    expect($svg)->toContain('…');
    // The id is drawn and budget-clamped before the name, so truncating a
    // long name never costs the id its own line.
    expect($svg)->toContain(">{$product->id}</text>");

    preg_match('/<svg[^>]*height="(\d+)"/', $svg, $matches);
    expect((int) $matches[1])->toBeLessThanOrEqual(380);
});

test('a product barcode label has no id label word in either locale', function (string $locale) {
    actingAsAdmin();

    $product = Product::factory()->create(['id' => 7, 'name' => 'Widgets', 'ar_name' => 'ودجات']);

    $svg = $this->withSession(['locale' => $locale])->get("/admin/products/{$product->id}/export-qr")->getContent();

    expect($svg)->toContain('>7</text>')
        ->and($svg)->not->toContain('ID:')
        ->and($svg)->not->toContain('المعرف');
})->with(['en', 'ar']);

test('a very long product name fills the configured height, ends in an ellipsis and never overlaps the id', function (string $name) {
    actingAsAdmin();
    $product = Product::factory()->create(['name' => $name, 'ar_name' => '']);

    $nameLineYs = function (int $height) use ($product): array {
        Setting::query()->delete();
        Setting::factory()->create(['qr_code_width' => 280, 'qr_code_height' => $height]);

        $svg = $this->get("/admin/products/{$product->id}/export-qr")->getContent();

        preg_match_all('/<text[^>]*y="(\d+)"[^>]*font-size="(\d+)"[^>]*>([^<]*)</', $svg, $matches, PREG_SET_ORDER);
        preg_match('/<svg[^>]*height="(\d+)"/', $svg, $svgHeight);

        return [
            'idY' => (int) $matches[0][1],
            'ys' => array_map(fn (array $m): int => (int) $m[1], array_slice($matches, 1)),
            'last' => end($matches)[3],
            'height' => (int) $svgHeight[1],
        ];
    };

    $tall = $nameLineYs(900);
    $short = $nameLineYs(500);

    expect($tall['ys'])->not->toBeEmpty()
        // More than two lines: the name spans the available space.
        ->and(count($tall['ys']))->toBeGreaterThan(2)
        ->and(count($tall['ys']))->toBeGreaterThan(count($short['ys']))
        ->and($tall['last'])->toEndWith('…')
        ->and($short['last'])->toEndWith('…')
        ->and($tall['height'])->toBeLessThanOrEqual(900)
        ->and($short['height'])->toBeLessThanOrEqual(500)
        // The first name baseline sits clear of the id's baseline by more
        // than the name's ascent, so the two blocks cannot collide.
        ->and($tall['ys'][0] - $tall['idY'])->toBeGreaterThan(28);
})->with([
    'with spaces' => [str_repeat('Widget Component ', 60)],
    'no spaces' => [str_repeat('W', 1500)],
    'arabic' => [str_repeat('مكون ودجات ', 60)],
]);

test('an Arabic product name wraps by characters, not bytes', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 360, 'qr_code_height' => 1000]);

    $product = Product::factory()->create(['id' => 104001, 'name' => 'x', 'ar_name' => 'العائلة جريش الكبة 5كغ ايراني']);

    $svg = $this->withSession(['locale' => 'ar'])->get("/admin/products/{$product->id}/export-qr")->getContent();

    preg_match_all('/font-size="28"[^>]*>([^<]*)</u', $svg, $matches);

    // 20 characters fit a line: "العائلة جريش الكبة" (18) is one line, not three.
    expect($matches[1])->toBe(['العائلة جريش الكبة', '5كغ ايراني']);
});

test('a multi-digit product id encodes to a longer barcode', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['id' => 4821, 'name' => 'Widgets', 'ar_name' => '']);

    $svg = $this->get("/admin/products/{$product->id}/export-qr")->getContent();

    // start + 4 digits + checksum = 6 symbols x 3 bars, + the 4-bar stop.
    expect(substr_count($svg, 'M'))->toBe(22);
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

/**
 * The files of a downloaded barcode ZIP, keyed by name.
 *
 * @return array<string, string>
 */
function barcodeZipEntries(string $zipContent): array
{
    $path = tempnam(sys_get_temp_dir(), 'barcode-test');
    file_put_contents($path, $zipContent);
    $zip = new ZipArchive;
    $zip->open($path);
    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
    }

    $zip->close();
    unlink($path);

    return $entries;
}

test('an admin can download one barcode of a product as an SVG carrying exactly that barcode', function () {
    actingAsAdmin();

    $product = Product::factory()->create(['name' => 'Widgets']);
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'ABC-12345']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'OTHER-999']);

    $response = $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    $response->assertHeader('Content-Disposition', "attachment; filename=\"product-{$product->id}-barcode-{$barcode->id}-ABC-12345.svg\"");
    expect($response->getContent())
        ->toContain('>ABC-12345</text>')
        ->toContain('Widgets')
        ->not->toContain('OTHER-999');
});

test('a downloaded barcode is encoded as the same Code 128 bars the product label uses for that value', function () {
    actingAsAdmin();

    // A barcode equal to the product id must render bar for bar like the existing
    // product-id export, which is already verified scannable.
    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => (string) $product->id]);

    $download = $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download")->getContent();
    $export = $this->get("/admin/products/{$product->id}/export-qr")->getContent();

    preg_match('/<path fill="#000000" d="([^"]+)"/', $download, $downloadBars);
    preg_match('/<path fill="#000000" d="([^"]+)"/', $export, $exportBars);

    expect($downloadBars[1])->not->toBeEmpty()->toBe($exportBars[1]);
});

test('a long barcode widens the label instead of shrinking its bars below a scannable width', function () {
    actingAsAdmin();
    Setting::current()->update(['qr_code_width' => 300]);

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => str_repeat('9', 40)]);

    $svg = $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download")->getContent();

    preg_match('/<svg [^>]*width="(\d+)"/', $svg, $width);
    // 40 digits is 11 modules each plus start, checksum, stop and quiet zones — far more than 300px at 2px a module.
    expect((int) $width[1])->toBeGreaterThan(300);
});

test('a barcode that is not printable ASCII cannot be downloaded as a label', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'باركود']);

    $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download")->assertUnprocessable();
});

test('downloading another products barcode through this product returns a 404', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $otherBarcode = ProductBarcode::factory()->create(['barcode' => 'OTHER-999']);

    $this->get("/admin/products/{$product->id}/barcodes/{$otherBarcode->id}/download")->assertNotFound();
});

test('an admin can download every barcode of a product, and only that product\'s, as a ZIP', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $first = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1111111111111']);
    $second = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '2222222222222']);
    $third = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'ABC/def 3']);
    ProductBarcode::factory()->create(['product_id' => Product::factory()->create()->id, 'barcode' => '9999999999999']);

    $response = $this->get("/admin/products/{$product->id}/barcodes/download");

    $response->assertOk();
    $response->assertDownload("product-{$product->id}-barcodes.zip");
    $entries = barcodeZipEntries($response->baseResponse->getFile()->getContent());

    expect(array_keys($entries))->toBe([
        "product-{$product->id}-barcode.svg",
        "product-{$product->id}-barcode-{$first->id}-1111111111111.svg",
        "product-{$product->id}-barcode-{$second->id}-2222222222222.svg",
        "product-{$product->id}-barcode-{$third->id}-ABC_def_3.svg",
    ])
        ->and($entries["product-{$product->id}-barcode-{$first->id}-1111111111111.svg"])->toContain('>1111111111111</text>')
        ->and($entries["product-{$product->id}-barcode-{$third->id}-ABC_def_3.svg"])->toContain('>ABC/def 3</text>')
        ->and(implode('', $entries))->not->toContain('9999999999999');
});

test('downloading all barcodes of a product without custom ones still yields its default barcode', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => Product::factory()->create()->id, 'barcode' => '9999999999999']);

    $response = $this->get("/admin/products/{$product->id}/barcodes/download");

    $response->assertOk();
    $entries = barcodeZipEntries($response->baseResponse->getFile()->getContent());

    expect(array_keys($entries))->toBe(["product-{$product->id}-barcode.svg"])
        ->and(implode('', $entries))->not->toContain('9999999999999');
});

test('one barcode that cannot be rendered fails the whole ZIP instead of omitting it', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1111111111111']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'باركود']);

    $this->get("/admin/products/{$product->id}/barcodes/download")->assertUnprocessable();
});

test('downloading all barcodes of a non-existent product returns a 404', function () {
    actingAsAdmin();

    $this->get('/admin/products/999999/barcodes/download')->assertNotFound();
});

test('a barcode label can be previewed inline as the same SVG that is downloaded', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1234567890123']);

    $response = $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/preview");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'inline');
    expect($response->getContent())
        ->toBe($this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download")->getContent());
});

test('the default barcode can be previewed inline as the same SVG that is exported', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->get("/admin/products/{$product->id}/export-qr/preview");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'inline');
    expect($response->getContent())->toBe($this->get("/admin/products/{$product->id}/export-qr")->getContent());

    $etag = $response->headers->get('ETag');
    $this->get("/admin/products/{$product->id}/export-qr/preview", ['If-None-Match' => $etag])->assertNotModified();
});

test('mobile app and unauthenticated users cannot preview the default barcode, and a missing product is a 404', function () {
    $product = Product::factory()->create();
    $url = "/admin/products/{$product->id}/export-qr/preview";

    $this->get($url)->assertRedirect(route('login'));

    actingAsMobilePanelUser();
    $this->get($url)->assertForbidden();

    actingAsAdmin();
    $this->get('/admin/products/999999/export-qr/preview')->assertNotFound();
});

test('a barcode preview answers a repeat request that already holds the current label with a 304', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '1234567890123']);
    $url = "/admin/products/{$product->id}/barcodes/{$barcode->id}/preview";

    $etag = $this->get($url)->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->get($url, ['If-None-Match' => $etag])->assertNotModified();

    $product->update(['name' => 'Renamed product']);

    $this->get($url, ['If-None-Match' => $etag])->assertOk();
});

test('previewing a barcode that belongs to another product returns a 404', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $otherBarcode = ProductBarcode::factory()->create();

    $this->get("/admin/products/{$product->id}/barcodes/{$otherBarcode->id}/preview")->assertNotFound();
});

test('a barcode that cannot be rendered as Code 128 cannot be previewed', function () {
    actingAsAdmin();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'باركود']);

    $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/preview")->assertUnprocessable();
});

test('mobile app and unauthenticated users cannot preview a barcode', function () {
    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);
    $url = "/admin/products/{$product->id}/barcodes/{$barcode->id}/preview";

    $this->get($url)->assertRedirect(route('login'));

    actingAsMobilePanelUser();
    $this->get($url)->assertForbidden();
});

test('a mobile app user cannot download barcodes', function () {
    actingAsMobilePanelUser();

    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download")->assertForbidden();
    $this->get("/admin/products/{$product->id}/barcodes/download")->assertForbidden();
});

test('an unauthenticated caller cannot download barcodes', function () {
    $product = Product::factory()->create();
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id]);

    $this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/download")->assertRedirect(route('login'));
    $this->get("/admin/products/{$product->id}/barcodes/download")->assertRedirect(route('login'));
});

test('a barcode must be printable ASCII to be added', function () {
    actingAsAdmin();

    $product = Product::factory()->create();

    $response = $this->post("/admin/products/{$product->id}/barcodes", ['barcode' => 'باركود']);

    $response->assertSessionHasErrors('barcode');
    $this->assertDatabaseCount('wms_product_barcodes', 0);
});

test('the product search answers a repeat request that already holds the current results with a 304', function () {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Widget']);

    $etag = $this->getJson('/admin/products/search?q=Widget')->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->getJson('/admin/products/search?q=Widget', ['If-None-Match' => $etag])->assertNotModified();
});

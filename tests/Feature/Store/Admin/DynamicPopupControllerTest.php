<?php

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\DynamicPopup;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{offer: Offer, slug: string} */
function popupOfferWithBaseProduct(): array
{
    $product = createStoreProduct();
    $offer = Offer::forceCreate(['title' => 'Spring offer']);
    OfferProduct::forceCreate(['offer_id' => $offer->id, 'product_id' => $product->id, 'is_base_product' => 1]);

    return ['offer' => $offer, 'slug' => $product->slug];
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function adminPopupForm(int $offerId, array $overrides = []): array
{
    return [
        'title' => 'Welcome', 'summary' => 'Take a look',
        'banner' => (string) Upload::factory()->create(['type' => 'image', 'user_id' => auth('store')->id()])->id,
        'delay_seconds' => 5, 'btn_text' => 'Open', 'offer_id' => $offerId, 'show_page' => 0,
        ...$overrides,
    ];
}

function adminDynamicPopup(string $title = 'Welcome'): DynamicPopup
{
    return DynamicPopup::forceCreate(['title' => $title, 'summary' => 'Hello', 'banner' => null, 'status' => 0, 'btn_link' => '/temp/product/example', 'btn_text' => 'Open', 'delay_seconds' => 5, 'offer_id' => null, 'show_page' => 0]);
}

test('the dynamic popup list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_all_dynamic_popups'));
    $this->get(route('dynamic-popups.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Marketing/DynamicPopups/Index'));
});

test('popup list returns only its complete narrow summary and searches stored text', function () {
    $popup = adminDynamicPopup('مرحبا');
    adminDynamicPopup('Other');
    actingAsStoreUser(createStoreStaff('view_all_dynamic_popups'));
    $this->get(route('dynamic-popups.index', ['search' => 'مرحبا']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['search' => 'مرحبا', 'per_page' => 20])->has('popups.data', 1)
        ->has('popups.data.0', fn (Assert $row) => $row->where('id', $popup->id)->where('title', 'مرحبا')->where('banner_url', uploaded_asset(null))->where('btn_link', '/temp/product/example')->where('status', false)));
});

test('popups paginate and validate page size and search', function () {
    foreach (range(1, 23) as $number) {
        adminDynamicPopup('Popup '.$number);
    }
    actingAsStoreUser(createStoreStaff('view_all_dynamic_popups'));
    assertPagesWithoutOverlap(fn (int $page) => $this->get(route('dynamic-popups.index', ['page' => $page])), fn ($response) => array_column($response->inertiaProps('popups.data'), 'id'), 23, 20);
    $this->get(route('dynamic-popups.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('popups.data', 10));
    $this->getJson(route('dynamic-popups.index', ['per_page' => 7]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson(route('dynamic-popups.index', ['search' => str_repeat('a', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('the create popup form can be rendered with only valid narrow offer options', function () {
    ['offer' => $offer] = popupOfferWithBaseProduct();
    Offer::forceCreate(['title' => 'Missing base product']);
    $category = Category::forceCreate(['name' => 'Fruit', 'slug' => 'fruit']);
    actingAsStoreUser(createStoreStaff('add_dynamic_popups'));
    $this->get(route('dynamic-popups.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Marketing/DynamicPopups/Create')->has('offers', 1)
        ->has('offers.0', fn (Assert $row) => $row->where('id', $offer->id)->where('name', 'Spring offer'))
        ->has('categories.0', fn (Assert $row) => $row->where('id', $category->id)->where('name', 'Fruit')));
});

test('the edit popup form can be rendered with a complete narrow payload', function () {
    $popup = adminDynamicPopup();
    actingAsStoreUser(createStoreStaff('edit_dynamic_popups'));
    $this->get(route('dynamic-popups.edit', $popup))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Marketing/DynamicPopups/Edit')->has('popup', fn (Assert $row) => $row
        ->where('id', $popup->id)->where('title', 'Welcome')->where('summary', 'Hello')->where('banner', '')
        ->where('btn_text', 'Open')->where('delay_seconds', 5)->where('offer_id', null)->where('show_page', 0)));
});

test('storing a popup derives the button columns and ignores forged ones', function () {
    ['offer' => $offer, 'slug' => $slug] = popupOfferWithBaseProduct();
    actingAsStoreUser(createStoreStaff('add_dynamic_popups'));
    $this->post(route('dynamic-popups.store'), adminPopupForm($offer->id, [
        'status' => 1, 'btn_link' => 'https://evil.example', 'btn_background_color' => '#ff0000', 'btn_text_color' => 'pink', 'id' => 999,
    ]))->assertRedirect(route('dynamic-popups.index'));
    $popup = DynamicPopup::firstOrFail();
    expect($popup->id)->not->toBe(999)->and($popup->status)->toBe(0)
        ->and($popup->btn_link)->toBe(route('product', ['slug' => $slug]))
        ->and($popup->btn_background_color)->toBe('#000000')->and($popup->btn_text_color)->toBe('white')->and($popup->title)->toBe('Welcome');
});

test('updating a popup cannot flip its status or button columns through the form', function () {
    ['offer' => $offer, 'slug' => $slug] = popupOfferWithBaseProduct();
    actingAsStoreUser(createStoreStaff('edit_dynamic_popups'));
    $popup = adminDynamicPopup('Old');
    $noise = adminDynamicPopup('Noise');
    $this->put(route('dynamic-popups.update', $popup), adminPopupForm($offer->id, ['title' => 'جديد', 'status' => 1, 'btn_link' => 'https://evil.example']))->assertRedirect(route('dynamic-popups.index'));
    expect($popup->fresh()->title)->toBe('جديد')->and($popup->fresh()->status)->toBe(0)
        ->and($popup->fresh()->btn_link)->toBe(route('product', ['slug' => $slug]))->and($noise->fresh()->title)->toBe('Noise');
});

test('popup writes validate references dimensions and text without changing records', function (string $field, mixed $value) {
    ['offer' => $offer] = popupOfferWithBaseProduct();
    $popup = adminDynamicPopup('Old');
    actingAsStoreUser(createStoreStaff('add_dynamic_popups', 'edit_dynamic_popups'));
    foreach (['store', 'update'] as $action) {
        $data = adminPopupForm($offer->id, [$field => $value]);
        $response = $action === 'store' ? $this->postJson(route('dynamic-popups.store'), $data) : $this->putJson(route('dynamic-popups.update', $popup), $data);
        $response->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(DynamicPopup::count())->toBe(1)->and($popup->fresh()->title)->toBe('Old');
})->with([
    'title required' => ['title', ''], 'title width' => ['title', str_repeat('x', 101)],
    'summary width' => ['summary', str_repeat('x', 192)], 'button width' => ['btn_text', str_repeat('x', 192)],
    'image missing' => ['banner', 999999], 'multiple images' => ['banner', '1,2'],
    'negative delay' => ['delay_seconds', -1], 'long delay' => ['delay_seconds', 101],
    'offer missing' => ['offer_id', 999999], 'page missing' => ['show_page', 999999],
    'negative page' => ['show_page', -1],
    'invalid page shape' => ['show_page', [['bad' => 'input']]],
]);

test('offers without a base product and non-image uploads cannot be selected', function () {
    $offer = Offer::forceCreate(['title' => 'Broken']);
    $file = Upload::factory()->create(['type' => 'document']);
    actingAsStoreUser(createStoreStaff('add_dynamic_popups'));
    $this->postJson(route('dynamic-popups.store'), adminPopupForm($offer->id, ['banner' => $file->id]))->assertUnprocessable()->assertJsonValidationErrors(['banner', 'offer_id']);
    $this->assertDatabaseCount('dynamic_popups', 0);
});

test('popup publication is separate and validates a boolean', function () {
    $popup = adminDynamicPopup();
    $noise = adminDynamicPopup('Other');
    actingAsStoreUser(createStoreStaff('publish_dynamic_popups'));
    $this->post(route('dynamic-popups.update-status', ['page' => 2]), ['id' => $popup->id, 'status' => 1])->assertRedirect(route('dynamic-popups.index', ['page' => 2]));
    expect($popup->fresh()->status)->toBe(1)->and($noise->fresh()->status)->toBe(0);
    $this->postJson(route('dynamic-popups.update-status'), ['id' => $popup->id, 'status' => 2])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson(route('dynamic-popups.update-status'), ['id' => 999999, 'status' => 0])->assertUnprocessable()->assertJsonValidationErrors('id');
    expect($popup->fresh()->status)->toBe(1);
});

test('popup deletion and bulk deletion affect only selected rows and preserve filters', function () {
    $first = adminDynamicPopup('First');
    $second = adminDynamicPopup('Second');
    $noise = adminDynamicPopup('Noise');
    actingAsStoreUser(createStoreStaff('delete_dynamic_popups'));
    $this->delete(route('dynamic-popups.destroy', [$first, 'search' => 'First']))->assertRedirect(route('dynamic-popups.index', ['search' => 'First']));
    $this->post(route('bulk-dynamic-popup-delete', ['page' => 2]), ['id' => [$second->id]])->assertRedirect(route('dynamic-popups.index', ['page' => 2]));
    expect(DynamicPopup::count())->toBe(1)->and($noise->fresh())->not->toBeNull();
    $this->postJson(route('bulk-dynamic-popup-delete'), ['id' => [$noise->id, 999999]])->assertUnprocessable()->assertJsonValidationErrors('id.1');
    $this->postJson(route('bulk-dynamic-popup-delete'), ['id' => []])->assertUnprocessable()->assertJsonValidationErrors('id');
    expect($noise->fresh())->not->toBeNull();
});

test('missing dynamic popups return 404 on edit update and destroy', function (string $action) {
    actingAsStoreUser(createStoreAdmin());
    $response = match ($action) {
        'edit' => $this->get(route('dynamic-popups.edit', 999999)),
        'update' => $this->put(route('dynamic-popups.update', 999999), []),
        'destroy' => $this->delete(route('dynamic-popups.destroy', 999999)),
    };
    $response->assertNotFound();
})->with(['edit', 'update', 'destroy']);

foreach (['index', 'create', 'store', 'edit', 'update', 'destroy', 'bulkDestroy', 'updateStatus'] as $action) {
    test("popup {$action} refuses unauthorized callers and leaves rows unchanged", function (string $caller) use ($action) {
        $popup = adminDynamicPopup('Unchanged');
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'index' => $this->get(route('dynamic-popups.index')),
            'create' => $this->get(route('dynamic-popups.create')),
            'store' => $this->post(route('dynamic-popups.store'), ['title' => 'Forbidden']),
            'edit' => $this->get(route('dynamic-popups.edit', $popup)),
            'update' => $this->put(route('dynamic-popups.update', $popup), ['title' => 'Forbidden']),
            'destroy' => $this->delete(route('dynamic-popups.destroy', $popup)),
            'bulkDestroy' => $this->post(route('bulk-dynamic-popup-delete'), ['id' => [$popup->id]]),
            'updateStatus' => $this->post(route('dynamic-popups.update-status'), ['id' => $popup->id, 'status' => 1]),
        };
        assertStoreCallerRefused($response, $caller);
        expect(DynamicPopup::count())->toBe(1)->and($popup->fresh()->title)->toBe('Unchanged')->and($popup->fresh()->status)->toBe(0);
    })->with('refused store admin callers');
}

test('popup page options use the selected database translation with a stored-name fallback', function () {
    $translated = Category::forceCreate(['name' => 'Fruit', 'slug' => 'fruit']);
    $fallback = Category::forceCreate(['name' => 'Vegetables', 'slug' => 'vegetables']);
    CategoryTranslation::forceCreate(['category_id' => $translated->id, 'lang' => 'ar', 'name' => 'فواكه']);
    CategoryTranslation::forceCreate(['category_id' => $translated->id, 'lang' => 'nl', 'name' => 'Not selected']);
    actingAsStoreUser(createStoreStaff('add_dynamic_popups'));
    $this->withSession(['locale' => 'ar'])->get(route('dynamic-popups.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('categories', [['id' => $translated->id, 'name' => 'فواكه'], ['id' => $fallback->id, 'name' => 'Vegetables']]));
});

test('published popup content and targeting remain server rendered on the public home page', function () {
    ['offer' => $offer] = popupOfferWithBaseProduct();
    $offer->forceFill(['is_active' => 1])->save();
    $visible = adminDynamicPopup('Visible campaign popup');
    $visible->forceFill(['offer_id' => $offer->id, 'status' => 1, 'delay_seconds' => 7, 'btn_text_color' => 'white', 'btn_background_color' => '#000000'])->save();
    $hidden = adminDynamicPopup('Hidden campaign popup');
    $hidden->forceFill(['offer_id' => $offer->id])->save();
    $category = adminDynamicPopup('Category-only campaign popup');
    $category->forceFill(['offer_id' => $offer->id, 'status' => 1, 'show_page' => 999])->save();
    actingAsStoreUser(createStoreUser('customer'));
    $this->get(route('home'))->assertOk()->assertViewIs('shop.frontend.index')
        ->assertSee('Visible campaign popup')->assertDontSee('Hidden campaign popup')->assertDontSee('Category-only campaign popup')
        ->assertSee('data-key="website-popup-'.$visible->id.'"', false)->assertSee('}, 7000)', false);
    $this->withHeader('X-Inertia', 'true')->get(route('home'))->assertConflict()->assertHeader('X-Inertia-Location', route('home'));
});

test('a popup refuses an offer whose base product has no usable canonical slug', function (string $slug) {
    ['offer' => $offer] = popupOfferWithBaseProduct();
    $productId = OfferProduct::query()->where('offer_id', $offer->id)->value('product_id');
    Product::query()->whereKey($productId)->update(['slug' => $slug]);
    actingAsStoreUser(createStoreStaff('add_dynamic_popups', 'edit_dynamic_popups'));
    $popup = adminDynamicPopup('Unchanged');
    $this->get(route('dynamic-popups.create'))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
    $this->postJson(route('dynamic-popups.store'), adminPopupForm($offer->id))->assertUnprocessable()->assertJsonValidationErrors('offer_id');
    $this->putJson(route('dynamic-popups.update', $popup), adminPopupForm($offer->id))->assertUnprocessable()->assertJsonValidationErrors('offer_id');
    expect(DynamicPopup::count())->toBe(1)->and($popup->fresh()->title)->toBe('Unchanged');
})->with(['empty' => '', 'whitespace' => '   ']);

test('popup images resolve through one narrow list query without per-row upload lookups', function () {
    $upload = Upload::factory()->create(['type' => 'image', 'file_name' => 'uploads/popup.webp', 'external_link' => null]);
    foreach (range(1, 3) as $number) {
        adminDynamicPopup('Popup '.$number)->forceFill(['banner' => (string) $upload->id])->save();
    }
    actingAsStoreUser(createStoreStaff('view_all_dynamic_popups'));
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'uploads')) {
            $queries[] = $query->sql;
        }
    });
    $this->get(route('dynamic-popups.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('popups.data', 3)->where('popups.data.0.banner_url', my_asset('uploads/popup.webp')));
    expect($queries)->toHaveCount(2);
    foreach ($queries as $sql) {
        expect($sql)->toContain('join "uploads"')->not->toContain('select *');
    }
});

test('marketing upload references follow the selected picker ownership contract', function () {
    $staff = actingAsStoreUser(createStoreStaff('add_dynamic_popups'));
    ['offer' => $offer] = popupOfferWithBaseProduct();
    $foreign = Upload::factory()->create(['type' => 'image']);
    $payload = adminPopupForm($offer->id, ['banner' => (string) $foreign->id]);
    $this->postJson(route('dynamic-popups.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('banner');
    expect(DynamicPopup::count())->toBe(0);
    $owned = Upload::factory()->create(['type' => 'image', 'user_id' => $staff->id]);
    $this->post(route('dynamic-popups.store'), array_replace($payload, ['banner' => (string) $owned->id]))->assertRedirect(route('dynamic-popups.index'));
    expect(DynamicPopup::sole()->banner)->toBe((string) $owned->id);
});

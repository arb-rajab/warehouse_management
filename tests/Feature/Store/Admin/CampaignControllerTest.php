<?php

use App\Models\Brand;
use App\Models\FlashDeal;
use App\Models\FlashDealProduct;
use App\Models\FlashDealTranslation;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function campaignForm(string $module, array $overrides = []): array
{
    $product = createStoreProduct(['published' => 1, 'approved' => 1, 'discount' => 2, 'discount_type' => 'amount']);
    $fields = ['title' => 'Campaign', 'start_date' => '2026-10-01T08:09:11', 'end_date' => '2026-10-30T09:08:07', 'products' => [['id' => $product->id, 'discount' => 20, 'discount_type' => 'percent']], 'banner' => null];
    if ($module === 'offer_deals') {
        $fields += ['offer_type' => 'products_offer', 'base_product_id' => $product->id, 'base_quantity' => 2];
        $fields['products'][0]['quantity'] = 3;
    } else {
        $fields += ['background_color' => '#ffffff', 'text_color' => 'dark', 'lang' => config('app.default_language')];
    }

    return array_replace($fields, $overrides);
}
function campaignRecord(string $module): Offer|FlashDeal
{
    return $module === 'offer_deals' ? Offer::forceCreate(['title' => 'Existing', 'offer_type' => 'products_offer', 'start_date' => strtotime('2026-10-01T08:09:11'), 'end_date' => strtotime('2026-10-30T09:08:07')]) : FlashDeal::forceCreate(['title' => 'Existing', 'slug' => 'stable-url', 'background_color' => '#ffffff', 'text_color' => 'dark', 'start_date' => strtotime('2026-10-01T08:09:11'), 'end_date' => strtotime('2026-10-30T09:08:07')]);
}
dataset('campaign modules', ['offer_deals', 'flash_deals']);

test('campaign lists have narrow full payloads, search, deterministic pagination and validated page size', function (string $module) {
    $permission = $module === 'offer_deals' ? 'view_all_offer_deals' : 'view_all_flash_deals';
    actingAsStoreUser(createStoreStaff($permission));
    $record = campaignRecord($module);
    $property = $module === 'offer_deals' ? 'offers' : 'deals';
    $component = $module === 'offer_deals' ? 'Offers' : 'FlashDeals';
    $expected = ['id' => $record->id, 'title' => 'Existing', 'banner' => '', 'start_date' => '2026-10-01T08:09:11', 'end_date' => '2026-10-30T09:08:07'];
    $fresh = $record->fresh();
    $expected += $module === 'offer_deals' ? ['offer_type' => 'products_offer', 'brand_id' => null, 'is_active' => (bool) $fresh->is_active, 'is_repeatable' => (bool) $fresh->is_repeatable, 'multiple_usage' => (bool) $fresh->multiple_usage] : ['slug' => 'stable-url', 'background_color' => '#ffffff', 'text_color' => 'dark', 'status' => (bool) $fresh->status, 'featured' => (bool) $fresh->featured, 'public_url' => route('flash-deal-details', 'stable-url')];
    $this->get(route($module.'.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Marketing/'.$component.'/Index')->where('filters', ['search' => null, 'per_page' => 20])->has($property.'.data', 1)->has($property.'.data.0', fn (Assert $row) => $row->whereAll($expected)));
    foreach (range(1, 24) as $i) {
        campaignRecord($module);
    }
    $this->get(route($module.'.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has($property.'.data', 20)->where($property.'.meta.total', 25));
    $first = $this->get(route($module.'.index', ['per_page' => 10]))->assertOk()->inertiaProps($property.'.data');
    $second = $this->get(route($module.'.index', ['per_page' => 10, 'page' => 2]))->assertOk()->inertiaProps($property.'.data');
    expect(array_intersect(array_column($first, 'id'), array_column($second, 'id')))->toBe([]);
    $this->get(route($module.'.index', ['search' => 'Missing']))->assertInertia(fn (Assert $page) => $page->has($property.'.data', 0));
    $this->getJson(route($module.'.index', ['per_page' => 13]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
})->with('campaign modules');

test('campaign forms render and a write stores validated seconds discount rows and banner', function (string $module) {
    actingAsStoreUser(createStoreAdmin());
    $fields = campaignForm($module);
    $upload = Upload::forceCreate(['user_id' => auth('store')->id(), 'type' => 'image', 'file_name' => 'uploads/all/banner.png']);
    $fields['banner'] = $upload->id;
    $this->get(route($module.'.create'))->assertOk();
    $this->post(route($module.'.store'), $fields + ['status' => 1, 'is_active' => 0, 'featured' => 1])->assertRedirect(route($module.'.index'));
    $record = $module === 'offer_deals' ? Offer::query()->firstOrFail() : FlashDeal::query()->firstOrFail();
    expect((int) $record->start_date)->toBe(strtotime($fields['start_date']));
    expect((int) $record->end_date)->toBe(strtotime($fields['end_date']));
    expect((int) $record->banner)->toBe($upload->id);
    if ($module === 'offer_deals') {
        expect($record->offer_products()->count())->toBe(1);
        expect((float) $record->base_product->quantity)->toBe(2.0);
        expect((float) $record->offer_products()->first()->discount)->toBe(20.0);
    } else {
        expect(FlashDealProduct::query()->count())->toBe(1);
        expect((float) Product::findOrFail($fields['products'][0]['id'])->discount)->toBe(20.0);
        expect(FlashDealTranslation::query()->first()->title)->toBe('Campaign');
    }
    $this->get(route($module.'.edit', $record))->assertOk();
    $fields['title'] = 'Updated';
    $this->put(route($module.'.update', $record), $fields)->assertRedirect(route($module.'.index'));
    expect($record->fresh()->title)->toBe('Updated');
})->with('campaign modules');

test('brand offer edits retain their discriminator and derive the correct base representative', function () {
    actingAsStoreUser(createStoreAdmin());
    $brand = Brand::forceCreate(['name' => 'Brand']);
    $brandProduct = createStoreProduct(['brand_id' => $brand->id, 'published' => 1, 'approved' => 1]);
    $another = Brand::forceCreate(['name' => 'Other']);
    $nextBase = createStoreProduct(['brand_id' => $another->id, 'published' => 1, 'approved' => 1]);
    $data = campaignForm('offer_deals', ['offer_type' => 'brand_offer', 'brand_id' => $brand->id, 'base_quantity' => 35.5]);
    $this->get(route('offer_deals.brands.create'))->assertInertia(fn (Assert $p) => $p->where('offerType', 'brand_offer'));
    $this->post(route('offer_deals.store'), $data)->assertRedirect();
    $offer = Offer::query()->firstOrFail();
    expect($offer->offer_type)->toBe('brand_offer');
    expect((int) $offer->base_product->product_id)->toBe($brandProduct->id);
    expect((float) $offer->base_product->quantity)->toBe(35.5);
    $data['brand_id'] = $another->id;
    $this->put(route('offer_deals.update', $offer), $data)->assertRedirect();
    expect((int) $offer->fresh()->brand_id)->toBe($another->id);
    expect((int) $offer->fresh()->base_product->product_id)->toBe($nextBase->id);
    $this->get(route('offer_deals.edit', $offer))->assertInertia(fn (Assert $p) => $p->where('offer.offer_type', 'brand_offer')->where('selectedBrand.id', $another->id));
    $data['offer_type'] = 'products_offer';
    $this->putJson(route('offer_deals.update', $offer), $data)->assertUnprocessable()->assertJsonValidationErrors('offer_type');
});

test('flash edits preserve slug and default title while translating and removal leaves global discounts', function () {
    actingAsStoreUser(createStoreAdmin());
    DB::table('languages')->insert(['name' => 'Arabic', 'code' => 'ar']);
    $deal = campaignRecord('flash_deals');
    $data = campaignForm('flash_deals', ['lang' => 'ar', 'title' => 'عرض']);
    $this->put(route('flash_deals.update', $deal), $data)->assertRedirect();
    expect($deal->fresh()->title)->toBe('Existing');
    expect($deal->fresh()->slug)->toBe('stable-url');
    expect(FlashDealTranslation::query()->where('lang', 'ar')->value('title'))->toBe('عرض');
    $this->get(route('flash_deals.edit', [$deal, 'lang' => 'ar']))->assertInertia(fn (Assert $p) => $p->where('title', 'عرض')->where('lang', 'ar')->has('products', 1));
    $product = Product::findOrFail($data['products'][0]['id']);
    $discount = $product->discount;
    $data = campaignForm('flash_deals', ['title' => 'Changed']);
    $this->put(route('flash_deals.update', $deal), $data)->assertRedirect();
    expect($deal->fresh()->slug)->toBe('stable-url');
    expect(FlashDealProduct::query()->where('product_id', $product->id)->exists())->toBeFalse();
    expect($product->fresh()->discount)->toBe($discount);
    $this->delete(route('flash_deals.destroy', $deal))->assertRedirect();
    expect($product->fresh()->discount)->toBe($discount);
    expect(FlashDealTranslation::query()->count())->toBe(0);
});

test('campaign product search is translated bounded and preserves legacy eligibility including sellers', function () {
    actingAsStoreUser(createStoreStaff('add_flash_deal'));
    $seller = createStoreUser('seller');
    $product = createStoreProduct(['user_id' => $seller->id, 'published' => 1, 'approved' => 1, 'name' => 'Translated']);
    DB::table('product_translations')->insert(['product_id' => $product->id, 'name' => 'منتج', 'lang' => 'ar']);
    app()->setLocale('ar');
    session(['locale' => 'ar']);
    createStoreProduct(['published' => 0, 'approved' => 1, 'name' => 'منتج']);
    createStoreProduct(['published' => 1, 'approved' => 0, 'name' => 'منتج']);
    $this->getJson(route('admin.campaign-products.index', ['q' => 'منتج']))->assertOk()->assertExactJson([['id' => $product->id, 'name' => 'منتج', 'price' => (float) $product->unit_price, 'discount' => (float) $product->discount, 'discount_type' => $product->discount_type ?? 'amount']]);
    foreach (range(1, 35) as $i) {
        createStoreProduct(['published' => 1, 'approved' => 1]);
    }$this->getJson(route('admin.campaign-products.index'))->assertJsonCount(30);
    $this->getJson(route('admin.campaign-products.index', ['q' => str_repeat('a', 256)]))->assertUnprocessable();
});

test('invalid campaign inputs refuse partial writes and preserve old targeting', function (string $module, string $field, mixed $value) {
    actingAsStoreUser(createStoreAdmin());
    $record = campaignRecord($module);
    $fields = campaignForm($module);
    $original = Product::findOrFail($fields['products'][0]['id'])->getAttributes();
    data_set($fields, $field, $value);
    $this->putJson(route($module.'.update', $record), $fields)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($record->fresh()->title)->toBe('Existing');
    expect(Product::findOrFail($fields['products'][0]['id'] ?? $original['id'])->getAttributes())->toBe($original);
})->with('campaign modules')->with(['missing title' => ['title', ''], 'malformed date' => ['start_date', 'invalid'], 'reversed date' => ['end_date', '2026-01-01T00:00:00'], 'no products' => ['products', []], 'missing discount' => ['products.0.discount', null], 'negative discount' => ['products.0.discount', -1], 'percent too large' => ['products.0.discount', 101], 'invalid type' => ['products.0.discount_type', 'html'], 'invalid upload' => ['banner', 999999]]);

test('invalid and repeated product references are refused before row deletion', function (string $module) {
    actingAsStoreUser(createStoreAdmin());
    $record = campaignRecord($module);
    $fields = campaignForm($module);
    $rows = $fields['products'];
    $unpublished = createStoreProduct(['published' => 0, 'approved' => 1]);
    $fields['products'][0]['id'] = $unpublished->id;
    $this->putJson(route($module.'.update', $record), $fields)->assertUnprocessable()->assertJsonValidationErrors('products.0.id');
    $fields['products'] = [$rows[0], $rows[0]];
    $this->putJson(route($module.'.update', $record), $fields)->assertUnprocessable()->assertJsonValidationErrors('products.0.id');
    if ($module === 'offer_deals') {
        $fields['products'] = $rows;
        $fields['base_quantity'] = 0;
        $this->putJson(route($module.'.update', $record), $fields)->assertJsonValidationErrors('base_quantity');
        $fields['offer_type'] = 'brand_offer';
        $fields['brand_id'] = Brand::forceCreate(['name' => 'Empty'])->id;
        $this->postJson(route($module.'.store'), $fields)->assertJsonValidationErrors('brand_id');
    } else {
        $fields['products'] = $rows;
        $fields['lang'] = 'unknown';
        $this->putJson(route($module.'.update', $record), $fields)->assertJsonValidationErrors('lang');
        $fields['lang'] = 'en';
        $fields['title'] = str_repeat('a', 51);
        $this->putJson(route($module.'.update', $record), $fields)->assertJsonValidationErrors('title');
    }
})->with('campaign modules');

test('campaign toggles preserve query and featured writes clear all other campaigns', function () {
    actingAsStoreUser(createStoreAdmin());
    $offer = campaignRecord('offer_deals');
    foreach (['update_status' => 'is_active', 'update_repeatable' => 'is_repeatable', 'update_multiple_usage' => 'multiple_usage'] as $action => $field) {
        $this->put(route('offer_deals.'.$action, [$offer, 'page' => 2]), ['value' => 1])->assertRedirect(route('offer_deals.index', ['page' => 2]));
        expect((int) $offer->fresh()->$field)->toBe(1);
        $this->putJson(route('offer_deals.'.$action, $offer), ['value' => 'invalid'])->assertUnprocessable();
    }
    $one = campaignRecord('flash_deals');
    $two = campaignRecord('flash_deals');
    $two->featured = 1;
    $two->save();
    $this->put(route('flash_deals.update_featured', $one), ['value' => 1])->assertRedirect();
    expect((int) $two->fresh()->featured)->toBe(0);
    expect((int) $one->fresh()->featured)->toBe(1);
    $this->put(route('flash_deals.update_featured', $two), ['value' => 0])->assertRedirect();
    expect((int) $one->fresh()->featured)->toBe(0);
    $this->put(route('flash_deals.update_status', $one), ['value' => 1])->assertRedirect();
    expect((int) $one->fresh()->status)->toBe(1);
});

foreach (['offer_deals', 'flash_deals'] as $module) {
    foreach (['index', 'create', 'edit', 'store', 'update', 'destroy', 'update_status', ...($module === 'offer_deals' ? ['brandCreate', 'update_repeatable', 'update_multiple_usage'] : ['update_featured'])] as $action) {
        test("$module $action refuses every unauthorized caller and performs no writes", function (string $caller) use ($module, $action) {
            $record = campaignRecord($module);
            $data = campaignForm($module);
            $attrs = $record->fresh()->getAttributes();
            $product = Product::findOrFail($data['products'][0]['id']);
            $productAttrs = $product->getAttributes();
            actingAsRefusedStoreCaller($caller);
            $route = $module.'.'.($action === 'brandCreate' ? 'brands.create' : $action);
            $params = in_array($action, ['index', 'create', 'brandCreate', 'store']) ? [] : [$record];
            $url = route($route, $params);
            $response = match ($action) {
                'index','create','edit','brandCreate' => $this->get($url),'store' => $this->post($url, $data),'destroy' => $this->delete($url),default => $this->put($url, str_starts_with($action, 'update_') ? ['value' => 1] : $data)
            };
            assertStoreCallerRefused($response, $caller);
            expect($record->fresh()->getAttributes())->toBe($attrs);
            expect($product->fresh()->getAttributes())->toBe($productAttrs);
        })->with('refused store admin callers');
    }
}
test('campaign endpoints and missing records retain store guard and 404 behavior', function (string $module) {
    actingAsStoreUser(createStoreAdmin());
    $fields = campaignForm($module);
    $this->get(route($module.'.edit', 999999))->assertNotFound();
    $this->put(route($module.'.update', 999999), $fields)->assertNotFound();
    $this->delete(route($module.'.destroy', 999999))->assertNotFound();
})->with('campaign modules');
test('product and brand search refuse callers outside the campaign write permissions', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    foreach (['index', 'brands'] as $action) {
        assertStoreCallerRefused($this->get(route('admin.campaign-products.'.$action)), $caller);
    }
})->with('refused store admin callers');

test('warehouse administrator sessions cannot write campaigns or change product discounts', function (string $module) {
    $record = campaignRecord($module);
    $fields = campaignForm($module);
    $original = $record->fresh()->getAttributes();
    $product = Product::findOrFail($fields['products'][0]['id']);
    $originalProduct = $product->getAttributes();
    actingAsAdmin();
    $this->post(route($module.'.store'), $fields)->assertRedirect(route('shop.login'));
    $this->put(route($module.'.update', $record), $fields)->assertRedirect(route('shop.login'));
    $this->put(route($module.'.update_status', $record), ['value' => 1])->assertRedirect(route('shop.login'));
    $this->delete(route($module.'.destroy', $record))->assertRedirect(route('shop.login'));
    expect($record->fresh()->getAttributes())->toBe($original);
    expect($product->fresh()->getAttributes())->toBe($originalProduct);
    $this->assertDatabaseCount($module === 'offer_deals' ? 'offers' : 'flash_deals', 1);
    $this->assertDatabaseCount($module === 'offer_deals' ? 'offer_products' : 'flash_deal_products', 0);
})->with('campaign modules');

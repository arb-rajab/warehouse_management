<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

test('order product search returns bounded search options by a part of the id, translated, and excludes nonmatches', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    foreach (range(1, 23) as $number) {
        $product = Product::factory()->create(['id' => 100000 + $number, 'name' => "Product $number", 'thumbnail_img' => null]);
        $product->forceFill(['description' => 'private description'])->save();
        DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'ar', 'name' => "منتج $number"]);
    }
    $noise = Product::factory()->create(['id' => 200001]);
    $this->withSession(['locale' => 'ar']);
    $response = $this->getJson(route('order_configuration.product-search', ['q' => '1000']))->assertOk();
    expect($response->json('data'))->toHaveCount(20)->and($response->json('meta.total'))->toBe(23)->and($response->json('meta.last_page'))->toBe(2);
    expect($response->json('data.0'))->toEqual([
        'id' => 100001, 'label' => 'منتج 1', 'description' => '#100001', 'image' => null, 'icon' => 'package', 'badge' => null,
        'extra' => ['mat_id' => '100001'],
    ]);
    expect(collect($response->json('data'))->pluck('id')->all())->not->toContain($noise->id)
        ->and(collect($response->json('data'))->every(fn (array $row): bool => $row['extra']['mat_id'] === (string) $row['id']))->toBeTrue();
    expect($response->getContent())->not->toContain('private description');
    expect($this->getJson(route('order_configuration.product-search', ['q' => '1000', 'page' => 2]))->json('data'))->toHaveCount(3);
});

test('product search finds a product by a part of its id and ranks ids that start with the term first', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $product = Product::factory()->create(['id' => 734251, 'name' => 'Needle']);
    $starts = Product::factory()->create(['id' => 342511, 'name' => 'Starts']);
    Product::factory()->create(['id' => 100001, 'name' => 'Noise']);

    $ids = $this->getJson(route('order_configuration.product-search', ['q' => '3425']))->assertOk()->json('data.*.id');

    expect($ids)->toBe([$starts->id, $product->id]);
});

test('product search returns markup as plain JSON data and a thumbnail only for an http image', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $product = Product::factory()->imageUrl('https://images.example.test/a.jpg')->create(['id' => 100500, 'name' => '<img src=x onerror=alert(1)>']);

    $row = $this->getJson(route('order_configuration.product-search', ['q' => '1005']))->assertOk()->json('data.0');

    expect($row['id'])->toBe($product->id)->and($row['label'])->toBe('<img src=x onerror=alert(1)>')->and($row['image'])->toBe('https://images.example.test/a.jpg');
});

test('order product search validates missing short long and array terms', function (mixed $query) {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->getJson(route('order_configuration.product-search', ['q' => $query]))
        ->assertUnprocessable()->assertJsonValidationErrors('q');
})->with([null, 'abc', str_repeat('a', 101), [['x']]]);

test('product attribute choices return exact JSON without HTML and exclude other attributes', function (string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    $attribute = Attribute::create(['name' => 'Size']);
    $other = Attribute::create(['name' => 'Other']);
    AttributeValue::create(['attribute_id' => $other->id, 'value' => 'Noise']);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => '"><img src=x onerror=alert(1)>']);
    expect($this->postJson(route('products.add-more-choice-option'), ['attribute_id' => $attribute->id])->assertOk()->json())
        ->toBe([['id' => $value->id, 'value' => '"><img src=x onerror=alert(1)>']]);
})->with(['add_new_product', 'product_edit']);

test('an unknown product attribute gives a field error rather than 404', function () {
    actingAsStoreUser(createStoreStaff('add_new_product'));
    $this->postJson(route('products.add-more-choice-option'), ['attribute_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_id');
});

test('order product search refuses an unauthorized caller', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('order_configuration.product-search', ['q' => '1000'])), $caller);
})->with('refused store admin callers');

test('attribute option lookup refuses an unauthorized caller without writes', function (string $caller) {
    $attribute = Attribute::create(['name' => 'Size']);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Large']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('products.add-more-choice-option'), ['attribute_id' => $attribute->id]), $caller);
    expect(AttributeValue::count())->toBe(1)->and($value->fresh()->value)->toBe('Large');
})->with('refused store admin callers');

<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

test('order product search returns bounded narrow translated JSON and excludes nonmatches', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    foreach (range(1, 23) as $number) {
        $product = Product::factory()->create(['name' => "Product $number"]);
        $product->forceFill(['mat_id' => "MAT-$number", 'description' => 'private description'])->save();
        DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'ar', 'name' => "منتج $number"]);
    }
    $noise = Product::factory()->create();
    $noise->forceFill(['mat_id' => 'OTHER'])->save();
    $this->withSession(['locale' => 'ar']);
    $response = $this->getJson(route('order_configuration.product-search', ['query' => 'MAT-']))->assertOk();
    expect($response->json())->toHaveCount(20);
    expect($response->json('0'))->toBe(['id' => Product::query()->orderBy('id')->first()->id, 'name' => 'منتج 1', 'mat_id' => 'MAT-1']);
    expect(collect($response->json())->pluck('id')->all())->not->toContain($noise->id);
});

test('product search returns markup as plain JSON data', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $product = Product::factory()->create(['name' => '<img src=x onerror=alert(1)>']);
    $product->forceFill(['mat_id' => 'MAT-" onmouseover="alert(1)'])->save();
    expect($this->getJson(route('order_configuration.product-search', ['query' => 'MAT-']))->assertOk()->json())
        ->toBe([['id' => $product->id, 'name' => '<img src=x onerror=alert(1)>', 'mat_id' => 'MAT-" onmouseover="alert(1)']]);
});

test('order product search validates missing short long and array terms', function (mixed $query) {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->getJson(route('order_configuration.product-search', ['query' => $query]))
        ->assertUnprocessable()->assertJsonValidationErrors('query');
})->with([null, 'abc', str_repeat('a', 201), [['x']]]);

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
    assertStoreCallerRefused($this->get(route('order_configuration.product-search', ['query' => 'MAT-'])), $caller);
})->with('refused store admin callers');

test('attribute option lookup refuses an unauthorized caller without writes', function (string $caller) {
    $attribute = Attribute::create(['name' => 'Size']);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Large']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('products.add-more-choice-option'), ['attribute_id' => $attribute->id]), $caller);
    expect(AttributeValue::count())->toBe(1)->and($value->fresh()->value)->toBe('Large');
})->with('refused store admin callers');

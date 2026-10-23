<?php

use App\Http\Resources\Store\ProductFormResource;
use App\Models\Category;
use App\Models\Language;
use App\Services\Store\InHouseProductWriter;
use App\Support\ProductFormNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

test('product edit decimals have the same exact string representation on MySQL and SQLite', function () {
    expect(ProductFormNumber::format('999999999999999999.90'))->toBe('999999999999999999.9');
    $product = createStoreProduct(['unit_price' => '12.50', 'discount' => '1.50', 'weight' => '1.20', 'shipping_cost' => '4.00']);
    $product->load(['stocks', 'variations', 'storeBarcodes']);
    $fields = (new ProductFormResource($product))->toArray(request());
    expect($fields['unit_price'])->toBe('12.5')->and($fields['discount'])->toBe('1.5')
        ->and($fields['weight'])->toBe('1.2')->and($fields['flat_shipping_cost'])->toBe('4');
});

test('an existing mismatch between stock names and stored choices cannot silently delete stock on save', function () {
    $product = createStoreProduct(['colors' => '[]', 'choice_options' => '[{"attribute_id":"1","values":["M"]},{"attribute_id":"2","values":["Cotton"]}]']);
    $product->stocks()->delete();
    $stock = $product->stocks()->create(['variant' => 'Cotton-M', 'price' => '17.50', 'sku' => 'KEEP', 'qty' => 47]);
    $before = $stock->fresh()->getAttributes();
    try {
        DB::transaction(fn () => app(InHouseProductWriter::class)->update($product, [
            'name' => 'Correct name', 'unit' => 'pc', 'unit_price' => '12.50', 'current_stock' => 0,
            'lang' => config('app.default_language'), 'choice_options' => [['attribute_id' => 1, 'values' => ['M']], ['attribute_id' => 2, 'values' => ['Cotton']]],
            'variants' => [['variant' => 'M-Cotton', 'price' => '12.50', 'qty' => 10]],
        ], ['M-Cotton'], [], false, false));
        $this->fail('The unresolved legacy mismatch must be refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('choice_options');
    }
    expect($stock->fresh()?->getAttributes())->toBe($before);
});

test('the real edit endpoint normalizes stored prices default variant prices and tax decimals', function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    actingAsStoreUser(createStoreStaff('product_edit'));
    $category = Category::forceCreate(['name' => 'Pantry', 'slug' => 'pantry', 'parent_id' => 0, 'digital' => 0]);
    $size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'S'], ['attribute_id' => $size, 'value' => 'L']]);
    $product = createStoreProduct(['category_id' => $category->id, 'unit_price' => '12.50', 'discount' => '1.50', 'weight' => '1.20', 'shipping_cost' => '4.00', 'colors' => '[]', 'choice_options' => json_encode([['attribute_id' => $size, 'values' => ['S', 'L']]])]);
    $product->stocks()->delete();
    $product->stocks()->create(['variant' => 'S', 'price' => '13.50', 'qty' => 9]);
    $tax = DB::table('taxes')->insertGetId(['name' => 'VAT', 'tax_status' => 1]);
    DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => $tax, 'tax' => '21.00', 'tax_type' => 'percent']);
    DB::table('business_settings')->insert(['type' => 'VAT_TAX_section', 'value' => '1']);
    $this->get(route('products.admin.edit', $product))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('product.unit_price', '12.5')->where('product.discount', '1.5')->where('product.weight', '1.2')->where('product.flat_shipping_cost', '4')
        ->where('variants.rows.0.price', '13.5')->where('variants.rows.1.price', '12.5')->where('taxes.0.tax', '21'));
});

test('numeric-looking variant values and their stock rows remain distinct in the real edit form', function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    actingAsStoreUser(createStoreStaff('product_edit'));
    $size = DB::table('attributes')->insertGetId(['name' => 'Size']);
    DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => '01'], ['attribute_id' => $size, 'value' => '1']]);
    $product = createStoreProduct(['colors' => '[]', 'choice_options' => json_encode([['attribute_id' => $size, 'values' => ['01', '1']]])]);
    $product->stocks()->delete();
    $product->stocks()->create(['variant' => '01', 'price' => '7.50', 'qty' => 1, 'sku' => 'FIRST']);
    $product->stocks()->create(['variant' => '1', 'price' => '9.50', 'qty' => 2, 'sku' => 'SECOND']);
    $this->get(route('products.admin.edit', $product))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('variants.choice_options.0.values', ['01', '1'])->has('variants.rows', 2)
        ->where('variants.rows.0.variant', '01')->where('variants.rows.0.price', '7.5')->where('variants.rows.0.sku', 'FIRST')
        ->where('variants.rows.1.variant', '1')->where('variants.rows.1.price', '9.5')->where('variants.rows.1.sku', 'SECOND'));
});

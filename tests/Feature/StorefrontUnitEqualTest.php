<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * A product whose `unit_equal` is 0 or null made the product page and every
 * listing card divide by zero (a DivisionByZeroError, so a 500) for a signed-in,
 * admin-verified customer, the only visitor the pages show prices to. The
 * per-piece price is now `Product::$price_per_piece`, null unless `unit_equal`
 * is a positive number, and the per-piece line is printed only when it is not null.
 */
beforeEach(function () {
    seedStoreCurrency();
});

function unitEqualVerifiedCustomer(): void
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['admin_verified' => true])->save();
    actingAsStoreUser($customer->fresh());
}

/**
 * @param  array<string, mixed>  $attributes
 */
function unitEqualProduct(array $attributes = []): Product
{
    $category = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);

    return createStoreProduct([
        'slug' => 'salty-crisps',
        'unit_price' => 2.5,
        'unit' => 'Box',
        'category_id' => $category,
        ...$attributes,
    ]);
}

/** A 10 % discount makes the product page take its "discounted" branch. */
const UNIT_EQUAL_DISCOUNT = ['discount' => 10, 'discount_type' => 'percent'];

dataset('unit_equal that is not a positive number', [
    'zero' => [0],
    'null' => [null],
]);

test('the product page of a discounted product with no usable unit_equal renders for a verified customer, without a per-piece line', function (?int $unitEqual) {
    unitEqualProduct([...UNIT_EQUAL_DISCOUNT, 'unit_equal' => $unitEqual]);
    unitEqualVerifiedCustomer();

    $this->get('/temp/product/salty-crisps')
        ->assertOk()
        ->assertDontSee('Price Per Piece');
})->with('unit_equal that is not a positive number');

test('the product page of an undiscounted product with no usable unit_equal renders for a verified customer, without a per-piece line', function (?int $unitEqual) {
    unitEqualProduct(['unit_equal' => $unitEqual]);
    unitEqualVerifiedCustomer();

    $this->get('/temp/product/salty-crisps')
        ->assertOk()
        ->assertDontSee('Price Per Piece');
})->with('unit_equal that is not a positive number');

test('a listing card with no usable unit_equal renders for a verified customer, without a per-piece price', function (?int $unitEqual) {
    unitEqualProduct(['unit_equal' => $unitEqual]);
    unitEqualVerifiedCustomer();

    $this->get('/temp/category/snacks')
        ->assertOk()
        ->assertSee('/temp/product/salty-crisps')
        ->assertDontSee('fw-800 text-primary', false);
})->with('unit_equal that is not a positive number');

test('a guest sees the product page and the listing of a product with no usable unit_equal, and no price at all', function (?int $unitEqual) {
    unitEqualProduct([...UNIT_EQUAL_DISCOUNT, 'unit_equal' => $unitEqual]);

    $this->get('/temp/product/salty-crisps')->assertOk()->assertDontSee('Price Per Piece');
    $this->get('/temp/category/snacks')->assertOk()->assertDontSee('fw-800 text-primary', false);
})->with('unit_equal that is not a positive number');

test('a product with a valid unit_equal still prints the same per-piece price, discounted or not', function () {
    unitEqualProduct([...UNIT_EQUAL_DISCOUNT, 'unit_equal' => 4]);
    unitEqualVerifiedCustomer();

    $discounted = $this->get('/temp/product/salty-crisps')->assertOk();
    $discounted->assertSee('Price Per Piece');
    $discounted->assertSeeInOrder(['Price Per Piece', '€0.625', '/ PIECE'], false);
    $this->get('/temp/category/snacks')->assertOk()->assertSee('<span class="mx-2 fw-800 text-primary"> €0.625</span>', false);

    Product::query()->update(['discount' => 0, 'discount_type' => null]);

    $plain = $this->get('/temp/product/salty-crisps')->assertOk();
    $plain->assertSeeInOrder(['Price Per Piece', '€0.625', '/ PIECE'], false);
});

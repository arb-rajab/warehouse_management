<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\StoreUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The storefront's review form let anyone review any product, any number of
 * times, with any rating (a rating of 100000 drags a product's average with it);
 * the app's endpoint checked that the customer had received the product but not
 * the rating. Both ask Product::isReviewableBy() now and take a rating from 1 to 5.
 */
function receivedProductFor(StoreUser $customer): Product
{
    $product = createStoreProduct();
    orderStoreProduct($customer, $product)->forceFill(['delivery_status' => 'delivered'])->save();

    return $product;
}

function ensureReviewPhotosColumn(): void
{
    // The store's own reviews table has it; this app's stand-in does not yet.
    if (! Schema::hasColumn('reviews', 'photos')) {
        Schema::table('reviews', fn (Blueprint $table) => $table->text('photos')->nullable());
    }
}

test('the app accepts a review from a customer who received the product, once, with a rating from 1 to 5', function () {
    $customer = createStoreUser('customer');
    $product = receivedProductFor($customer);
    $token = storeApiToken($customer);
    $submit = fn (array $payload) => test()->withToken($token)->postJson('/temp/api/v2/reviews/submit', ['product_id' => $product->id, 'comment' => 'Good', ...$payload]);

    foreach ([0, 6, 100000, 'five', null] as $bad) {
        app('auth')->forgetGuards();
        $submit(['rating' => $bad])->assertStatus(422);
    }
    expect(Review::count())->toBe(0);

    app('auth')->forgetGuards();
    $submit(['rating' => 4])->assertOk()->assertJsonPath('result', true);
    app('auth')->forgetGuards();
    $submit(['rating' => 5])->assertOk()->assertJsonPath('result', false);

    expect(Review::pluck('rating')->all())->toEqual([4]);
});

test('the app refuses a review of a product the customer did not receive', function () {
    $customer = createStoreUser('customer');
    $product = createStoreProduct();
    orderStoreProduct($customer, $product); // ordered, not delivered

    $this->withToken(storeApiToken($customer))->postJson('/temp/api/v2/reviews/submit', ['product_id' => $product->id, 'rating' => 5, 'comment' => 'x'])
        ->assertOk()->assertJsonPath('result', false);

    expect(Review::count())->toBe(0);
});

test('the storefront form takes a review from a customer who received the product, once, and refuses a wild rating', function () {
    ensureReviewPhotosColumn();
    $customer = createStoreUser('customer');
    $product = receivedProductFor($customer);
    actingAsStoreUser($customer);

    $this->post('/temp/reviews', ['product_id' => $product->id, 'rating' => 100000, 'comment' => 'x'])->assertSessionHasErrors('rating');
    expect(Review::count())->toBe(0);

    $this->post('/temp/reviews', ['product_id' => $product->id, 'rating' => 5, 'comment' => 'great']);
    $this->post('/temp/reviews', ['product_id' => $product->id, 'rating' => 1, 'comment' => 'again']);

    expect(Review::pluck('rating')->all())->toEqual([5]);
});

test('the storefront form refuses a review of a product the customer never received', function () {
    ensureReviewPhotosColumn();
    $customer = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $product = receivedProductFor($customer);
    actingAsStoreUser($stranger);

    $this->post('/temp/reviews', ['product_id' => $product->id, 'rating' => 5, 'comment' => 'never bought it']);

    expect(Review::count())->toBe(0);
});

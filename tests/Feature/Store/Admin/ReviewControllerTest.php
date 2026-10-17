<?php

use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function catalogueReview(array $attributes = []): Review
{
    return Review::forceCreate([
        'product_id' => createStoreProduct(['name' => 'Dates', 'slug' => 'dates', 'added_by' => 'admin'])->id,
        'user_id' => createStoreUser('customer', 'reviewer@example.com')->id,
        'rating' => 4, 'comment' => '<img src=x onerror=alert(1)>', 'status' => 1,
        ...$attributes,
    ]);
}

test('the product reviews page can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_product_reviews'));
    $this->get('/temp/admin/reviews')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/Reviews/Index'));
});

test('reviews expose only their full display shape and translated product name', function () {
    actingAsStoreUser(createStoreStaff('view_product_reviews'));
    $review = catalogueReview();
    DB::table('product_translations')->insert(['product_id' => $review->product_id, 'lang' => 'en', 'name' => 'Translated dates']);
    $customer = $review->user;
    $this->get('/temp/admin/reviews')->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['per_page' => 20, 'rating' => null])
        ->has('reviews.data', 1)
        ->has('reviews.data.0', fn (Assert $row) => $row
            ->where('id', $review->id)->where('rating', 4)->where('comment', '<img src=x onerror=alert(1)>')->where('published', true)
            ->where('product', ['id' => $review->product_id, 'name' => 'Translated dates', 'owner' => 'admin', 'url' => route('product', 'dates')])
            ->where('customer', ['id' => $customer->id, 'name' => $customer->name, 'email' => 'reviewer@example.com'])));
});

test('reviews paginate exclude orphans and support rating sorting', function () {
    actingAsStoreUser(createStoreStaff('view_product_reviews'));
    $first = catalogueReview(['rating' => 5]);
    foreach (range(1, 20) as $number) {
        Review::forceCreate(['product_id' => $first->product_id, 'user_id' => $first->user_id, 'rating' => 1, 'comment' => "Review $number", 'status' => 1]);
    }
    Review::forceCreate(['product_id' => 999999, 'user_id' => $first->user_id, 'rating' => 5, 'comment' => 'Orphan', 'status' => 1]);
    $this->get('/temp/admin/reviews?rating=rating,desc')->assertInertia(fn (Assert $page) => $page->has('reviews.data', 20)->where('reviews.meta.total', 21)->where('reviews.data.0.id', $first->id));
    $this->get('/temp/admin/reviews?per_page=10&rating=rating,asc')->assertInertia(fn (Assert $page) => $page->has('reviews.data', 10)->where('reviews.data.0.rating', 1));
    $this->get('/temp/admin/reviews?per_page=999')->assertSessionHasErrors('per_page');
    $this->get('/temp/admin/reviews?rating=rating,invalid')->assertSessionHasErrors('rating');
});

test('review list refuses unauthorized store callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/reviews'), $caller);
})->with('refused store admin callers');

test('publishing updates product average and preserves the filter query', function () {
    actingAsStoreUser(createStoreStaff('publish_product_review'));
    $review = catalogueReview(['status' => 0]);
    Review::forceCreate(['product_id' => $review->product_id, 'user_id' => $review->user_id, 'rating' => 2, 'comment' => 'Another review', 'status' => 1]);
    $this->post("/temp/admin/reviews/{$review->id}/published?per_page=10", ['status' => 1])->assertRedirect(route('admin.reviews.index', ['per_page' => 10]));
    expect((int) $review->fresh()->status)->toBe(1)->and((float) $review->product->fresh()->rating)->toBe(3.0);
    $this->post("/temp/admin/reviews/{$review->id}/published", ['status' => 0])->assertRedirect();
    expect((float) $review->product->fresh()->rating)->toBe(2.0);
});

test('publishing seller reviews is idempotent and unpublishing cannot underflow the shop count', function () {
    actingAsStoreUser(createStoreStaff('publish_product_review'));
    $seller = createStoreUser('seller');
    $product = createStoreProduct(['added_by' => 'seller', 'user_id' => $seller->id]);
    DB::table('shops')->insert(['user_id' => $seller->id, 'name' => 'Seller shop', 'rating' => 0, 'num_of_reviews' => 0]);
    $review = catalogueReview(['product_id' => $product->id, 'status' => 0]);
    foreach (range(1, 2) as $attempt) {
        $this->post("/temp/admin/reviews/{$review->id}/published", ['status' => 1])->assertRedirect();
    }
    expect((int) DB::table('shops')->where('user_id', $seller->id)->value('num_of_reviews'))->toBe(1);
    $this->post("/temp/admin/reviews/{$review->id}/published", ['status' => 0])->assertRedirect();
    expect((int) DB::table('shops')->where('user_id', $seller->id)->value('num_of_reviews'))->toBe(0)->and((float) $product->fresh()->rating)->toBe(0.0);
});

test('invalid publication status leaves the review untouched', function () {
    actingAsStoreUser(createStoreStaff('publish_product_review'));
    $review = catalogueReview();
    $this->post("/temp/admin/reviews/{$review->id}/published", ['status' => 2])->assertSessionHasErrors('status');
    expect((int) $review->fresh()->status)->toBe(1);
});

test('publishing a missing review returns 404', function () {
    actingAsStoreUser(createStoreStaff('publish_product_review'));
    $this->post('/temp/admin/reviews/999999/published', ['status' => 1])->assertNotFound();
});

test('review publication refuses unauthorized store callers without writing', function (string $caller) {
    $review = catalogueReview();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post("/temp/admin/reviews/{$review->id}/published", ['status' => 0]), $caller);
    expect((int) $review->fresh()->status)->toBe(1);
})->with('refused store admin callers');

test('the old reviews.index URL redirects to the Inertia list keeping the query string', function () {
    actingAsStoreUser(createStoreStaff('view_product_reviews'));
    expect(route('reviews.index'))->toBe(url('/temp/reviews'));
    $this->get('/temp/reviews')->assertRedirect(route('admin.reviews.index'));
    $this->get('/temp/reviews?rating=rating,asc&per_page=10&page=2')
        ->assertRedirect(route('admin.reviews.index', ['rating' => 'rating,asc', 'per_page' => 10, 'page' => 2]));
});

test('the old reviews.index URL refuses unauthorized callers without redirecting to the list', function (string $caller, ?int $status) {
    actingAsRefusedStoreCaller($caller);
    $response = $this->get('/temp/reviews?per_page=10');
    // The legacy route sits outside the store admin group, so a customer is refused by the permission gate (403), not hidden as a 404.
    $status === null ? $response->assertRedirect(route('shop.login')) : $response->assertStatus($status);
    expect((string) $response->headers->get('Location'))->not->toContain('/temp/admin/reviews');
})->with([
    'guest' => ['guest', null],
    'customer' => ['customer', 403],
    'staff without the permission' => ['staff without the permission', 403],
]);

test('the root review route still stores customer reviews and no longer lists them', function () {
    expect(Route::has('reviews.store'))->toBeTrue()
        ->and(Route::getRoutes()->getByName('reviews.index')->getActionName())->toContain('Store\\Admin\\ReviewController@redirectLegacyIndex')
        ->and(view()->exists('shop.backend.product.reviews.index'))->toBeFalse();
});

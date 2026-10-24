<?php

use App\Enums\CellState;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\ProductImageReview;
use App\Models\Row;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an admin records a mismatch with an optional note and reviewer without changing the image', function () {
    $user = actingAsAdmin();
    $product = Product::factory()->imageUrl('https://cdn.example.com/old.jpg')->create();
    $this->post("/admin/products/{$product->id}/image-reviews?page=2", [
        'image_review_status' => 'doesnt_match', 'image_source' => $product->image_url,
        'image_review_note' => '  Different packaging  ',
    ])->assertRedirect('/admin/products?page=2')->assertSessionHasNoErrors();
    $review = ProductImageReview::query()->sole();
    expect($review->status)->toBe('doesnt_match')->and($review->note)->toBe('Different packaging')
        ->and($review->user_id)->toBe($user->id)->and($review->created_at)->not->toBeNull()
        ->and($product->fresh()->image_url)->toBe('https://cdn.example.com/old.jpg');
});

test('an admin can record a missing image without a note', function () {
    actingAsAdmin();
    $product = Product::factory()->imageUrl(null)->create();
    $this->post("/admin/products/{$product->id}/image-reviews", [
        'image_review_status' => 'image_missing', 'image_source' => null,
    ])->assertSessionHasNoErrors();
    expect(ProductImageReview::query()->sole()->note)->toBeNull();
});

test('reviews refuse invalid outcomes stale images and oversized notes', function (array $payload, string $field) {
    actingAsAdmin();
    $product = Product::factory()->imageUrl('https://cdn.example.com/old.jpg')->create();
    $this->post("/admin/products/{$product->id}/image-reviews", array_replace([
        'image_review_status' => 'matches', 'image_source' => $product->image_url,
    ], $payload))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('wms_product_image_reviews', 0);
})->with([
    'unreviewed' => [['image_review_status' => ''], 'image_review_status'],
    'unknown' => [['image_review_status' => 'yes'], 'image_review_status'],
    'missing when present' => [['image_review_status' => 'image_missing'], 'image_review_status'],
    'stale' => [['image_source' => 'https://cdn.example.com/stale.jpg'], 'image_review_status'],
    'note cap' => [['image_review_note' => str_repeat('x', 1001)], 'image_review_note'],
]);

test('new images invalidate reviews and preserve their notes and old reference images', function () {
    Storage::fake('public');
    $user = actingAsAdmin();
    $product = Product::factory()->imageUrl('https://cdn.example.com/old.jpg')->create();
    $review = ProductImageReview::factory()->create([
        'product_id' => $product->id, 'user_id' => $user->id,
        'status' => 'doesnt_match', 'image_source' => (string) $product->thumbnail_img,
        'image_url' => $product->image_url, 'note' => 'Wrong variant',
    ]);
    $this->post("/admin/products/{$product->id}/reference-image", [
        'image' => UploadedFile::fake()->image('new.png', 200, 100),
    ])->assertSessionHasNoErrors()->assertRedirect('/admin/products');
    $product->refresh();
    Storage::disk('public')->assertExists($product->warehouse_image_path);
    expect($product->image_url)->toBe(Storage::disk('public')->url($product->warehouse_image_path));
    $this->get('/admin/products')->assertInertia(
        fn (Assert $page) => $page->where('products.data.0.id', $product->id)->where('products.data.0.image_verification', null)
    );
    $this->get("/admin/products/{$product->id}/image-reviews")->assertOk()
        ->assertJsonPath('reviews.data.0.note', 'Wrong variant')
        ->assertJsonPath('reviews.data.0.image_url', 'https://cdn.example.com/old.jpg');
    expect($review->fresh()->status)->toBe('doesnt_match');
});

test('the image status filter returns only products whose current latest review matches', function (string $status) {
    actingAsAdmin();
    Product::factory()->create(['name' => 'Noise']);
    $product = Product::factory()->imageUrl(null)->create();
    ProductImageReview::factory()->create(['product_id' => $product->id, 'status' => 'doesnt_match']);
    ProductImageReview::factory()->create(['product_id' => $product->id, 'status' => $status]);
    $this->get('/admin/products?image_verification='.$status)->assertInertia(
        fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $product->id)->where('filters.image_verification', $status)
    );
})->with(['matches', 'doesnt_match', 'image_missing']);

test('a changed catalog thumbnail requires another review and no longer matches its previous status', function () {
    actingAsAdmin();
    $product = Product::factory()->imageUrl('https://cdn.example.com/old.jpg')->create();
    ProductImageReview::factory()->create(['product_id' => $product->id, 'image_source' => (string) $product->thumbnail_img, 'status' => 'matches']);
    $other = Product::factory()->imageUrl('https://cdn.example.com/new.jpg')->create();
    $product->update(['thumbnail_img' => $other->thumbnail_img]);
    $this->get('/admin/products?image_verification=matches')->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    $this->get('/admin/products')->assertInertia(fn (Assert $page) => $page->has('products.data', 2)
        ->where('products.data.0.image_verification', null)
        ->where('products.data.1.image_verification', null));
});

test('the removed needs review filter is rejected', function () {
    actingAsAdmin();
    $this->get('/admin/products?image_verification=needs_review')
        ->assertSessionHasErrors('image_verification');
});

test('review history is paginated and excludes other products', function () {
    $user = actingAsAdmin();
    $product = Product::factory()->imageUrl(null)->create();
    ProductImageReview::factory()->count(11)->create(['product_id' => $product->id, 'user_id' => $user->id]);
    ProductImageReview::factory()->create();
    $this->get("/admin/products/{$product->id}/image-reviews")->assertOk()->assertJsonCount(10, 'reviews.data')->assertJsonPath('reviews.total', 11);
    $this->get("/admin/products/{$product->id}/image-reviews?page=2")->assertOk()->assertJsonCount(1, 'reviews.data');
});

test('warehouse placement requires a review and stores mismatches atomically with optional notes', function () {
    $user = actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->sole();
    $product = Product::factory()->imageUrl('https://cdn.example.com/reference.jpg')->create();
    $url = "/admin/cells/{$cell->id}/pallet";
    $this->post($url, ['product_id' => $product->id])->assertSessionHasErrors(['image_review_status', 'image_source']);
    expect($cell->fresh()->state)->toBe(CellState::Empty);
    $this->assertDatabaseCount('pallets', 0);
    $this->post($url, ['product_id' => $product->id, 'image_review_status' => 'doesnt_match',
        'image_source' => $product->image_url, 'image_review_note' => 'Different size'])
        ->assertSessionHasNoErrors();
    expect(Pallet::query()->sole()->product_id)->toBe($product->id)
        ->and(ProductImageReview::query()->sole()->note)->toBe('Different size');
    $this->post($url, ['product_id' => $product->id, 'image_review_status' => 'matches', 'image_source' => $product->image_url])->assertSessionHasErrors('action');
    $this->assertDatabaseCount('wms_product_image_reviews', 1);
});

test('mobile placement cannot omit image verification', function () {
    actingAsMobileUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();
    $this->postJson('/api/v1/pallets', ['product_id' => $product->id, 'row_letter' => $row->letter, 'cell_number' => 1, 'flat_number' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('image_review_status');
    $this->assertDatabaseCount('pallets', 0);
    $this->assertDatabaseCount('wms_product_image_reviews', 0);
});

test('image uploads reject unsafe files', function () {
    Storage::fake('public');
    actingAsAdmin();
    $product = Product::factory()->create();
    $this->post("/admin/products/{$product->id}/reference-image", ['image' => UploadedFile::fake()->create('script.svg', 2, 'image/svg+xml')])->assertSessionHasErrors('image');
    expect($product->fresh()->warehouse_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('image review routes reject guests and mobile users without mutations', function (string $caller, string $method, string $suffix) {
    if ($caller === 'mobile') {
        actingAsMobileUser();
    }
    $product = Product::factory()->create();
    $response = $this->{$method}("/admin/products/{$product->id}/{$suffix}", ['image_review_status' => 'image_missing', 'image_source' => null]);
    if ($caller === 'mobile') {
        $response->assertForbidden();
    } else {
        $response->assertRedirect(route('login'));
    }
    $this->assertDatabaseCount('wms_product_image_reviews', 0);
    expect($product->fresh()->warehouse_image_path)->toBeNull();
})->with(['guest', 'mobile'])->with([['get', 'image-reviews'], ['post', 'image-reviews'], ['post', 'reference-image']]);

test('image review routes return 404 for unknown products', function (string $method, string $suffix) {
    actingAsAdmin();
    $this->{$method}('/admin/products/999999/'.$suffix)->assertNotFound();
})->with([['get', 'image-reviews'], ['post', 'image-reviews'], ['post', 'reference-image']]);

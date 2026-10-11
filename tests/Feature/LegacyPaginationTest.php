<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The store's paginated endpoints each hold more than one page of rows, so a
 * `get()` in place of `paginate()` -- or a page that repeats rows of the one
 * before -- shows up as a wrong count on page 1 or an overlap with page 2.
 * Rows that belong to someone else, or that the listing hides, sit beside
 * them so a listing that ignores its scope fails too.
 *
 * Rows get distinct timestamps wherever the listing sorts on one, so the page
 * boundary does not depend on how the database orders ties.
 */

/**
 * A seller with 13 products of their own and one product of another seller,
 * for the two seller API listings that page over a seller's products.
 *
 * @return array{token: string, otherSellersProductId: int}
 */
function sellerWithManyProducts(): array
{
    seedStoreCurrency();
    $seller = createStoreUser('seller');
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0]);

    foreach (range(1, 13) as $number) {
        createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller', 'category_id' => $categoryId, 'thumbnail_img' => null]);
    }

    $otherSellersProduct = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'added_by' => 'seller', 'category_id' => $categoryId, 'thumbnail_img' => null]);

    return ['token' => storeApiToken($seller), 'otherSellersProductId' => $otherSellersProduct->id];
}

test('the product reviews API pages, and leaves out other products and hidden reviews', function () {
    $reviewer = createStoreUser('customer');
    $review = fn (string $comment, int $productId, int $status, int $minutesAgo) => DB::table('reviews')->insert([
        'product_id' => $productId, 'user_id' => $reviewer->id, 'rating' => 4, 'comment' => $comment,
        'status' => $status, 'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo),
    ]);

    foreach (range(1, 13) as $number) {
        $review("Review $number", 7, 1, $number);
    }
    $review('Review of another product', 8, 1, 1);
    $review('Hidden review', 7, 0, 1);

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/reviews/product/7?page=$page"),
        fn ($response) => $response->json('data.*.comment'),
        total: 13,
        perPage: 10,
        excluded: ['Review of another product', 'Hidden review'],
    );
});

test("the purchase history API pages, and lists only the signed-in customer's orders", function () {
    seedStoreCurrency();
    $customer = createStoreUser('customer');

    foreach (range(1, 18) as $number) {
        createStoreOrder($customer, ['code' => "MINE-$number", 'created_at' => now()->subMinutes($number)]);
    }
    createStoreOrder(createStoreUser('customer'), ['code' => 'THEIRS-1']);

    $this->withToken(storeApiToken($customer));

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/purchase-history?page=$page"),
        fn ($response) => $response->json('data.*.code'),
        total: 18,
        perPage: 15,
        excluded: ['THEIRS-1'],
    );
});

test("the wallet history API pages, and lists only the signed-in customer's recharges", function () {
    seedStoreCurrency();

    // The store's wallets table carries these two; this app's stand-in does not yet.
    foreach (['offline_payment', 'approval'] as $column) {
        if (! Schema::hasColumn('wallets', $column)) {
            Schema::table('wallets', fn (Blueprint $table) => $table->integer($column)->default(0));
        }
    }

    $customer = createStoreUser('customer');
    $recharge = fn (int $userId, int $amount, int $minutesAgo) => DB::table('wallets')->insert([
        'user_id' => $userId, 'amount' => $amount, 'payment_method' => 'paypal',
        'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo),
    ]);

    foreach (range(1, 13) as $number) {
        $recharge($customer->id, $number, $number);
    }
    $recharge(createStoreUser('customer')->id, 999, 1);

    $this->withToken(storeApiToken($customer));

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/wallet/history?page=$page"),
        fn ($response) => $response->json('data.*.amount'),
        total: 13,
        perPage: 10,
        excluded: [format_price(999)],
    );
});

test('the product list API pages, and leaves out unpublished and unapproved products', function () {
    seedStoreCurrency();

    foreach (range(1, 13) as $number) {
        createStoreProduct(['created_at' => now()->subMinutes($number)]);
    }
    $unpublished = createStoreProduct(['published' => 0]);
    $unapproved = createStoreProduct(['approved' => 0]);

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/products?page=$page"),
        fn ($response) => $response->json('data.*.id'),
        total: 13,
        perPage: 10,
        excluded: [$unpublished->id, $unapproved->id],
    );
});

test('the shop list API pages, and leaves out shops that are not verified', function () {
    // A shop counts as verified through its owner, so the unverified shop needs its own owner.
    $shop = fn (string $name, int $verified) => DB::table('shops')->insertGetId([
        'user_id' => createStoreUser('seller')->id, 'name' => $name, 'verification_status' => $verified,
    ]);

    foreach (range(1, 13) as $number) {
        $shop("Shop $number", 1);
    }
    $unverifiedShopId = $shop('Unverified shop', 0);

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/shops?page=$page"),
        fn ($response) => $response->json('data.*.id'),
        total: 13,
        perPage: 10,
        excluded: [$unverifiedShopId],
    );
});

test("the seller order list API pages, and lists only the signed-in seller's orders", function () {
    seedStoreCurrency();
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');

    foreach (range(1, 13) as $number) {
        createStoreOrder($customer, ['seller_id' => $seller->id, 'code' => "SELLER-$number", 'created_at' => now()->subMinutes($number)]);
    }
    $otherSellersOrder = createStoreOrder($customer, ['seller_id' => createStoreUser('seller')->id, 'code' => 'OTHER-1']);

    $this->withToken(storeApiToken($seller));

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/seller/orders?page=$page"),
        fn ($response) => $response->json('data.*.id'),
        total: 13,
        perPage: 10,
        excluded: [$otherSellersOrder->id],
    );
});

test("the seller product list API pages, and lists only the signed-in seller's products", function () {
    ['token' => $token, 'otherSellersProductId' => $otherSellersProductId] = sellerWithManyProducts();

    $this->withToken($token);

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/seller/products/all?page=$page"),
        fn ($response) => $response->json('data.*.id'),
        total: 13,
        perPage: 10,
        excluded: [$otherSellersProductId],
    );
});

test("the seller coupon product search API pages, and searches only the signed-in seller's products", function () {
    ['token' => $token, 'otherSellersProductId' => $otherSellersProductId] = sellerWithManyProducts();

    $this->withToken($token);

    assertPagesWithoutOverlap(
        fn (int $page) => $this->getJson("/temp/api/v2/seller/coupon/for-product?coupon_type=product_base&page=$page"),
        fn ($response) => $response->json('data.*.id'),
        total: 13,
        perPage: 10,
        excluded: [$otherSellersProductId],
    );
});

test('the store admin flash deal list pages', function () {
    actingAsStoreUser(createStoreAdmin());

    foreach (range(1, 18) as $number) {
        DB::table('flash_deals')->insert([
            'title' => "Deal $number", 'slug' => "deal-$number", 'start_date' => now()->subDay()->timestamp,
            'end_date' => now()->addDay()->timestamp, 'created_at' => now()->subMinutes($number),
        ]);
    }

    assertPagesWithoutOverlap(
        fn (int $page) => $this->get("/temp/admin/flash_deals?page=$page"),
        fn ($response) => $response->viewData('flash_deals')->pluck('id')->all(),
        total: 18,
        perPage: 15,
    );
});

test('the store admin review list pages', function () {
    actingAsStoreUser(createStoreAdmin());
    $reviewer = createStoreUser('customer');

    foreach (range(1, 18) as $number) {
        DB::table('reviews')->insert([
            'product_id' => 1, 'user_id' => $reviewer->id, 'rating' => 5, 'comment' => "Review $number",
            'created_at' => now()->subMinutes($number), 'updated_at' => now()->subMinutes($number),
        ]);
    }

    assertPagesWithoutOverlap(
        fn (int $page) => $this->get("/temp/admin/reviews?page=$page"),
        fn ($response) => $response->viewData('reviews')->pluck('id')->all(),
        total: 18,
        perPage: 15,
    );
});

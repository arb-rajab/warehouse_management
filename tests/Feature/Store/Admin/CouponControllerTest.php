<?php

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function storeCouponForm(array $overrides = []): array
{
    return [
        'type' => 'cart_base', 'code' => 'SPRING', 'discount' => 10, 'discount_type' => 'percent',
        'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'min_buy' => 20, 'max_discount' => 5,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->couponOwner = createStoreUser('admin');
});

test('coupon list exposes the full form contract and excludes seller coupons', function () {
    actingAsStoreUser(createStoreStaff('view_all_coupons'));
    $coupon = couponFor($this->couponOwner, ['code' => 'SPRING', 'discount' => 10, 'discount_type' => 'percent',
        'details' => json_encode(['min_buy' => 20, 'max_discount' => 5]),
        'start_date' => strtotime('2026-10-01'), 'end_date' => strtotime('2026-10-31')]);
    couponFor(createStoreUser('seller'));

    $this->get(route('coupon.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Marketing/Coupons/Index')
        ->where('filters', ['per_page' => 20])
        ->has('coupons.data', 1)
        ->has('coupons.data.0', fn (Assert $row) => $row
            ->where('id', $coupon->id)->where('type', 'cart_base')->where('code', 'SPRING')
            ->where('discount', 10)->where('discount_type', 'percent')
            ->where('start_date', '2026-10-01')->where('end_date', '2026-10-31')
            ->where('product_ids', [])->where('min_buy', 20)->where('max_discount', 5)));
});

test('coupon list paginates and validates the allowed page sizes', function () {
    actingAsStoreUser(createStoreStaff('view_all_coupons'));
    foreach (range(1, 23) as $number) {
        couponFor($this->couponOwner);
    }
    $first = $this->get(route('coupon.index'))->assertOk()->inertiaProps('coupons');
    $second = $this->get(route('coupon.index', ['page' => 2]))->assertOk()->inertiaProps('coupons');
    expect($first['data'])->toHaveCount(20)->and($second['data'])->toHaveCount(3)
        ->and(collect($first['data'])->pluck('id')->intersect(collect($second['data'])->pluck('id')))->toBeEmpty();
    $this->get(route('coupon.index', ['per_page' => 10]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('coupons.data', 10)->where('filters.per_page', 10));
    $this->get(route('coupon.index', ['per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('coupon create and edit render narrow selected translated product options', function () {
    actingAsStoreUser(createStoreStaff('add_coupon', 'edit_coupon'));
    $product = createStoreProduct(['user_id' => $this->couponOwner->id, 'added_by' => 'admin', 'published' => 1]);
    DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'en', 'name' => 'Translated product']);
    createStoreProduct(['user_id' => $this->couponOwner->id, 'added_by' => 'admin', 'published' => 1]);
    $coupon = couponFor($this->couponOwner, ['type' => 'product_base', 'details' => json_encode([['product_id' => $product->id]])]);
    $this->get(route('coupon.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Marketing/Coupons/Create')->where('selectedProducts', []));
    $this->get(route('coupon.edit', $coupon))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Marketing/Coupons/Edit')
        ->has('coupon', fn (Assert $row) => $row
            ->where('id', $coupon->id)->where('type', 'product_base')->where('code', $coupon->code)
            ->where('discount', 50)->where('discount_type', 'amount')
            ->where('start_date', date('Y-m-d', $coupon->start_date))->where('end_date', date('Y-m-d', $coupon->end_date))
            ->where('product_ids', [$product->id])->where('min_buy', null)->where('max_discount', null))
        ->where('selectedProducts', [['id' => $product->id, 'name' => 'Translated product']]));
});

test('editing retains selected unpublished products and validation refuses them without changing targeting', function () {
    actingAsStoreUser(createStoreStaff('edit_coupon'));
    $product = createStoreProduct(['user_id' => $this->couponOwner->id, 'added_by' => 'admin', 'published' => 0]);
    $coupon = couponFor($this->couponOwner, ['type' => 'product_base', 'details' => json_encode([['product_id' => $product->id]])]);
    $this->get(route('coupon.edit', $coupon))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selectedProducts', [['id' => $product->id, 'name' => $product->name]])
        ->where('coupon.product_ids', [$product->id]));
    $this->put(route('coupon.update', $coupon), storeCouponForm(['type' => 'product_base', 'product_ids' => [$product->id]]))
        ->assertSessionHasErrors('product_ids.0');
    expect(json_decode($coupon->fresh()->details, true))->toBe([['product_id' => $product->id]]);
});

test('coupon product search is translated bounded and scoped to eligible shop products', function () {
    actingAsStoreUser(createStoreStaff('add_coupon'));
    $product = createStoreProduct(['user_id' => $this->couponOwner->id, 'name' => 'Base name', 'added_by' => 'admin', 'published' => 1]);
    DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'en', 'name' => 'Translated target']);
    createStoreProduct(['user_id' => $this->couponOwner->id, 'name' => 'Translated target', 'added_by' => 'admin', 'published' => 0]);
    createStoreProduct(['user_id' => createStoreUser('seller')->id, 'name' => 'Translated target', 'published' => 1]);
    $this->getJson(route('coupon.product-options', ['q' => 'Translated']))->assertOk()->assertExactJson([
        ['id' => $product->id, 'name' => 'Translated target'],
    ]);
    foreach (range(1, 32) as $number) {
        createStoreProduct(['user_id' => $this->couponOwner->id, 'added_by' => 'admin', 'published' => 1]);
    }
    $this->getJson(route('coupon.product-options'))->assertOk()->assertJsonCount(30);
    $this->getJson(route('coupon.product-options', ['q' => str_repeat('x', 256)]))->assertUnprocessable()->assertJsonValidationErrors('q');
});

test('storing coupon derives dates details and ownership and updating changes targeting', function () {
    actingAsStoreUser(createStoreStaff('add_coupon', 'edit_coupon'));
    $this->post(route('coupon.store'), storeCouponForm(['user_id' => 999, 'details' => 'forged', 'date_range' => 'forged']))
        ->assertRedirect(route('coupon.index'));
    $coupon = Coupon::firstOrFail();
    expect((int) $coupon->user_id)->toBe($this->couponOwner->id)
        ->and((int) $coupon->start_date)->toBe(strtotime('2026-10-01'))
        ->and((int) $coupon->end_date)->toBe(strtotime('2026-10-31'))
        ->and(json_decode($coupon->details, true))->toBe(['min_buy' => 20, 'max_discount' => 5]);
    $product = createStoreProduct(['user_id' => $this->couponOwner->id, 'added_by' => 'admin', 'published' => 1]);
    $this->put(route('coupon.update', $coupon), storeCouponForm(['type' => 'product_base', 'product_ids' => [$product->id], 'discount_type' => 'amount', 'discount' => 2.5]))
        ->assertRedirect(route('coupon.index'));
    expect($coupon->fresh()->type)->toBe('product_base')
        ->and((float) $coupon->fresh()->discount)->toBe(2.5)
        ->and(json_decode($coupon->fresh()->details, true))->toBe([['product_id' => $product->id]]);
});

test('coupon forms reject malformed input without changing stored records', function (array $override, string $error) {
    actingAsStoreUser(createStoreStaff('add_coupon', 'edit_coupon'));
    $coupon = couponFor($this->couponOwner, ['code' => 'SPRING']);
    $before = $coupon->fresh()->getAttributes();
    $this->post(route('coupon.store'), storeCouponForm(['code' => 'NEW', ...$override]))->assertSessionHasErrors($error);
    $this->put(route('coupon.update', $coupon), storeCouponForm($override))->assertSessionHasErrors($error);
    expect(Coupon::count())->toBe(1)->and($coupon->fresh()->getAttributes())->toBe($before);
})->with([
    'unsupported type' => [['type' => 'other'], 'type'],
    'blank code' => [['code' => ''], 'code'],
    'long code' => [['code' => str_repeat('x', 256)], 'code'],
    'negative discount' => [['discount' => -1], 'discount'],
    'too large percent' => [['discount' => 101], 'discount'],
    'unknown discount type' => [['discount_type' => 'other'], 'discount_type'],
    'malformed date' => [['start_date' => 'bad'], 'start_date'],
    'impossible date' => [['start_date' => '2026-02-30'], 'start_date'],
    'reversed dates' => [['end_date' => '2026-09-01'], 'end_date'],
    'no products' => [['type' => 'product_base', 'product_ids' => []], 'product_ids'],
    'missing product' => [['type' => 'product_base', 'product_ids' => [999999]], 'product_ids.0'],
    'zero minimum' => [['min_buy' => 0], 'min_buy'],
    'missing cap' => [['max_discount' => null], 'max_discount'],
]);

test('coupon forms refuse duplicate codes and foreign unpublished or repeated product ids', function () {
    actingAsStoreUser(createStoreStaff('add_coupon', 'edit_coupon'));
    $coupon = couponFor($this->couponOwner, ['code' => 'OLD']);
    couponFor(createStoreUser('seller'), ['code' => 'TAKEN']);
    $this->post(route('coupon.store'), storeCouponForm(['code' => 'TAKEN']))->assertSessionHasErrors('code');
    $this->put(route('coupon.update', $coupon), storeCouponForm(['code' => 'TAKEN']))->assertSessionHasErrors('code');
    $foreign = createStoreProduct(['user_id' => createStoreUser('seller')->id, 'published' => 1]);
    $unpublished = createStoreProduct(['user_id' => $this->couponOwner->id, 'published' => 0, 'added_by' => 'admin']);
    $eligible = createStoreProduct(['user_id' => $this->couponOwner->id, 'published' => 1, 'added_by' => 'admin']);
    foreach ([[$foreign->id], [$unpublished->id], [$eligible->id, $eligible->id]] as $ids) {
        $this->post(route('coupon.store'), storeCouponForm(['type' => 'product_base', 'product_ids' => $ids]))->assertSessionHasErrors('product_ids.0');
    }
    expect(Coupon::count())->toBe(2)->and($coupon->fresh()->code)->toBe('OLD');
});

test('coupon delete preserves query and leaves other records intact', function () {
    actingAsStoreUser(createStoreStaff('delete_coupon'));
    $coupon = couponFor($this->couponOwner);
    $other = couponFor($this->couponOwner);
    $this->delete(route('coupon.destroy', [$coupon, 'per_page' => 10, 'page' => 2]))
        ->assertRedirect(route('coupon.index', ['per_page' => 10, 'page' => 2]));
    expect(Coupon::find($coupon->id))->toBeNull()->and(Coupon::find($other->id))->not->toBeNull();
});

test('coupon actions return 404 for missing records', function () {
    actingAsStoreUser(createStoreStaff('edit_coupon', 'delete_coupon'));
    $this->get(route('coupon.edit', 999999))->assertNotFound();
    $this->put(route('coupon.update', 999999), storeCouponForm())->assertNotFound();
    $this->delete(route('coupon.destroy', 999999))->assertNotFound();
});

test('admin cannot edit update or delete a seller coupon', function () {
    actingAsStoreUser(createStoreAdmin());
    $coupon = couponFor(createStoreUser('seller'));
    $before = $coupon->fresh()->getAttributes();
    $this->get(route('coupon.edit', $coupon))->assertForbidden();
    $this->put(route('coupon.update', $coupon), storeCouponForm())->assertForbidden();
    $this->delete(route('coupon.destroy', $coupon))->assertForbidden();
    expect($coupon->fresh()->getAttributes())->toBe($before);
});

test('coupon actions refuse callers without permissions and writes leave the database unchanged', function (string $caller) {
    $coupon = couponFor($this->couponOwner);
    $before = $coupon->fresh()->getAttributes();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('coupon.index')), $caller);
    assertStoreCallerRefused($this->get(route('coupon.create')), $caller);
    assertStoreCallerRefused($this->get(route('coupon.edit', $coupon)), $caller);
    assertStoreCallerRefused($this->get(route('coupon.product-options')), $caller);
    assertStoreCallerRefused($this->post(route('coupon.store'), storeCouponForm()), $caller);
    expect(Coupon::count())->toBe(1);
    assertStoreCallerRefused($this->put(route('coupon.update', $coupon), storeCouponForm()), $caller);
    expect($coupon->fresh()->getAttributes())->toBe($before);
    assertStoreCallerRefused($this->delete(route('coupon.destroy', $coupon)), $caller);
    expect(Coupon::count())->toBe(1)->and($coupon->fresh()->getAttributes())->toBe($before);
})->with('refused store admin callers');

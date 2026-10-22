<?php

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductsImport;
use App\Services\ProductSpreadsheetImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function sellerCouponInput(array $overrides = []): array
{
    return ['type' => 'cart_base', 'code' => 'SAVE10', 'discount' => 10, 'discount_type' => 'amount', 'min_buy' => 20, 'max_discount' => 10, 'date_range' => '10/22/2026 - 10/23/2026', ...$overrides];
}

test('seller coupon create and update refuse hostile input with no writes', function (array $input, string $field, string $action) {
    $seller = sellerAccount();
    $coupon = sellerCoupon($seller);
    $before = DB::table('coupons')->get()->toJson();
    $route = $action === 'store' ? route('seller.coupon.store') : route('seller.coupon.update', $coupon);
    $this->{$action === 'store' ? 'post' : 'patch'}($route, sellerCouponInput($input))->assertSessionHasErrors([$field]);
    expect(DB::table('coupons')->get()->toJson())->toBe($before);
})->with([
    'type array' => [['type' => ['cart_base']], 'type'], 'type unknown' => [['type' => 'forged'], 'type'],
    'code array' => [['code' => ['SAVE']], 'code'], 'code long' => [['code' => str_repeat('é', 256)], 'code'],
    'discount array' => [['discount' => [1]], 'discount'], 'discount zero' => [['discount' => 0], 'discount'],
    'discount overflow' => [['discount' => '1e999'], 'discount'],
    'discount rounding overflow' => [['discount' => '999999999999999999.999'], 'discount'], 'discount too large' => [['discount' => '1000000000000000000'], 'discount'],
    'discount markup' => [['discount' => '<b>10</b>'], 'discount'], 'percent overflow' => [['discount_type' => 'percent', 'discount' => 100.01], 'discount'],
    'discount type array' => [['discount_type' => ['percent']], 'discount_type'], 'discount type forged' => [['discount_type' => 'forged'], 'discount_type'],
    'date array' => [['date_range' => ['10/22/2026']], 'date_range'], 'date malformed' => [['date_range' => 'tomorrow'], 'date_range'],
    'date impossible' => [['date_range' => '02/30/2026 - 03/01/2026'], 'date_range'], 'date reversed' => [['date_range' => '10/23/2026 - 10/22/2026'], 'date_range'],
    'date overflow' => [['date_range' => '01/01/2040 - 01/01/2041'], 'date_range'],
    'minimum array' => [['min_buy' => [20]], 'min_buy'], 'minimum negative' => [['min_buy' => -1], 'min_buy'],
    'maximum array' => [['max_discount' => [20]], 'max_discount'], 'maximum overflow' => [['max_discount' => '1e999'], 'max_discount'],
    'forged owner' => [['user_id' => 999999], 'user_id'], 'forged details' => [['details' => 'forged'], 'details'],
    'forged start' => [['start_date' => 1], 'start_date'], 'forged end' => [['end_date' => 1], 'end_date'],
    'unknown empty' => [['unexpected' => null], 'unexpected'], 'hidden product ids' => [['product_ids' => [1]], 'product_ids'],
])->with(['store', 'update']);

test('seller coupon product ids belong to caller on create and update', function (string $ownerType, string $action) {
    $seller = sellerAccount();
    $other = createStoreUser($ownerType);
    $product = createStoreProduct(['user_id' => $other->id, 'added_by' => 'seller']);
    $coupon = sellerCoupon($seller);
    $before = DB::table('coupons')->get()->toJson();
    $input = ['type' => 'product_base', 'code' => 'NEW', 'discount' => 1, 'discount_type' => 'percent', 'date_range' => '10/22/2026 - 10/22/2026', 'product_ids' => [$product->id]];
    $this->{$action === 'store' ? 'post' : 'patch'}($action === 'store' ? route('seller.coupon.store') : route('seller.coupon.update', $coupon), $input)->assertSessionHasErrors('product_ids.0');
    expect(DB::table('coupons')->get()->toJson())->toBe($before);
})->with(['seller', 'customer'])->with(['store', 'update']);

test('seller coupon cannot update another account coupon even with malformed dates', function (string $type) {
    sellerAccount();
    $coupon = sellerCoupon(createStoreUser($type));
    $before = $coupon->getAttributes();
    $this->patch(route('seller.coupon.update', $coupon), sellerCouponInput(['date_range' => []]))->assertForbidden();
    expect($coupon->fresh()->getAttributes())->toBe($before);
})->with(['seller', 'customer']);

test('seller coupon preserves uniqueness and accepts own code and column boundaries', function () {
    $seller = sellerAccount();
    $coupon = sellerCoupon($seller);
    $this->patch(route('seller.coupon.update', $coupon), sellerCouponInput(['code' => $coupon->code, 'discount' => 100, 'discount_type' => 'percent']))->assertRedirect(route('seller.coupon.index'))->assertSessionHasNoErrors();
    expect(flashedMessage())->toBe('Coupon has been updated successfully');
    $this->post(route('seller.coupon.store'), sellerCouponInput(['code' => $coupon->code]))->assertSessionHasErrors('code');
    $this->post(route('seller.coupon.store'), sellerCouponInput(['code' => str_repeat('é', 255), 'discount' => '999999999999999999.99']))->assertRedirect(route('seller.coupon.index'))->assertSessionHasNoErrors();
    expect(Coupon::query()->where('code', str_repeat('é', 255))->value('user_id'))->toBe($seller->id);
});

test('seller coupon product form can be submitted and old input survives a refusal', function () {
    $seller = sellerAccount();
    $product = createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller']);
    $input = ['type' => 'product_base', 'code' => '<b>SAVE</b>', 'discount' => 1, 'discount_type' => 'amount', 'date_range' => '10/22/2026 - 10/22/2026', 'product_ids' => [$product->id]];
    $this->post(route('seller.coupon.store'), $input)->assertSessionHasNoErrors();
    expect(Coupon::query()->where('code', '<b>SAVE</b>')->value('details'))->toBe(json_encode([['product_id' => $product->id]]));
    $this->from(route('seller.coupon.create'))->post(route('seller.coupon.store'), [...$input, 'discount' => 0]);
    $response = $this->get(route('seller.coupon.create'))->assertOk()->assertSee('&lt;b&gt;SAVE&lt;/b&gt;', false);
    $xpath = htmlXPath($response);
    $form = formPostingTo($xpath, route('seller.coupon.store'));
    expect($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)->and($xpath->query('.//*[contains(@class,"invalid-feedback")]', $form)->length)->toBeGreaterThan(0);
});

test('seller bulk upload rejects invalid files and unknown ids before importer writes', function (string $kind) {
    sellerAccount();
    $input = match ($kind) {
        'absent' => [], 'array' => ['bulk_file' => ['x']], 'reference' => ['bulk_file' => '1'],
        'html' => ['bulk_file' => UploadedFile::fake()->createWithContent('file.xlsx', '<b>HTML</b>')],
        'oversized' => ['bulk_file' => UploadedFile::fake()->create('large.xlsx', 10241)],
        default => ['bulk_file' => UploadedFile::fake()->createWithContent('file.csv', "name,unit\nExample,box"), 'user_id' => createStoreUser('seller')->id],
    };
    $before = [Product::count(), DB::table('uploads')->count(), DB::table('product_stocks')->count()];
    $this->post(route('seller.bulk_product_upload'), $input)->assertSessionHasErrors($kind === 'owner' ? 'user_id' : 'bulk_file');
    expect([Product::count(), DB::table('uploads')->count(), DB::table('product_stocks')->count()])->toBe($before);
})->with(['absent', 'array', 'reference', 'html', 'oversized', 'owner']);

test('seller bulk importer keeps ownership and the existing sanitized row contract', function () {
    $seller = sellerAccount();
    $other = createStoreUser('seller');
    $unrelated = createStoreProduct(['user_id' => $other->id, 'name' => 'Other sellers product']);
    $before = $unrelated->fresh()->getAttributes();
    $row = catalogueImportRow(['description' => '<b>Keep</b>']);
    $this->from(route('seller.product_bulk_upload.index'))->post(route('seller.bulk_product_upload'), ['bulk_file' => catalogueImportFile([$row])])->assertRedirect(route('seller.product_bulk_upload.index'))->assertSessionHasNoErrors();
    $product = Product::query()->where('name', $row['name'])->sole();
    expect($product->user_id)->toBe($seller->id)->and($product->getAttribute('description'))->toBe('<b>Keep</b>')->and($unrelated->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'qty' => 12, 'price' => 2.5]);
});

test('seller bulk form marks its file and displays refusal errors', function () {
    $seller = sellerAccount();
    DB::table('shops')->where('user_id', $seller->id)->update(['verification_status' => 1]);
    $this->from(route('seller.product_bulk_upload.index'))->post(route('seller.bulk_product_upload'));
    $xpath = htmlXPath($this->get(route('seller.product_bulk_upload.index'))->assertOk());
    $form = formPostingTo($xpath, route('seller.bulk_product_upload'));
    expect(formFieldMarks($xpath, $form)['bulk_file'])->toMatchArray(['mark' => true, 'required' => true])
        ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(@class,"invalid-feedback")]', $form)->length)->toBeGreaterThan(0);
});

test('seller coupon missing route id returns 404', function () {
    sellerAccount();
    $this->patch(route('seller.coupon.update', 999999), sellerCouponInput())->assertNotFound();
});

test('seller bulk row validation appears under the file and rolls back the entire import', function () {
    sellerAccount();
    $row = catalogueImportRow();
    $before = [Product::count(), DB::table('uploads')->count(), DB::table('product_stocks')->count()];
    $this->post(route('seller.bulk_product_upload'), ['bulk_file' => catalogueImportFile([$row, [...$row, 'unit_price' => -1]])])->assertSessionHasErrors('bulk_file');
    expect([Product::count(), DB::table('uploads')->count(), DB::table('product_stocks')->count()])->toBe($before);
});

test('seller coupon codes are unique across owners', function () {
    $seller = sellerAccount();
    $coupon = sellerCoupon($seller);
    $other = sellerCoupon(createStoreUser('seller'));
    $other->update(['code' => 'OTHER']);
    $before = DB::table('coupons')->get()->toJson();
    $this->post(route('seller.coupon.store'), sellerCouponInput(['code' => 'OTHER']))->assertSessionHasErrors('code');
    $this->patch(route('seller.coupon.update', $coupon), sellerCouponInput(['code' => 'OTHER']))->assertSessionHasErrors('code');
    expect(DB::table('coupons')->get()->toJson())->toBe($before);
});

test('seller coupon date boundaries fit the signed timestamp columns', function () {
    sellerAccount();
    $this->post(route('seller.coupon.store'), sellerCouponInput(['date_range' => '12/14/1901 - 01/19/2038']))->assertSessionHasNoErrors();
    foreach (['12/13/1901 - 01/19/2038', '12/14/1901 - 01/20/2038'] as $range) {
        $this->post(route('seller.coupon.store'), sellerCouponInput(['code' => 'NEW', 'date_range' => $range]))->assertSessionHasErrors('date_range');
    }
    expect(Coupon::count())->toBe(1);
});

test('commerce writes refuse guests and customers with no writes', function (string $action, string $caller) {
    if ($caller === 'customer') {
        actingAsStoreUser(createStoreUser('customer'));
    }
    $before = [Coupon::count(), Product::count(), DB::table('uploads')->count()];
    $response = $action === 'update' ? $this->patch(route('seller.coupon.update', 999999), sellerCouponInput()) : $this->post(route($action === 'store' ? 'seller.coupon.store' : 'seller.bulk_product_upload'), sellerCouponInput());
    $response->assertNotFound();
    expect([Coupon::count(), Product::count(), DB::table('uploads')->count()])->toBe($before);
})->with(['store', 'update', 'bulk'])->with(['guest', 'customer']);

test('seller bulk file size accepts exactly ten MiB and refuses one byte more before importing', function () {
    sellerAccount();
    $limit = 10 * 1024 * 1024;
    $this->mock(ProductSpreadsheetImporter::class, function ($mock) use ($limit) {
        $mock->shouldReceive('import')->once()->withArgs(fn ($import, $file) => $import instanceof ProductsImport && $file->getSize() === $limit);
    });
    $header = "name,unit\n";
    $row = "Example,box\n";
    $contents = $header.str_repeat($row, intdiv($limit - strlen($header), strlen($row)));
    $contents .= str_repeat(' ', $limit - strlen($contents));
    $this->post(route('seller.bulk_product_upload'), ['bulk_file' => UploadedFile::fake()->createWithContent('boundary.csv', $contents)])->assertSessionHasNoErrors();
    $this->post(route('seller.bulk_product_upload'), ['bulk_file' => UploadedFile::fake()->createWithContent('overflow.csv', $contents.' ')])->assertSessionHasErrors('bulk_file');
    expect(Product::count())->toBe(0);
});

<?php

use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function sellerShopAccount(): StoreUser
{
    $seller = sellerAccount();
    DB::table('shops')->where('user_id', $seller->id)->update(['slug' => 'shop-'.$seller->id]);

    return $seller;
}

function sellerShopInput(array $overrides = []): array
{
    return ['name' => 'Updated shop', 'address' => 'Shop address', ...$overrides];
}

function seedSellerPanelVerificationForm(): void
{
    DB::table('business_settings')->insert(['type' => 'verification_form', 'value' => json_encode([
        ['type' => 'text', 'label' => 'Company'], ['type' => 'file', 'label' => 'Document'],
        ['type' => 'select', 'label' => 'Kind', 'options' => '["Company","Individual"]'],
        ['type' => 'multi_select', 'label' => 'Regions', 'options' => '["North","South"]'],
        ['type' => 'radio', 'label' => 'Registered', 'options' => '["Yes","No"]'],
    ])]);
}

function sellerVerificationInput(array $overrides = []): array
{
    return ['element_0' => 'Acme', 'element_1' => UploadedFile::fake()->createWithContent('extract.pdf', "%PDF-1.4\n%%EOF"), 'element_2' => 'Company', 'element_3' => ['North'], 'element_4' => 'Yes', ...$overrides];
}

test('shop accepts column boundaries and preserves another sellers shop', function () {
    $seller = sellerShopAccount();
    $otherId = createShopFor(createStoreUser('seller'), 200);
    $before = (array) DB::table('shops')->find($otherId);
    $image = Upload::factory()->create(['user_id' => $seller->id, 'type' => 'image']);
    $this->from(route('seller.shop.index'))->post(route('seller.shop.update'), sellerShopInput([
        'name' => str_repeat('é', 200), 'address' => str_repeat('é', 500), 'phone' => str_repeat('1', 255),
        'meta_title' => str_repeat('é', 255), 'meta_description' => str_repeat('a', 65535), 'logo' => $image->id,
    ]))->assertRedirect(route('seller.shop.index'))->assertSessionHasNoErrors();
    expect(mb_strlen($seller->shop->fresh()->name))->toBe(200)->and($seller->shop->fresh()->logo)->toEqual($image->id)
        ->and((array) DB::table('shops')->find($otherId))->toBe($before)->and(flashedMessage())->toBe('Your Shop has been updated successfully!');
});

test('shop refuses hostile input before changing any shop', function (array $input, string $field) {
    sellerShopAccount();
    $before = DB::table('shops')->get()->toJson();
    $this->post(route('seller.shop.update'), $input)->assertSessionHasErrorsIn('sellerShop', [$field]);
    expect(DB::table('shops')->get()->toJson())->toBe($before);
})->with([
    'missing address' => [['name' => 'Shop'], 'address'],
    'name array' => [sellerShopInput(['name' => ['x']]), 'name'], 'name long' => [sellerShopInput(['name' => str_repeat('é', 201)]), 'name'],
    'address array' => [sellerShopInput(['address' => ['x']]), 'address'], 'address long' => [sellerShopInput(['address' => str_repeat('é', 501)]), 'address'],
    'phone array' => [sellerShopInput(['phone' => ['x']]), 'phone'], 'phone long' => [sellerShopInput(['phone' => str_repeat('x', 256)]), 'phone'],
    'title array' => [sellerShopInput(['meta_title' => ['x']]), 'meta_title'], 'title long' => [sellerShopInput(['meta_title' => str_repeat('é', 256)]), 'meta_title'],
    'description array' => [sellerShopInput(['meta_description' => ['x']]), 'meta_description'], 'description long' => [sellerShopInput(['meta_description' => str_repeat('a', 65536)]), 'meta_description'],
    'description bytes' => [sellerShopInput(['meta_description' => str_repeat('é', 32768)]), 'meta_description'],
    'logo array' => [sellerShopInput(['logo' => [1]]), 'logo'], 'logo absent' => [sellerShopInput(['logo' => 999999]), 'logo'],
    'hidden shipping' => [sellerShopInput(['shipping_cost' => 10]), 'shipping_cost'],
    'mixed sections' => [sellerShopInput(['facebook' => 'https://example.com']), 'facebook'], 'unknown empty' => [sellerShopInput(['unknown' => null]), 'unknown'],
    'latitude array' => [['delivery_pickup_latitude' => [1], 'delivery_pickup_longitude' => 1], 'delivery_pickup_latitude'],
    'latitude past pole' => [['delivery_pickup_latitude' => 90.001, 'delivery_pickup_longitude' => 1], 'delivery_pickup_latitude'],
    'longitude array' => [['delivery_pickup_latitude' => 1, 'delivery_pickup_longitude' => [1]], 'delivery_pickup_longitude'],
    'longitude past meridian' => [['delivery_pickup_latitude' => 1, 'delivery_pickup_longitude' => -180.001], 'delivery_pickup_longitude'],
    'latitude markup' => [['delivery_pickup_latitude' => '<b>1</b>', 'delivery_pickup_longitude' => 1], 'delivery_pickup_latitude'],
    'half point' => [['delivery_pickup_latitude' => 1], 'delivery_pickup_longitude'],
    'url array' => [['facebook' => ['https://example.com']], 'facebook'], 'url markup' => [['facebook' => '<b>link</b>'], 'facebook'],
    'url long' => [['facebook' => 'https://example.com/'.str_repeat('a', 236)], 'facebook'],
    'banner array' => [['top_banner' => [1]], 'top_banner'], 'banner long' => [['banner_full_width_1' => str_repeat('1', 192)], 'banner_full_width_1'],
]);

test('shop refuses another seller or customers ids', function (string $type, string $field) {
    sellerShopAccount();
    $other = createStoreUser($type);
    $otherShop = createShopFor($other, 200);
    $before = DB::table('shops')->get()->toJson();
    $this->post(route('seller.shop.update'), sellerShopInput([$field => $field === 'shop_id' ? $otherShop : $other->id]))->assertSessionHasErrorsIn('sellerShop', [$field]);
    expect(DB::table('shops')->get()->toJson())->toBe($before);
})->with(['seller', 'customer'])->with(['shop_id', 'user_id']);

test('shop image fields require the sellers own image', function (string $field, string $type) {
    $seller = sellerShopAccount();
    $owner = $type === 'document' ? $seller : createStoreUser($type);
    $image = Upload::factory()->create(['user_id' => $owner->id, 'type' => $type === 'document' ? 'document' : 'image']);
    $before = DB::table('shops')->get()->toJson();
    $this->post(route('seller.shop.update'), $field === 'logo' ? sellerShopInput([$field => $image->id]) : [$field => (string) $image->id])->assertSessionHasErrorsIn('sellerShop', [$field]);
    expect(DB::table('shops')->get()->toJson())->toBe($before);
})->with(['logo', 'top_banner', 'sliders', 'banner_full_width_1', 'banners_half_width', 'banner_full_width_2'])->with(['seller', 'customer', 'document']);

test('shop accepts own banners URLs and map boundaries', function () {
    $seller = sellerShopAccount();
    $images = Upload::factory()->count(2)->create(['user_id' => $seller->id, 'type' => 'image'])->pluck('id')->implode(',');
    $this->post(route('seller.shop.update'), ['sliders' => $images])->assertSessionHasNoErrors();
    expect($seller->shop->fresh()->sliders)->toBe($images);
    foreach ([-1, 1] as $sign) {
        $this->post(route('seller.shop.update'), ['delivery_pickup_latitude' => $sign * 90, 'delivery_pickup_longitude' => $sign * 180])->assertSessionHasNoErrors();
        expect((float) $seller->shop->fresh()->delivery_pickup_longitude)->toBe((float) ($sign * 180));
    }
    $url = 'https://example.com/'.str_repeat('a', 235);
    $this->post(route('seller.shop.update'), ['facebook' => $url])->assertSessionHasNoErrors();
    expect($seller->shop->fresh()->facebook)->toBe($url);
});

test('shop shipping requires a bounded number only in seller wise shipping', function () {
    $seller = sellerShopAccount();
    DB::table('business_settings')->insert(['type' => 'shipping_type', 'value' => 'seller_wise_shipping']);
    foreach ([null, -1, [1], '1e999', '1000000000000000000'] as $value) {
        $this->post(route('seller.shop.update'), sellerShopInput(['shipping_cost' => $value]))->assertSessionHasErrorsIn('sellerShop', ['shipping_cost']);
    }
    expect((float) $seller->shop->fresh()->shipping_cost)->toBe(0.0);
    $this->post(route('seller.shop.update'), sellerShopInput(['shipping_cost' => '9999999999999999.99']))->assertSessionHasNoErrors();
});

test('shop form keeps escaped old input errors and honest requirements', function () {
    sellerShopAccount();
    $this->from(route('seller.shop.index'))->post(route('seller.shop.update'), sellerShopInput(['name' => '', 'phone' => '<b>Keep</b>']));
    $xpath = htmlXPath($this->get(route('seller.shop.index'))->assertOk()->assertSee('&lt;b&gt;Keep&lt;/b&gt;', false));
    $forms = $xpath->query('//form[@action="'.route('seller.shop.update').'"]');
    foreach ($forms as $form) {
        foreach (formFieldMarks($xpath, $form) as $name => $field) {
            expect($field['required'])->toBe(in_array($name, ['name', 'address'], true), $name)->and($field['mark'])->toBe(in_array($name, ['name', 'address'], true), $name);
        }
        expect($xpath->query('.//input[@name="shop_id"]', $form)->length)->toBe(0);
    }
    expect($xpath->query('.//*[@data-required-legend]', $forms->item(0))->length)->toBe(1)->and($xpath->query('.//*[contains(@class,"invalid-feedback")]', $forms->item(0))->length)->toBeGreaterThan(0);
});

test('verification accepts configured fields and privately stores the document on the callers shop', function () {
    Storage::fake('local');
    $seller = sellerShopAccount();
    seedSellerPanelVerificationForm();
    $otherShop = createShopFor(createStoreUser('seller'), 200);
    $this->post(route('seller.shop.verify.store'), sellerVerificationInput(['element_0' => str_repeat('é', 10000)]))->assertRedirect(route('seller.dashboard'))->assertSessionHasNoErrors();
    $info = json_decode($seller->shop->fresh()->verification_info, true);
    expect(mb_strlen($info[0]['value']))->toBe(10000)->and($info[1]['value'])->toMatch('/^[A-Za-z0-9]{40}\.pdf$/')->and(DB::table('shops')->where('id', $otherShop)->value('verification_info'))->toBeNull();
    Storage::disk('local')->assertExists('uploads/verification_form/'.$info[1]['value']);
});

test('verification refuses invalid input before storing any file or shop data', function (array $overrides, string $field) {
    Storage::fake('local');
    sellerShopAccount();
    seedSellerPanelVerificationForm();
    $before = DB::table('shops')->get()->toJson();
    $this->post(route('seller.shop.verify.store'), sellerVerificationInput($overrides))->assertSessionHasErrorsIn('sellerVerification', [$field]);
    expect(DB::table('shops')->get()->toJson())->toBe($before)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'missing text' => [['element_0' => ''], 'element_0'], 'text array' => [['element_0' => ['x']], 'element_0'], 'text long' => [['element_0' => str_repeat('é', 10001)], 'element_0'],
    'file absent' => [['element_1' => null], 'element_1'], 'file reference' => [['element_1' => '1'], 'element_1'], 'file array' => [['element_1' => ['1']], 'element_1'],
    'select absent' => [['element_2' => null], 'element_2'], 'select forged' => [['element_2' => 'Forged'], 'element_2'], 'select array' => [['element_2' => ['Company']], 'element_2'],
    'multi scalar' => [['element_3' => 'North'], 'element_3'], 'multi forged' => [['element_3' => ['Forged']], 'element_3.0'], 'multi nested' => [['element_3' => [['North']]], 'element_3.0'],
    'multi repeated' => [['element_3' => ['North', 'North']], 'element_3.0'], 'radio forged' => [['element_4' => 'Forged'], 'element_4'], 'radio array' => [['element_4' => ['Yes']], 'element_4'],
    'unknown field' => [['element_5' => null], 'element_5'], 'owner forged' => [['user_id' => 999999], 'user_id'], 'shop forged' => [['shop_id' => 999999], 'shop_id'],
]);

test('verification refuses wrong content and oversized files before storage', function () {
    Storage::fake('local');
    $seller = sellerShopAccount();
    seedSellerPanelVerificationForm();
    foreach ([UploadedFile::fake()->createWithContent('bad.pdf', '<b>HTML</b>'), UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf')] as $file) {
        $this->post(route('seller.shop.verify.store'), sellerVerificationInput(['element_1' => $file]))->assertSessionHasErrorsIn('sellerVerification', ['element_1']);
        expect($seller->shop->fresh()->verification_info)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
    }
});

test('verification form retains old text errors marks and required controls', function () {
    sellerShopAccount();
    seedSellerPanelVerificationForm();
    $this->from(route('seller.shop.verify'))->post(route('seller.shop.verify.store'), sellerVerificationInput(['element_0' => '<b>Keep</b>', 'element_2' => 'Invalid']));
    $xpath = htmlXPath($this->get(route('seller.shop.verify'))->assertOk()->assertSee('&lt;b&gt;Keep&lt;/b&gt;', false));
    $form = formPostingTo($xpath, route('seller.shop.verify.store'));
    expect($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)->and($xpath->query('.//*[@data-required-mark]', $form)->length)->toBe(5)->and($xpath->query('.//*[contains(@class,"invalid-feedback")]', $form)->length)->toBeGreaterThan(0);
});

test('missing verification configuration refuses an empty application', function () {
    $seller = sellerShopAccount();
    $this->get(route('seller.shop.verify'))->assertOk();
    $this->post(route('seller.shop.verify.store'))->assertSessionHasErrorsIn('sellerVerification', ['verification_form']);
    expect($seller->shop->fresh()->verification_info)->toBeNull();
});

test('shop write endpoints refuse guests and customers without writing', function (string $route, string $caller) {
    if ($caller === 'customer') {
        actingAsStoreUser(createStoreUser('customer'));
    }
    $before = DB::table('shops')->get()->toJson();
    $response = $this->post(route($route), sellerShopInput());
    $response->assertNotFound();
    expect(DB::table('shops')->get()->toJson())->toBe($before);
})->with(['seller.shop.update', 'seller.shop.verify.store'])->with(['guest', 'customer']);

test('shop banner lists accept exactly the varchar boundary and reject overflow and repeats', function () {
    $seller = sellerShopAccount();
    for ($id = 10000; $id < 10033; $id++) {
        Upload::factory()->create(['id' => $id, 'user_id' => $seller->id, 'type' => 'image']);
    }
    $ids = implode(',', range(10000, 10031));
    expect(strlen($ids))->toBe(191);
    $this->post(route('seller.shop.update'), ['banner_full_width_1' => $ids])->assertSessionHasNoErrors();
    $before = $seller->shop->fresh()->getAttributes();
    foreach ([$ids.',10032', '10000,10000'] as $bad) {
        $this->post(route('seller.shop.update'), ['banner_full_width_1' => $bad])->assertSessionHasErrorsIn('sellerShop', ['banner_full_width_1']);
        expect($seller->shop->fresh()->getAttributes())->toBe($before);
    }
});

test('verification validates later documents before storing earlier valid documents', function () {
    Storage::fake('local');
    $seller = sellerShopAccount();
    seedSellerPanelVerificationForm();
    $fields = json_decode(DB::table('business_settings')->where('type', 'verification_form')->value('value'), true);
    $fields[] = ['type' => 'file', 'label' => 'Later document'];
    DB::table('business_settings')->where('type', 'verification_form')->update(['value' => json_encode($fields)]);
    $this->post(route('seller.shop.verify.store'), sellerVerificationInput(['element_5' => UploadedFile::fake()->createWithContent('bad.pdf', '<b>HTML</b>')]))->assertSessionHasErrorsIn('sellerVerification', ['element_5']);
    expect($seller->shop->fresh()->verification_info)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('verification cannot target another account or reference its upload', function (string $type) {
    Storage::fake('local');
    sellerShopAccount();
    seedSellerPanelVerificationForm();
    $other = createStoreUser($type);
    $otherShop = createShopFor($other, 200);
    $image = Upload::factory()->create(['user_id' => $other->id, 'type' => 'image']);
    $before = DB::table('shops')->get()->toJson();
    foreach ([['user_id' => $other->id], ['shop_id' => $otherShop], ['element_1' => (string) $image->id]] as $input) {
        $this->post(route('seller.shop.verify.store'), sellerVerificationInput($input))->assertSessionHasErrorsIn('sellerVerification', array_keys($input));
        expect(DB::table('shops')->get()->toJson())->toBe($before)->and(Storage::disk('local')->allFiles())->toBe([]);
    }
})->with(['seller', 'customer']);

test('pickup point explains the conditional pair and retains numeric limits', function () {
    sellerShopAccount();
    DB::table('addons')->insert(['name' => 'Delivery boy', 'unique_identifier' => 'delivery_boy', 'activated' => 1]);
    $response = $this->get(route('seller.shop.index'))->assertOk()->assertSee('Enter both latitude and longitude, or leave both empty.');
    $xpath = htmlXPath($response);
    expect($xpath->query('//input[@id="latitude"]')->item(0)->getAttribute('min'))->toBe('-90')
        ->and($xpath->query('//input[@id="longitude"]')->item(0)->getAttribute('max'))->toBe('180');
});

test('verification document accepts exactly twenty MiB and refuses one byte more without new files', function () {
    Storage::fake('local');
    $seller = sellerShopAccount();
    seedSellerPanelVerificationForm();
    $limit = 20 * 1024 * 1024;
    $header = "%PDF-1.4\n";
    $contents = $header.str_repeat(' ', $limit - strlen($header));
    $this->post(route('seller.shop.verify.store'), sellerVerificationInput(['element_1' => UploadedFile::fake()->createWithContent('boundary.pdf', $contents)]))->assertSessionHasNoErrors();
    $before = $seller->shop->fresh()->verification_info;
    $files = Storage::disk('local')->allFiles();
    expect($files)->toHaveCount(1)->and(Storage::disk('local')->size($files[0]))->toBe($limit);
    $this->post(route('seller.shop.verify.store'), sellerVerificationInput(['element_1' => UploadedFile::fake()->createWithContent('overflow.pdf', $contents.' ')]))->assertSessionHasErrorsIn('sellerVerification', ['element_1']);
    expect($seller->shop->fresh()->verification_info)->toBe($before)->and(Storage::disk('local')->allFiles())->toBe($files);
});

test('shop meta description reports its byte limit for multibyte text', function () {
    sellerShopAccount();
    $this->post(route('seller.shop.update'), sellerShopInput(['meta_description' => str_repeat('é', 32768)]))
        ->assertSessionHasErrorsIn('sellerShop', ['meta_description' => 'The meta description may not exceed 65,535 bytes.']);
});

test('shop shipping refuses a value that would round beyond decimal storage', function () {
    $seller = sellerShopAccount();
    DB::table('business_settings')->insert(['type' => 'shipping_type', 'value' => 'seller_wise_shipping']);
    $this->post(route('seller.shop.update'), sellerShopInput(['shipping_cost' => '999999999999999999.999']))->assertSessionHasErrorsIn('sellerShop', ['shipping_cost']);
    expect((float) $seller->shop->fresh()->shipping_cost)->toBe(0.0);
    $this->post(route('seller.shop.update'), sellerShopInput(['shipping_cost' => '999999999999999999.99']))->assertSessionHasNoErrors();
});

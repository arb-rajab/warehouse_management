<?php

use App\Http\Middleware\WrapStoreApiResources;
use App\Http\Resources\V2\ConversationCollection;
use App\Http\Resources\V2\CustomerPackageResource;
use App\Http\Resources\V2\DigitalProductDetailCollection;
use App\Http\Resources\V2\PublicShopDetailsCollection;
use App\Http\Resources\V2\PurchaseHistoryMiniCollection;
use App\Http\Resources\V2\RefundRequestCollection;
use App\Http\Resources\V2\Seller\CommissionHistoryResource;
use App\Http\Resources\V2\Seller\SellerPackageResource;
use App\Http\Resources\V2\Seller\SellerPaymentResource;
use App\Http\Resources\V2\Seller\SellerWithdrawResource;
use App\Http\Resources\V2\ShopDetailsCollection;
use App\Http\Resources\V2\WalletCollection;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageTranslation;
use App\Models\SellerPackage;
use App\Models\Shop;
use App\Support\ApiResourceLabel;
use App\Support\StoreLocale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedStoreCurrency();
    DB::table('business_settings')->insert([
        ['type' => 'no_of_decimals', 'value' => '2'],
        ['type' => 'symbol_format', 'value' => '1'],
    ]);
});

/** @return array<string, mixed> */
function apiResourceResponseData(JsonResource $resource): array
{
    $request = request();
    /** @var JsonResponse $response */
    $response = (new WrapStoreApiResources)->handle($request, fn (Request $request): JsonResponse => $resource->response($request));

    return $response->getData(true);
}

dataset('API resource locales', [
    'English' => ['en', 'Free', 'Verified seller', 'Pending', 'Paid'],
    'Arabic' => ['ar', 'مجاني', 'بائع موثّق', 'قيد الانتظار', 'مدفوع'],
    'legacy Arabic content' => ['sa', 'مجاني', 'بائع موثّق', 'قيد الانتظار', 'مدفوع'],
]);

test('mobile package and withdrawal resources use file labels without changing their payloads', function (string $locale, string $free, string $verified, string $pending, string $paid) {
    app()->setLocale($locale);
    app('translator')->setLocale(StoreLocale::uiLocale());
    $package = (new CustomerPackage)->forceFill([
        'id' => 17, 'name' => 'Base package', 'logo' => null, 'product_upload' => 12, 'amount' => 0,
    ])->setRelation('customer_package_translations', new Collection([
        new CustomerPackageTranslation(['lang' => $locale, 'name' => 'Business-owned package']),
    ]));
    $sellerPackage = Mockery::mock(SellerPackage::class)->makePartial();
    $sellerPackage->forceFill(['id' => 23, 'name' => 'Base package', 'logo' => null, 'product_upload_limit' => 8, 'amount' => 0, 'duration' => 30]);
    $sellerPackage->shouldReceive('getTranslation')->once()->with('name')->andReturn('Custom seller package');

    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();
    expect((new CustomerPackageResource($package))->resolve())->toBe([
        'id' => 17, 'name' => 'Business-owned package', 'logo' => static_asset('assets/img/placeholder.jpg'),
        'product_upload_limit' => 12, 'amount' => $free, 'price' => 0.0,
    ])->and((new SellerPackageResource($sellerPackage))->resolve())->toBe([
        'id' => 23, 'name' => 'Custom seller package', 'logo' => static_asset('assets/img/placeholder.jpg'),
        'product_upload_limit' => 8, 'amount' => $free, 'price' => 0.0, 'duration' => 30,
    ]);
    foreach ([0 => $pending, 1 => $paid] as $status => $label) {
        expect((new SellerWithdrawResource((object) ['id' => 31, 'amount' => 12.5, 'status' => $status, 'created_at' => '2026-10-28 14:00:00']))->resolve())->toBe([
            'id' => 31, 'amount' => format_price(12.5), 'status' => $label, 'created_at' => '28-10-2026',
        ]);
    }
    expectNoStorefrontTranslationQueries();
    expect(app()->getLocale())->toBe($locale);
})->with('API resource locales');

test('shop verification labels use files and preserve public and owner response contracts', function (string $locale, string $free, string $verified) {
    app()->setLocale($locale);
    app('translator')->setLocale(StoreLocale::uiLocale());
    $seller = createStoreUser('seller');
    $shopId = createShopFor($seller, 100);
    $shop = Shop::findOrFail($shopId)->forceFill([
        'name' => 'Business shop', 'meta_title' => 'Custom title', 'meta_description' => 'Custom description',
        'sliders' => '', 'verification_status' => 1, 'rating' => 4.5,
    ])->setRelation('seller_package', null);

    startStorefrontTranslationQueryLog();
    $public = apiResourceResponseData(new PublicShopDetailsCollection($shop));
    expect($public)->toBe([
        'data' => [
            'id' => $shopId, 'user_id' => $seller->id, 'name' => 'Business shop', 'title' => 'Custom title',
            'description' => 'Custom description', 'logo' => static_asset('assets/img/placeholder.jpg'),
            'sliders' => [static_asset('assets/img/placeholder.jpg')], 'address' => null,
            'facebook' => null, 'google' => null, 'twitter' => null, 'instagram' => null, 'youtube' => null,
            'rating' => 4.5, 'verified' => true, 'verified_img' => static_asset('assets/img/verified.png'),
            'verify_text' => $verified, 'products' => 0,
        ],
        'success' => true, 'status' => 200,
    ]);
    $owner = (new ShopDetailsCollection($shop))->resolve();
    expect($owner['verified'])->toBeTrue()->and($owner['verify_text'])->toBe($verified)
        ->and($owner['user_id'])->toBe($seller->id)->and($owner['email'])->toBe($seller->email);
    $shop->verification_status = 0;
    $unverified = (new ShopDetailsCollection($shop))->resolve();
    expect($unverified['verified'])->toBeFalse()
        ->and($unverified['verify_text'])->toBe($locale === 'en' ? 'Non verified seller' : 'بائع غير موثّق')
        ->and(Arr::except($unverified, ['verified', 'verified_img', 'verify_text']))->toBe(Arr::except($owner, ['verified', 'verified_img', 'verify_text']));
    expectNoStorefrontTranslationQueries();
})->with('API resource locales');

test('mobile order display strings are localized while status identifiers and custom methods stay stable', function (string $locale) {
    app()->setLocale($locale);
    app('translator')->setLocale(StoreLocale::uiLocale());
    $order = (object) [
        'id' => 45, 'code' => 'ORDER-45', 'for_customer' => null, 'user_id' => '9',
        'user' => (object) ['name' => 'Customer-authored name', 'AccSysID' => 'ERP-9'],
        'payment_type' => 'custom_transfer', 'payment_status' => 'unpaid', 'delivery_status' => 'pending',
        'grand_total' => 25.5, 'date' => 1793145600,
    ];
    startStorefrontTranslationQueryLog();
    $response = apiResourceResponseData(new PurchaseHistoryMiniCollection(collect([$order])));
    expect($response)->toBe([
        'data' => [[
            'id' => 45, 'code' => 'ORDER-45', 'user_id' => 9, 'name' => 'Customer-authored name',
            'otajer_id' => 'ERP-9', 'payment_type' => 'Custom Transfer', 'payment_status' => 'unpaid',
            'payment_status_string' => $locale === 'en' ? 'Unpaid' : 'غير مدفوع', 'delivery_status' => 'pending',
            'delivery_status_string' => $locale === 'en' ? 'Order Placed' : 'تم تقديم الطلب',
            'grand_total' => format_price(25.5), 'date' => date('d-m-Y', 1793145600), 'links' => ['details' => ''],
        ]],
        'success' => true, 'status' => 200,
    ])->and(ApiResourceLabel::status('custom_review'))->toBe('Custom Review')
        ->and(ApiResourceLabel::deliveryStatus('custom_delivery'))->toBe('Custom Delivery');
    expectNoStorefrontTranslationQueries();
})->with(['en', 'ar', 'sa']);

test('seller transaction interpolation and wallet approval labels preserve values and envelope types', function (string $locale) {
    app()->setLocale($locale);
    app('translator')->setLocale(StoreLocale::uiLocale());
    $transaction = 'TX-<123> :method';
    $payment = (object) ['id' => 54, 'amount' => 12.5, 'payment_method' => 'custom_transfer', 'txn_code' => $transaction, 'created_at' => '2026-10-28 14:00:00'];
    startStorefrontTranslationQueryLog();
    expect((new SellerPaymentResource($payment))->resolve())->toBe([
        'id' => 54, 'amount' => format_price(12.5),
        'payment_method' => $locale === 'en' ? 'Custom transfer TRX ID:'.$transaction : 'Custom transfer رقم العملية: '.$transaction,
        'payment_date' => '28-10-2026',
    ]);
    $payment->txn_code = null;
    expect((new SellerPaymentResource($payment))->resolve()['payment_method'])->toBe('Custom transfer');
    $wallet = apiResourceResponseData(new WalletCollection(collect([
        (object) ['amount' => 12.5, 'payment_method' => 'custom_transfer', 'offline_payment' => 1, 'approval' => 0, 'created_at' => '2026-10-28 14:00:00'],
        (object) ['amount' => 4, 'payment_method' => 'wallet', 'offline_payment' => 0, 'approval' => 1, 'created_at' => '2026-10-28 14:00:00'],
    ])));
    expect($wallet)->toBe([
        'data' => [
            ['amount' => format_price(12.5), 'payment_method' => 'Custom Transfer', 'approval_string' => $locale === 'en' ? 'Declined' : 'مرفوض', 'date' => '28-10-2026'],
            ['amount' => format_price(4), 'payment_method' => 'Wallet', 'approval_string' => $locale === 'en' ? 'N/A' : 'لا ينطبق', 'date' => '28-10-2026'],
        ],
        'result' => true, 'status' => 200,
    ]);
    expectNoStorefrontTranslationQueries();
})->with(['en', 'ar', 'sa']);

test('in-house product labels and price ranges are file-backed while content keeps its selected language', function (string $locale) {
    app()->setLocale($locale);
    app('translator')->setLocale(StoreLocale::uiLocale());
    $product = createStoreProduct([
        'name' => 'Business product', 'description' => 'Business description', 'slug' => 'localized-resource',
        'user_id' => createStoreUser('admin')->id, 'added_by' => 'admin', 'variant_product' => 1,
        'unit_price' => 2.5, 'discount' => 0, 'discount_type' => 'amount', 'discount_start_date' => null,
        'photos' => '', 'tags' => 'business', 'rating' => 4, 'thumbnail_img' => null, 'video_link' => null,
    ]);
    $product->product_translations()->create(['lang' => $locale, 'name' => 'Selected content name', 'description' => 'Selected content description']);
    DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => 'Large', 'price' => 4.75, 'qty' => 2]);
    $product = $product->fresh()->load(['user', 'stocks', 'taxes', 'product_translations']);
    startStorefrontTranslationQueryLog();
    $row = (new DigitalProductDetailCollection(collect([$product])))->resolve()['data']->first();
    expect($row)->toBe([
        'id' => $product->id, 'name' => 'Selected content name', 'added_by' => 'admin', 'seller_id' => $product->user_id,
        'shop_id' => 0, 'shop_name' => $locale === 'en' ? 'In House Product' : 'منتج المتجر',
        'shop_logo' => static_asset('assets/img/placeholder.jpg'),
        'photos' => [['variant' => '', 'path' => static_asset('assets/img/placeholder.jpg')]],
        'thumbnail_image' => static_asset('assets/img/placeholder.jpg'), 'tags' => ['business'],
        'price_high_low' => $locale === 'en' ? 'From '.format_price(2.5).' to '.format_price(4.75) : 'من '.format_price(2.5).' إلى '.format_price(4.75),
        'has_discount' => false, 'stroked_price' => format_price(2.5), 'main_price' => format_price(2.5),
        'calculable_price' => 2.5, 'currency_symbol' => '€', 'rating' => 4.0, 'rating_count' => 0, 'earn_point' => 0.0,
        'description' => 'Selected content description', 'video_link' => '', 'link' => route('product', 'localized-resource'),
    ]);
    expectNoStorefrontTranslationQueries();
    expect(app()->getLocale())->toBe($locale);
})->with(['en', 'ar', 'sa']);

test('database overrides cannot replace static resource labels', function () {
    app()->setLocale('ar');
    DB::table('translations')->insert([
        ['lang' => 'ar', 'lang_key' => 'in_house_product', 'lang_value' => 'Database override'],
        ['lang' => 'ar', 'lang_key' => 'paid', 'lang_value' => 'Database override'],
    ]);
    $before = DB::table('translations')->get()->toJson();
    $conversation = (object) [
        'id' => 61, 'receiver_id' => '7', 'receiver' => (object) ['user_type' => 'admin'],
        'title' => 'User-authored title', 'sender_viewed' => '1', 'receiver_viewed' => '0', 'updated_at' => '2026-10-28 14:00:00',
    ];
    startStorefrontTranslationQueryLog();
    expect(apiResourceResponseData(new ConversationCollection(collect([$conversation]))))->toBe([
        'data' => [[
            'id' => 61, 'receiver_id' => 7, 'receiver_type' => 'admin', 'shop_id' => 0, 'shop_name' => 'منتج المتجر',
            'shop_logo' => static_asset('assets/img/placeholder.jpg'), 'title' => 'User-authored title',
            'sender_viewed' => 1, 'receiver_viewed' => 0, 'date' => '2026-10-28 14:00:00',
        ]],
        'success' => true, 'status' => 200,
    ]);
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->get()->toJson())->toBe($before);
});

test('refund resources translate only display labels and retain approval and reason values', function (string $locale) {
    app()->setLocale($locale);
    app('translator')->setLocale(StoreLocale::uiLocale());
    $refunds = collect([0, 1, 2])->map(fn (int $status) => (object) [
        'id' => 70 + $status, 'user_id' => '9', 'order' => (object) ['code' => 'ORDER-9'], 'orderDetail' => null,
        'refund_status' => $status, 'seller_approval' => 1, 'reject_reason' => 'Seller-authored reason',
        'reason' => 'Customer-authored reason', 'created_at' => '2026-10-28 14:00:00',
    ]);
    startStorefrontTranslationQueryLog();
    $response = apiResourceResponseData(new RefundRequestCollection($refunds));
    $labels = $locale === 'en' ? ['Pending', 'Approved', 'Rejected'] : ['قيد الانتظار', 'معتمد', 'مرفوضة'];
    expect($response)->toBe([
        'data' => array_map(fn (int $status) => [
            'id' => 70 + $status, 'user_id' => 9, 'order_code' => 'ORDER-9', 'product_name' => '', 'product_price' => '',
            'refund_status' => $status, 'refund_label' => $labels[$status], 'seller_approval' => 1,
            'reject_reason' => 'Seller-authored reason', 'reason' => 'Customer-authored reason', 'date' => '28-10-2026',
        ], [0, 1, 2]),
        'success' => true, 'status' => 200,
    ]);
    $commission = (object) ['id' => 81, 'order' => null, 'admin_commission' => '1.25', 'seller_earning' => 12.5, 'created_at' => '2026-10-28 14:00:00'];
    expect((new CommissionHistoryResource($commission))->resolve())->toBe([
        'id' => 81, 'order_code' => $locale === 'en' ? 'Order Deleted' : 'الطلب محذوف',
        'admin_commission' => '1.25', 'seller_earning' => format_price(12.5), 'created_at' => '28-10-2026',
    ]);
    $commission->order = (object) ['code' => 'ORDER-81'];
    expect((new CommissionHistoryResource($commission))->resolve()['order_code'])->toBe('ORDER-81');
    expectNoStorefrontTranslationQueries();
})->with(['en', 'ar', 'sa']);

test('API resource catalogs have matching semantic keys and interpolation placeholders', function () {
    $english = Arr::dot(require lang_path('en/api_resources.php'));
    $arabic = Arr::dot(require lang_path('ar/api_resources.php'));
    expect(array_keys($arabic))->toBe(array_keys($english));

    foreach ($english as $key => $value) {
        expect($value)->toBeString()->not->toBeEmpty();
        expect($arabic[$key])->toBeString()->toMatch('/[\x{0600}-\x{06ff}]/u');
        preg_match_all('/:[A-Za-z_][A-Za-z_0-9]*/', $value, $enTokens);
        preg_match_all('/:[A-Za-z_][A-Za-z_0-9]*/', $arabic[$key], $arTokens);
        sort($enTokens[0]);
        sort($arTokens[0]);
        expect($arTokens[0])->toBe($enTokens[0]);
    }
});

<?php

use App\Http\Requests\Seller\PhysicalProductRequest;
use App\Models\Category;
use App\Models\ProductStock;
use App\Models\StoreUser;
use App\Rules\Recaptcha;
use App\Services\SellerWithdrawService;
use App\Services\Store\ProductVariantCombinations;
use App\Support\StoreLocale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
    $this->withoutVite();
    Mail::fake();
    seedStoreCurrency();
    $this->seller = sellerAccount();
    DB::table('shops')->where('user_id', $this->seller->id)->update(['slug' => 'localization-shop', 'verification_status' => 1]);
    DB::table('business_settings')->insert(['type' => 'conversation_system', 'value' => '1']);
});

test('seller screens render natural Arabic with an empty translations table and no translation queries', function (string $route, array $labels) {
    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();
    $response = $this->get(route($route))->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    foreach ($labels as $label) {
        $response->assertSee($label);
    }
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->count())->toBe(0);
})->with([
    'dashboard' => ['seller.dashboard', ['إجمالي الطلبات', 'إجمالي المبيعات', 'إحصاءات المبيعات', 'أفضل 12 منتجًا']],
    'profile' => ['seller.profile.index', ['المعلومات الأساسية', 'اسم صاحب الحساب البنكي', 'رقم التوجيه البنكي']],
    'shop' => ['seller.shop.index', ['شعار المتجر', 'عنوان المتجر', 'إعدادات اللافتات']],
    'verification' => ['seller.shop.verify', ['التحقق من المتجر', 'معلومات التحقق']],
    'products' => ['seller.products', ['المنتجات', 'الكمية الحالية']],
    'product form' => ['seller.products.create', ['معلومات المنتج', 'تنويعات المنتج', 'الحد الأدنى لكمية الشراء', 'طريقة عرض المخزون']],
    'digital form' => ['seller.digitalproducts.create', ['إضافة منتج رقمي جديد', 'ملف المنتج', 'سعر الشراء']],
    'bulk import' => ['seller.product_bulk_upload.index', ['إضافة المنتجات دفعة واحدة', 'رفع ملف CSV', 'تنزيل الفئات']],
    'uploads' => ['seller.uploaded-files.index', ['جميع الملفات المرفوعة', 'رفع ملف جديد']],
    'orders' => ['seller.orders.index', ['الطلبات', 'تصفية حسب حالة الدفع', 'عدد المنتجات']],
    'payments' => ['seller.payments.index', ['سجل المدفوعات']],
    'withdrawals' => ['seller.money_withdraw_requests.index', ['إرسال طلب سحب', 'الرصيد المعلّق']],
    'coupons' => ['seller.coupon.create', ['إضافة قسيمتك', 'رمز القسيمة', 'الحد الأدنى للشراء', 'الحد الأقصى للخصم']],
    'notifications' => ['seller.all-notification', ['لا توجد إشعارات']],
    'conversations' => ['seller.conversations.index', ['المحادثات']],
    'support' => ['seller.support_ticket.index', ['تذكرة دعم', 'الموضوع']],
]);

test('seller UI respects the configured locale session legacy alias and fallback', function (string $default, ?string $selection, string $fallback, string $ui, string $direction, string $label) {
    DB::table('languages')->insert([
        ['name' => 'Arabic', 'code' => 'sa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1],
        ['name' => 'French', 'code' => 'fr', 'app_lang_code' => 'fr', 'rtl' => 0, 'status' => 1],
    ]);
    config(['app.locale' => $default, 'app.fallback_locale' => $fallback]);
    if ($selection !== null) {
        $this->withSession(['locale' => $selection]);
    }
    startStorefrontTranslationQueryLog();
    $this->get(route('seller.dashboard'))->assertOk()->assertSee('lang="'.$ui.'" dir="'.$direction.'"', false)->assertSee($label);
    expectNoStorefrontTranslationQueries();
    expect(default_language())->toBe('en');
})->with([
    ['en', null, 'en', 'en', 'ltr', 'Sales Stat'],
    ['ar', 'en', 'en', 'en', 'ltr', 'Sales Stat'],
    ['en', 'sa', 'en', 'ar', 'rtl', 'إحصاءات المبيعات'],
    ['ar', 'invalid', 'en', 'ar', 'rtl', 'إحصاءات المبيعات'],
    ['en', 'fr', 'ar', 'fr', 'ltr', 'إحصاءات المبيعات'],
]);

test('database static overrides cannot replace seller text and existing records remain untouched', function () {
    DB::table('translations')->insert(['lang' => 'ar', 'lang_key' => 'sales_stat', 'lang_value' => 'Database override']);
    $before = DB::table('translations')->get()->toJson();
    startStorefrontTranslationQueryLog();
    $this->get(route('seller.dashboard'))->assertOk()->assertSee('إحصاءات المبيعات')->assertDontSee('Database override');
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->get()->toJson())->toBe($before);
});

test('seller content still uses its legacy language while static form text uses Arabic', function () {
    DB::table('languages')->insert(['name' => 'Arabic', 'code' => 'sa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1]);
    $category = Category::forceCreate(['name' => 'Base category', 'slug' => 'seller-content', 'digital' => 0, 'parent_id' => 0]);
    $category->category_translations()->create(['lang' => 'sa', 'name' => 'تصنيف مخصص قديم']);
    $category->category_translations()->create(['lang' => 'ar', 'name' => 'Different content record']);
    startStorefrontTranslationQueryLog();
    $this->withSession(['locale' => 'sa'])->get(route('seller.products.create'))->assertOk()
        ->assertSee('تصنيف مخصص قديم')->assertDontSee('Different content record')->assertSee('معلومات المنتج');
    expectNoStorefrontTranslationQueries();
    expect(app()->getLocale())->toBe('sa')->and(app('translator')->getLocale())->toBe('ar');
});

test('seller flash text and translated JavaScript remain serialized as data', function () {
    $hostile = "Quote ' \" ` \${value} </script><script>alert(1)</script> &";
    Lang::addLines(['storefront.common.nothing_selected' => $hostile, 'seller.products.could_not_generate_variants_check_the_selected_options' => $hostile], 'ar');
    flash($hostile)->warning();
    $this->get(route('seller.products.create'))->assertOk()->assertSee((string) Js::from($hostile), false)
        ->assertDontSee('</script><script>alert(1)</script>', false);
});

test('seller order notifications interpolate the order code and known status without database translations', function () {
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\OrderNotification',
        'notifiable_type' => StoreUser::class, 'notifiable_id' => $this->seller->id,
        'data' => json_encode(['order_id' => 123, 'order_code' => 'ORDER-123', 'status' => 'on_delivery']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    startStorefrontTranslationQueryLog();
    $this->get(route('seller.all-notification'))->assertOk()->assertSee('تم تحديث الطلب ORDER-123: قيد التوصيل');
    expectNoStorefrontTranslationQueries();
});

test('seller validation and successful writes use Arabic without translating database messages', function () {
    startStorefrontTranslationQueryLog();
    $this->from(route('seller.shop.index'))->post(route('seller.shop.update'), ['name' => 'Shop', 'address' => '']);
    $this->get(route('seller.shop.index'))->assertOk()->assertSee('حقل العنوان مطلوب.');
    $this->from(route('seller.shop.index'))->post(route('seller.shop.update'), ['name' => 'New name', 'address' => 'New address'])->assertRedirect();
    expect(flashedMessage())->toBe('تم تحديث متجرك بنجاح!');
    expectNoStorefrontTranslationQueries();
    expect($this->seller->shop->fresh()->name)->toBe('New name');
});

test('seller localization leaves ownership refusals ahead of malformed product input', function () {
    $other = createStoreUser('seller');
    $product = createStoreProduct(['user_id' => $other->id, 'added_by' => 'seller', 'digital' => 1]);
    $before = $product->getAttributes();
    startStorefrontTranslationQueryLog();
    $this->post(route('seller.digitalproducts.update', $product), ['name' => []])->assertRedirect();
    expect(flashedMessage())->toBe('هذا المنتج لا يخصك.');
    expectNoStorefrontTranslationQueries();
    expect($product->fresh()->getAttributes())->toBe($before);
});

test('seller coupon validation renders Arabic without inserting translation records', function () {
    startStorefrontTranslationQueryLog();
    $this->from(route('seller.coupon.create'))->post(route('seller.coupon.store'), []);
    $this->get(route('seller.coupon.create'))->assertOk()->assertSee('نوع القسيمة مطلوب')->assertSee('رمز القسيمة مطلوب');
    expectNoStorefrontTranslationQueries();
    expect(DB::table('coupons')->count())->toBe(0)->and(DB::table('translations')->count())->toBe(0);
});

test('seller withdrawal exceptions retain their class and accounting while translating the message', function () {
    $this->get(route('seller.dashboard'))->assertOk();
    $before = $this->seller->shop->getAttributes();
    startStorefrontTranslationQueryLog();
    expect(fn () => app(SellerWithdrawService::class)->request($this->seller, 1000, null))->toThrow(DomainException::class, 'المبلغ غير صالح');
    expectNoStorefrontTranslationQueries();
    expect($this->seller->shop->fresh()->getAttributes())->toBe($before)->and(DB::table('seller_withdraw_requests')->count())->toBe(0);
});

test('variant validation interpolates human labels without changing encoded input keys', function () {
    $this->get(route('seller.dashboard'))->assertOk();
    $request = PhysicalProductRequest::create('/', 'POST', ['choice_no' => [1], 'choice_options_1' => ['S[mall]']]);
    startStorefrontTranslationQueryLog();
    $key = ProductVariantCombinations::inputKey('S[mall]');
    expect($request->attributes()['price_'.$key])->toBe('سعر التنويعة: S[mall]');
    expectNoStorefrontTranslationQueries();
});

test('stock reconciliation errors and retained admin variant labels use localization files', function () {
    $this->get(route('seller.dashboard'))->assertOk();
    $product = createStoreProduct(['user_id' => $this->seller->id, 'colors' => '[]', 'choice_options' => '[]']);
    ProductStock::forceCreate(['product_id' => $product->id, 'variant' => 'orphan', 'price' => 10, 'qty' => 2]);
    startStorefrontTranslationQueryLog();
    try {
        app(ProductVariantCombinations::class)->assertLegacyStocksRetained($product, []);
        $this->fail('Unmatched stocks must still be refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['choice_options' => ['المخزون المحفوظ لا يطابق خيارات المنتج. طابق الخيارات مع المخزون الحالي قبل الحفظ.']]);
    }
    $html = view('shop.backend.product.products.sku_combinations', ['combinations' => [['Small']], 'colors_active' => false, 'unit_price' => 10, 'product_name' => 'Custom product'])->render();
    expect($html)->toContain('سعر التنويعة', 'رمز المخزون', 'Small');
    expectNoStorefrontTranslationQueries();
});

test('seller upload limits use plural forms for Arabic counts', function (int $count, string $expected) {
    $this->get(route('seller.dashboard'))->assertOk();
    expect(trans_choice('seller.dashboard.upload_limit', $count, ['count' => $count]))->toBe($expected);
})->with([[0, 'لا توجد منتجات'], [1, 'منتج واحد'], [2, 'منتجان'], [3, '3 منتجات'], [11, '11 منتجًا']]);

test('seller registration robot checks use file messages and still fail closed', function () {
    $this->get(route('seller.dashboard'))->assertOk();
    config(['services.recaptcha.secret_key' => null]);
    Http::fake();
    $errors = [];
    startStorefrontTranslationQueryLog();
    (new Recaptcha)->validate('g-recaptcha-response', 'token', function (string $message) use (&$errors): void {
        $errors[] = $message;
    });
    expect($errors)->toBe(['تعذّر إكمال التحقق من أنك لست روبوتًا. يرجى المحاولة مرة أخرى.'])
        ->and(Recaptcha::messages()['g-recaptcha-response.required'])->toBe('يرجى تأكيد أنك لست روبوتًا.');
    expectNoStorefrontTranslationQueries();
    Http::assertNothingSent();
});

test('seller and remaining admin catalogs match in keys translations and interpolation', function () {
    foreach (['seller', 'store_admin', 'store_permissions'] as $file) {
        $english = Arr::dot(require lang_path('en/'.$file.'.php'));
        $arabic = Arr::dot(require lang_path('ar/'.$file.'.php'));
        expect(array_keys($arabic))->toBe(array_keys($english));
        foreach ($english as $key => $value) {
            expect($value)->toBeString()->not->toBe('')->and($arabic[$key])->toBeString()->not->toBe('');
            preg_match_all('/:[a-z_]+/', $value, $enParameters);
            preg_match_all('/:[a-z_]+/', $arabic[$key], $arParameters);
            expect(array_unique($arParameters[0]))->toEqual(array_unique($enParameters[0]), $file.'.'.$key);
            if (! in_array($key, ['products.csv', 'products.variant_label'], true)) {
                expect(preg_match('/\p{Arabic}/u', $arabic[$key]))->toBe(1, $file.'.'.$key);
            }
        }
    }
});

test('seller static sources and retained variant fragments resolve file keys with only content helper exceptions', function () {
    $paths = [resource_path('views/shop/seller'), app_path('Http/Controllers/Seller'), app_path('Http/Requests/Seller')];
    $files = [resource_path('views/shop/backend/product/products/sku_combinations.blade.php'), resource_path('views/shop/backend/product/products/sku_combinations_edit.blade.php')];
    foreach ($paths as $path) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
    }
    foreach ($files as $path) {
        $source = file_get_contents($path);
        preg_match_all("/__\('([a-z0-9_.]+)'/", $source, $matches);
        foreach ($matches[1] as $key) {
            expect(Lang::has($key, 'en', false))->toBeTrue($path.': '.$key);
            expect(Lang::has($key, 'ar', false))->toBeTrue($path.': '.$key);
        }
        $source = preg_replace('/transform="[^"]*"/', '', $source);
        $source = str_replace([
            'translate($query->user->name)', 'translate($query->product->name)',
            'translate(Str::limit($query->question, 100))', 'translate(Str::limit($query->reply, 100))',
        ], '', $source);
        expect($source)->not->toMatch('/\btranslate\(/');
    }
});

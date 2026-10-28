<?php

use App\Models\Category;
use App\Notifications\EmailVerificationNotification;
use App\Services\OrderService;
use App\Support\StoreLocale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Js;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
    Mail::fake();
});

test('both customer login routes render Arabic labels with an empty translations table', function (string $path) {
    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();
    $this->get($path)->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSee('مرحبًا بعودتك!')->assertSee('سجّل الدخول إلى حسابك')
        ->assertSee('البريد الإلكتروني')->assertSee('كلمة المرور')->assertSee('تسجيل الدخول')
        ->assertSee('تذكرني')->assertSee('نسيت كلمة المرور؟')->assertSee('سجّل الآن')
        ->assertSessionMissing('locale');
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->count())->toBe(0);
})->with(['/temp/login', '/temp/users/login']);

test('public customer flows render actual Arabic without database translations', function (string $path, array $labels) {
    seedStoreCurrency();
    createStoreProduct(['name' => 'Unchanged catalog name', 'unit_price' => 12.5]);
    startStorefrontTranslationQueryLog();
    $response = $this->get($path)->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    foreach ($labels as $label) {
        $response->assertSee($label);
    }
    expectNoStorefrontTranslationQueries();
})->with([
    'registration' => ['/temp/users/registration', ['إنشاء حساب', 'الاسم الكامل', 'البريد الإلكتروني', 'الشروط والأحكام', 'حقول مطلوبة']],
    'recovery' => ['/temp/password/reset', ['نسيت كلمة المرور؟', 'أدخل بريدك الإلكتروني لاستعادة كلمة المرور.', 'إرسال رابط إعادة تعيين كلمة المرور']],
    'search' => ['/temp/search', ['الفئات', 'ترتيب حسب', 'السعر من الأقل إلى الأعلى']],
    'cart' => ['/temp/cart', ['سلة التسوق فارغة', '١. سلة التسوق', '٤. الدفع']],
    'brands' => ['/temp/brands', ['جميع العلامات التجارية', 'الرئيسية']],
    'categories' => ['/temp/categories', ['جميع الفئات', 'الرئيسية']],
]);

test('customer account flows render Arabic without translation queries', function (string $route, array $labels) {
    seedStoreCurrency();
    actingAsStoreUser(createStoreUser());
    DB::table('business_settings')->insert(['type' => 'conversation_system', 'value' => '1']);
    startStorefrontTranslationQueryLog();
    $response = $this->get(route($route))->assertOk();
    foreach ($labels as $label) {
        $response->assertSee($label);
    }
    expectNoStorefrontTranslationQueries();
})->with([
    'profile' => ['profile', ['المعلومات الأساسية', 'عنوانك', 'تحديث الملف الشخصي']],
    'orders' => ['purchase_history.index', ['سجل المشتريات']],
    'wallet' => ['wallet.index', ['محفظتي', 'رصيد المحفظة', 'شحن المحفظة']],
    'notifications' => ['all-notifications', ['لا توجد إشعارات']],
    'support' => ['support_ticket.index', ['تذكرة دعم', 'إنشاء تذكرة دعم', 'الموضوع', 'أدخل وصفًا تفصيليًا']],
    'conversations' => ['conversations.index', ['المحادثات', 'اختر محادثة لعرض جميع الرسائل']],
]);

test('database overrides cannot replace migrated labels and are preserved for legacy consumers', function () {
    DB::table('translations')->insert([
        ['lang' => 'ar', 'lang_key' => 'login', 'lang_value' => 'Database override'],
        ['lang' => 'en', 'lang_key' => 'legacy_label', 'lang_value' => 'Legacy label'],
        ['lang' => 'ar', 'lang_key' => 'legacy_label', 'lang_value' => 'نص قديم'],
    ]);
    $before = DB::table('translations')->orderBy('id')->get();
    startStorefrontTranslationQueryLog();
    $this->get('/temp/users/login')->assertOk()->assertSee('تسجيل الدخول')->assertDontSee('Database override');
    expectNoStorefrontTranslationQueries();
    expect(translate('Legacy label'))->toBe('نص قديم')
        ->and(DB::table('translations')->orderBy('id')->get()->toArray())->toEqual($before->toArray());
});

test('English and explicit UI choices control actual labels and direction', function (string $default, mixed $session, string $locale, string $label, string $dir) {
    config(['app.locale' => $default]);
    if ($session !== null) {
        $this->withSession(['locale' => $session]);
    }
    startStorefrontTranslationQueryLog();
    $this->get('/temp/users/login')->assertOk()->assertSee('lang="'.$locale.'" dir="'.$dir.'"', false)->assertSee($label);
    expectNoStorefrontTranslationQueries();
})->with([
    ['en', null, 'en', 'Welcome Back !', 'ltr'],
    ['ar', 'en', 'en', 'Welcome Back !', 'ltr'],
    ['en', 'ar', 'ar', 'مرحبًا بعودتك!', 'rtl'],
    ['ar', 'xx', 'ar', 'مرحبًا بعودتك!', 'rtl'],
    ['ar', ['ar'], 'ar', 'مرحبًا بعودتك!', 'rtl'],
]);

test('the legacy sa session resolves Arabic UI while content lookups keep their store code', function () {
    DB::table('languages')->insert(['name' => 'Arabic', 'code' => 'sa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1]);
    $category = Category::query()->create(['name' => 'Base category', 'slug' => 'locale-category']);
    $category->category_translations()->create(['lang' => 'sa', 'name' => 'محتوى عربي قديم']);
    $category->category_translations()->create(['lang' => 'ar', 'name' => 'Different content record']);
    config(['app.locale' => 'en']);
    startStorefrontTranslationQueryLog();
    $this->withSession(['locale' => 'sa'])->get('/temp/users/login')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)->assertSee('تسجيل الدخول')->assertSessionHas('locale', 'sa');
    expectNoStorefrontTranslationQueries();
    expect(app()->getLocale())->toBe('sa')->and(app('translator')->getLocale())->toBe('ar')
        ->and(StoreLocale::isRtl())->toBeTrue()
        ->and($category->fresh()->getTranslation('name'))->toBe('محتوى عربي قديم')
        ->and(default_language())->toBe('en');
});

test('a valid additional language uses the configured file fallback and its own direction', function (string $fallback, string $label) {
    DB::table('languages')->insert(['name' => 'Persian', 'code' => 'fa', 'app_lang_code' => 'fa', 'rtl' => 1, 'status' => 1]);
    config(['app.fallback_locale' => $fallback]);
    $this->withSession(['locale' => 'fa'])->get('/temp/users/login')->assertOk()
        ->assertSee('lang="fa" dir="rtl"', false)->assertSee($label);
})->with([['en', 'Welcome Back !'], ['ar', 'مرحبًا بعودتك!']]);

test('recovery and validation messages render Arabic on the customer form', function () {
    startStorefrontTranslationQueryLog();
    $this->post('/temp/password/email', ['email' => 'missing@example.com'])->assertOk()
        ->assertSee('إعادة تعيين كلمة المرور')->assertSee('كلمة المرور الجديدة')->assertSee('رمز');
    $this->from('/temp/users/login')->post('/temp/login', ['email' => 'bad@example.com', 'password' => 'wrong'])->assertRedirect();
    $this->get('/temp/users/login')->assertOk()->assertSee((string) Js::from('بيانات الدخول غير صحيحة'), false);
    expectNoStorefrontTranslationQueries();
});

test('checkout refusals and minimum quantity messages are interpolated in Arabic', function () {
    actingAsStoreUser(createStoreUser());
    startStorefrontTranslationQueryLog();
    $this->get(route('checkout.shipping_info'))->assertRedirect();
    expect(flashedMessage())->toBe('سلة التسوق فارغة');
    expect(view('shop.frontend.partials.minQtyNotSatisfied', ['min_qty' => 3])->render())->toContain('يجب إضافة 3 منتجات على الأقل!');
    expect(trans_choice('storefront.upload.selected_files', 2, ['count' => 2]))->toBe('تم تحديد ملفين');
    expectNoStorefrontTranslationQueries();
});

test('all delivery states have file based Arabic names', function () {
    $this->get('/temp/users/login')->assertOk();
    expect(array_map(StoreLocale::status(...), OrderService::DELIVERY_STATUSES))->toBe([
        'قيد الانتظار', 'مؤكد', 'تم الاستلام', 'في الطريق', 'قيد التوصيل', 'جاهز للتوصيل', 'تم التوصيل', 'ملغى',
    ]);
    expect(StoreLocale::status('cash_on_delivery'))->toBe('الدفع عند الاستلام');
});

test('migrated JavaScript labels and flash text are serialized safely', function () {
    $hostile = "Quote ' \" </script><script>alert(1)</script> &";
    Lang::addLines(['storefront.common.nothing_selected' => $hostile], 'ar');
    flash($hostile)->warning();
    $this->get('/temp/users/login')->assertOk()
        ->assertSee((string) Js::from($hostile), false)
        ->assertDontSee('</script><script>alert(1)</script>', false);
});

test('English and Arabic storefront catalogs have matching complete translated keys', function () {
    $en = Arr::dot(require lang_path('en/storefront.php'));
    $ar = Arr::dot(require lang_path('ar/storefront.php'));
    expect(array_keys($ar))->toBe(array_keys($en));
    $brands = ['Aamarpay', 'Bkash', 'Dailymotion', 'ngenius', 'payhere', 'Payhere', 'PDF', 'QR', 'sslcommerz', 'ToyyibPay', 'Vimeo', 'Voguepay', 'Youtube', '+', '+880', 'johndoe@example.com'];
    foreach ($en as $key => $value) {
        expect($value)->not->toBe('');
        expect($ar[$key])->not->toBe('');
        if (! in_array($ar[$key], $brands, true) && ! str_starts_with($key, 'status.')) {
            expect(preg_match('/\p{Arabic}/u', $ar[$key]))->toBe(1, $key);
        }
        preg_match_all('/:[a-z_]+/', $value, $englishParameters);
        preg_match_all('/:[a-z_]+/', $ar[$key], $arabicParameters);
        expect(array_unique($arabicParameters[0]))->toEqual(array_unique($englishParameters[0]), $key);
    }
});

test('scoped templates resolve every literal storefront key in both catalogs', function () {
    $catalog = Arr::dot(require lang_path('en/storefront.php'));
    $paths = [resource_path('views/shop/frontend'), resource_path('views/shop/auth'), resource_path('views/shop/errors'), resource_path('views/components')];
    foreach ($paths as $path) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            preg_match_all("/storefront\.([a-z0-9_.]+)'/", $source, $matches);
            foreach ($matches[1] as $key) {
                expect(array_key_exists($key, $catalog))->toBeTrue($file->getPathname().': '.$key);
            }
            // Only business-setting and external-button content may use the legacy helper.
            $source = preg_replace('/transform="[^"]*"/', '', $source);
            $source = str_replace([
                "translate(get_setting('cupon_title'))", "translate(get_setting('cupon_subtitle'))",
                'translate($value)', 'translate($detailedProduct->external_link_btn)', 'translate($product->external_link_btn)',
            ], '', $source);
            expect($source)->not->toMatch('/\btranslate\(/');
        }
    }
});

test('Arabic checkout address validation uses translated attributes on the form', function () {
    actingAsStoreUser(createStoreUser());
    startStorefrontTranslationQueryLog();
    $this->from(route('profile'))->post(route('addresses.store'), ['address' => '', 'postal_code' => '', 'phone' => ''])->assertRedirect();
    $this->get(route('profile'))->assertOk()->assertSee('حقل العنوان مطلوب.')->assertSee('حقل الرمز البريدي مطلوب.');
    expectNoStorefrontTranslationQueries();
});

test('auth verification messages and email actions use files without creating translation rows', function () {
    $customer = createStoreUser();
    $this->get('/temp/users/login')->assertOk();
    startStorefrontTranslationQueryLog();
    $message = (new EmailVerificationNotification)->toMail($customer);
    expect($message->subject)->toBe('التحقق من البريد الإلكتروني - '.config('app.name'));
    expect(view($message->view, $message->viewData)->render())->toContain('موافق');
    expectNoStorefrontTranslationQueries();
});

test('the legacy language switcher and Vue admin agree on the Arabic UI locale', function () {
    DB::table('languages')->insert(['name' => 'العربية', 'code' => 'sa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1]);
    DB::table('business_settings')->insert(['type' => 'show_language_switcher', 'value' => 'on']);
    $this->get('/temp/users/login')->assertOk()->assertSee('العربية');
    actingAsStoreUser(createStoreStaff('view_all_staffs'));
    $this->withSession(['locale' => 'sa'])->get('/temp/admin/staffs')->assertOk()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('store.rtl', true));
});

test('maintenance and error pages use Arabic static labels without database translation reads', function (string $view, string $label) {
    $this->get('/temp/users/login')->assertOk();
    startStorefrontTranslationQueryLog();
    expect(view('shop.errors.'.$view)->render())->toContain($label);
    expectNoStorefrontTranslationQueries();
})->with([['403', 'تم رفض الوصول!'], ['404', 'الصفحة غير موجودة!'], ['500', 'حدث خطأ!'], ['503', 'الموقع قيد الصيانة.']]);

<?php

use App\Models\OrderDetail;
use App\Models\RepresentativePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use niklasravnsborg\LaravelPdf\Facades\Pdf;

/*
 * Direction A beyond the storefront shell (.ai/rules/views.md): the seller
 * panel's own stylesheet, the standalone login layout, the customer emails and
 * the customer-facing PDFs. The WMS and the store control panel are untouched.
 */

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.ui_default_locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
    seedStoreCurrency();
});

const IDENTITY_LATIN_FONT = 'node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-latin-400-normal.woff2';
const IDENTITY_ARABIC_FONT = 'node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2';

dataset('identity languages', [
    'Arabic' => ['ar', 'rtl', 'اسم المتجر'],
    'English' => ['en', 'ltr', 'Store name'],
]);

test('the seller panel loads its identity stylesheet after the seller theme, with the Plex font, in both languages', function (string $locale, string $dir) {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    createShopFor($seller, 0);

    $html = $this->withSession(['locale' => $locale])->get(route('seller.dashboard'))->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$dir.'"', false)
        ->assertSee('<link rel="stylesheet" href="'.Vite::asset('resources/css/seller.css').'"', false)
        ->assertSee('<link rel="preload" as="font" type="font/woff2" crossorigin href="'.Vite::asset(IDENTITY_LATIN_FONT).'">', false)
        ->assertDontSee(Vite::asset('resources/css/storefront.css'), false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('Poppins', false)
        ->assertDontSee('Roboto', false)
        ->assertDontSee('A9A3CC', false)
        ->assertDontSee("backgroundColor: ['#2E294E'", false)
        ->getContent();

    expect(strpos($html, Vite::asset('resources/css/seller.css')))->toBeGreaterThan(strpos($html, 'assets/css/aiz-seller.css'))
        ->and(str_contains($html, Vite::asset(IDENTITY_ARABIC_FONT)))->toBe($dir === 'rtl')
        ->and(str_contains($html, 'assets/css/bootstrap-rtl.min.css'))->toBe($dir === 'rtl');
})->with([['ar', 'rtl'], ['en', 'ltr']]);

test('the seller stylesheet shares the storefront tokens and overrides the seller theme', function () {
    $css = Vite::content('resources/css/seller.css');

    expect($css)
        ->toContain('IBM Plex Sans Arabic')
        ->toContain('IBM Plex Mono')
        ->toContain('@font-face')
        ->toMatch('/--brand:\s*#c4170c/i')
        ->toMatch('/--primary:\s*var\(--brand\)/')
        ->toMatch('/--danger-color:\s*#b42318/i')
        ->toContain('.aiz-sidebar.left')
        ->toContain('.aiz-topbar')
        ->toMatch('/\[dir=["\']?rtl["\']?\]/')
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('Poppins')
        ->not->toContain('#e9e9f0');

    expect(Vite::content('resources/css/storefront.css'))->toMatch('/--brand:\s*#c4170c/i');
});

test('the shared token partial is the one place the Direction A palette is defined', function () {
    $tokens = file_get_contents(resource_path('css/brand/tokens.css'));

    expect($tokens)->toMatch('/--brand:\s*#c4170c/i')
        ->and(file_get_contents(resource_path('css/storefront.css')))->not->toMatch('/--brand:\s*#/')->toContain("@import './brand/tokens.css'")
        ->and(file_get_contents(resource_path('css/seller.css')))->not->toMatch('/--brand:\s*#/')->toContain("@import './brand/tokens.css'");
});

test('the standalone login layout loads the identity stylesheet instead of Poppins', function (string $locale, string $dir) {
    storeSetting('vendor_system_activation', 1);

    $html = $this->withSession(['locale' => $locale])->get(route('seller.login'))->assertOk()
        ->assertSee('lang="'.$locale.'" dir="'.$dir.'"', false)
        ->assertSee('<link rel="stylesheet" href="'.Vite::asset('resources/css/storefront.css').'"', false)
        ->assertSee('<link rel="preload" as="font" type="font/woff2" crossorigin href="'.Vite::asset(IDENTITY_LATIN_FONT).'">', false)
        ->assertSee('assets/img/33logoqr.png', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('Poppins', false)
        ->assertDontSee('rounded-0', false)
        ->getContent();

    expect(strpos($html, Vite::asset('resources/css/storefront.css')))->toBeGreaterThan(strpos($html, 'assets/css/aiz-core.css'));

    foreach (['shop.frontend.deliveryboy_login', 'shop.errors.503'] as $view) {
        expect(view($view)->render())
            ->toContain(Vite::asset('resources/css/storefront.css'))
            ->not->toContain('fonts.googleapis.com')
            ->not->toContain('Poppins');
    }
})->with([['ar', 'rtl'], ['en', 'ltr']]);

test('the storefront sign-in, registration and password pages carry the identity and no old colours', function (string $routeName, array $parameters) {
    $html = $this->get(route($routeName, $parameters))->assertOk()
        ->assertSee(Vite::asset('resources/css/storefront.css'), false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('Public Sans', false)
        ->assertDontSee('Poppins', false)
        ->getContent();

    expect($html)->not->toMatch('/style="[^"]*#(8d8d8d|ff9819|d43533|e62e04|2e294e)/i');
})->with([
    'sign in' => ['user.login', []],
    'registration' => ['user.registration', []],
    'password request' => ['password.request', []],
    'password reset' => ['password.reset', ['token' => 'a-token']],
]);

test('the back office never loads the seller stylesheet', function () {
    $this->get(route('login'))->assertOk()
        ->assertDontSee(Vite::asset('resources/css/seller.css'), false);

    expect(file_get_contents(resource_path('css/app.css')))->not->toContain('seller.css');
});

/*
 * Emails: inline CSS, web-safe fonts, the placeholder brand name and the logo.
 */

dataset('identity mails', function () {
    return [
        'password reset' => ['shop.emails.password_reset', fn (string $l) => ['array' => ['locale' => $l, 'link' => 'https://shop.example.test/reset/abc']]],
        'order notification' => ['shop.emails.order_notification', fn (string $l) => ['array' => ['locale' => $l, 'subject' => 'Order update', 'content' => 'Your order shipped', 'link' => 'https://shop.example.test/o/1']]],
        'verification' => ['shop.emails.verification', fn (string $l) => ['array' => ['locale' => $l, 'subject' => 'Verify', 'content' => 'Code 123456', 'link' => 'https://shop.example.test/v']]],
        'terms' => ['shop.emails.terms', fn (string $l) => ['array' => ['locale' => $l, 'subject' => 'Terms', 'content' => 'Please agree', 'link' => 'https://shop.example.test/t']]],
        'app verification' => ['shop.emails.app_verification', fn (string $l) => ['array' => ['locale' => $l, 'subject' => 'Verify app', 'content' => 'Code 654321']]],
        'support' => ['shop.emails.support', fn (string $l) => ['mailLocale' => $l, 'content' => 'New ticket', 'sender' => 'Alice', 'details' => '<p>Help</p>', 'link' => 'https://shop.example.test/ticket/1']],
        'conversation' => ['shop.emails.conversation', fn (string $l) => ['mailLocale' => $l, 'content' => 'New message', 'sender' => 'Alice', 'details' => 'Hello', 'link' => 'https://shop.example.test/c/1']],
        'auction bid' => ['shop.emails.auction_bid', fn (string $l) => ['mailLocale' => $l, 'content' => 'You were outbid', 'link' => 'https://shop.example.test/a/1']],
        'newsletter' => ['shop.emails.newsletter', fn (string $l) => ['mailLocale' => $l, 'array' => ['content' => '<p>Offers</p>']]],
    ];
});

test('every customer email uses the brand shell with inline CSS and no external fonts', function (string $view, Closure $data, string $locale, string $dir, string $brandName) {
    $html = view($view, $data($locale))->render();

    expect($html)
        ->toContain('lang="'.$locale.'" dir="'.$dir.'"')
        ->toContain($brandName)
        ->toContain('assets/img/33logoqr.png')
        ->toContain('#c4170c')
        ->toContain('#faf7f4')
        ->toContain('#1f1a17')
        ->toContain('Tahoma')
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('@import')
        ->not->toContain('@font-face')
        ->not->toContain('<link')
        ->not->toContain('<script')
        ->not->toContain('Fira Mono')
        ->not->toContain('Ubuntu')
        ->not->toContain('#2e57ae')
        ->not->toContain('#007bff')
        ->not->toContain('Albaraka Holland')
        ->not->toContain('delivery.')
        ->not->toContain('storefront.');
})->with('identity mails')->with([
    'Arabic' => ['ar', 'rtl', 'اسم المتجر'],
    'English' => ['en', 'ltr', 'Store name'],
]);

test('a mail action is a bulletproof brand button pointing at the link', function () {
    $html = view('shop.emails.password_reset', ['array' => ['locale' => 'en', 'link' => 'https://shop.example.test/reset/abc']])->render();

    expect($html)->toContain('href="https://shop.example.test/reset/abc"')
        ->toContain('bgcolor="#c4170c"')
        ->toContain('Reset password');
});

test('the invoice email keeps its amounts for a verified customer and takes the brand shell', function (string $locale, string $brandName) {
    storeSetting('no_of_decimals', 2);
    $customer = actingAsStoreUser(createStoreUser());
    // The invoice email shows prices only to a signed-in, verified customer of the store guard.
    Auth::shouldUse('store');
    $order = createStoreOrder($customer, ['code' => 'MAIL-INV-1', 'grand_total' => 37.50]);
    $product = createStoreProduct(['name' => 'Original Rice']);
    OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id,
        'price' => 37.50, 'tax' => 0, 'quantity' => 3,
    ]);

    $html = view('shop.emails.invoice', ['mailLocale' => $locale, 'order' => $order->fresh()->load('orderDetails.product')])->render();

    expect($html)->toContain($brandName, 'MAIL-INV-1', 'Original Rice', '37.50', '#c4170c', 'assets/img/33logoqr.png')
        ->not->toContain('#eceff4')
        ->not->toContain('fonts.googleapis.com');
})->with([['ar', 'اسم المتجر'], ['en', 'Store name']]);

/*
 * Customer-facing PDFs (mPDF): brand colours, logo and brand name; DejaVu Sans
 * stays so Arabic keeps shaping. The admin data exports are not restyled.
 */

test('the invoice PDF view takes the brand colours, logo and name and still renders in both languages', function (string $locale, string $dir, string $brandName) {
    $customer = createStoreUser();
    $order = createStoreOrder($customer, ['code' => 'PDF-INV-1', 'grand_total' => 37.50]);
    $product = createStoreProduct(['name' => 'Original Rice']);
    OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id,
        'price' => 37.50, 'tax' => 0, 'quantity' => 3,
    ]);
    $data = ['documentLocale' => $locale, 'font_family' => 'dejavusans', 'order' => $order->fresh()->load('orderDetails.product'), 'cartResults' => []];

    $html = view('shop.backend.invoices.invoice', $data)->render();

    expect($html)->toContain('lang="'.$locale.'" dir="'.$dir.'"', $brandName, 'PDF-INV-1', '#c4170c', '#faf7f4', 'assets/img/33logoqr.png', 'dejavusans')
        ->not->toContain('#eceff4')
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('<script');

    expect(Pdf::loadView('shop.backend.invoices.invoice', $data, [], [])->output())->toStartWith('%PDF-');
})->with([['ar', 'rtl', 'اسم المتجر'], ['en', 'ltr', 'Store name']]);

test('the payment receipt PDF view takes the brand colours, logo and name', function (string $locale, string $brandName) {
    $payment = (new RepresentativePayment)->forceFill([
        'receipt_code' => 'R-001', 'invoice_code' => 'I-9', 'amount' => 125.5, 'date' => '2026-10-08', 'notes' => 'Note',
    ]);

    $html = view('shop.frontend.user.export-rep-payments', ['documentLocale' => $locale, 'payment' => $payment, 'qr' => '<svg></svg>'])->render();

    expect($html)->toContain($brandName, 'R-001', '#c4170c', 'assets/img/33logoqr.png')
        ->not->toContain('#eceff4');
})->with([['ar', 'اسم المتجر'], ['en', 'Store name']]);

test('the admin data-export PDFs and the label sheets keep their own styling', function () {
    foreach (glob(resource_path('views/shop/backend/downloads/*.blade.php')) as $file) {
        expect(file_get_contents($file))->not->toContain('c4170c', $file);
    }

    expect(file_get_contents(resource_path('views/pdf/qr-labels.blade.php')))->not->toContain('c4170c');
});

<?php

use App\Mail\AuctionBidMailManager;
use App\Mail\ConversationMailManager;
use App\Mail\EmailManager;
use App\Mail\InvoiceEmailManager;
use App\Mail\LocalizedMail;
use App\Mail\SecondEmailVerifyMailManager;
use App\Mail\SupportMailManager;
use App\Models\Category;
use App\Models\OrderDetail;
use App\Models\ProductsExport;
use App\Models\RepresentativePayment;
use App\Models\StoreUser;
use App\Notifications\AppEmailVerificationNotification;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\NewDriverTripNotification;
use App\Notifications\OrderNotification;
use App\Notifications\PasswordResetRequest;
use App\Notifications\TermsAgreementNotification;
use App\Notifications\UnorderedCartNotification;
use App\Services\DriverService;
use App\Services\TripService;
use App\Support\StoreLocale;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use setasign\Fpdi\TcpdfFpdi;
use Smalot\PdfParser\Parser;
use Symfony\Component\Mime\Email;

class DeliveryLocaleRecipient implements HasLocalePreference
{
    public function __construct(private string $preference) {}

    public function preferredLocale(): string
    {
        return $this->preference;
    }
}

function deliverLocalizedMail(LocalizedMail $mail): Email
{
    $job = unserialize(serialize(new SendQueuedMailable($mail->mailer('array')->to('recipient@example.test'))));
    $job->handle(app(MailFactory::class));

    return Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

beforeEach(function () {
    config(['app.ui_default_locale' => 'ar', 'app.default_language' => 'en', 'mail.default' => 'array']);
});

test('delivery defaults cannot inherit browser or worker locale and honor valid recipient preferences', function () {
    request()->setLaravelSession(app('session')->driver());
    app()->setLocale('en');
    session(['locale' => 'en']);
    expect(StoreLocale::deliveryLocale())->toBe('ar')
        ->and(StoreLocale::deliveryLocale(new DeliveryLocaleRecipient('sa')))->toBe('ar')
        ->and(StoreLocale::deliveryLocale(new DeliveryLocaleRecipient('en')))->toBe('en')
        ->and(StoreLocale::deliveryLocale(new DeliveryLocaleRecipient('invalid')))->toBe('ar')
        ->and(StoreLocale::deliveryLocale(new StoreUser))->toBe('ar');

    config(['app.ui_default_locale' => 'en']);
    app()->setLocale('ar');
    expect(StoreLocale::deliveryLocale())->toBe('en')
        ->and(StoreLocale::documentLocale())->toBe('en');
    session(['locale' => 'sa']);
    expect(StoreLocale::documentLocale())->toBe('ar')
        ->and(StoreLocale::deliveryLocale())->toBe('en')
        ->and(config('app.default_language'))->toBe('en');
});

test('queued static mail renders subjects HTML and text for each recipient without translation records or locale leaks', function (string $locale, string $subject, string $heading) {
    expect(DB::table('translations')->count())->toBe(0);
    $mail = new SupportMailManager([
        'view' => 'shop.emails.support', 'from' => 'support@example.test',
        'subject_key' => 'storefront.support.ticket_subject', 'subject_parameters' => ['code' => 'T-123'],
        'content_key' => 'storefront.support.ticket_created',
        'sender' => 'Alice <script>alert(1)</script>', 'details' => '<b>Help</b><script>alert(2)</script>',
        'link' => 'https://example.test/ticket?x=1&y=2',
    ], new DeliveryLocaleRecipient($locale));
    expect($mail->locale)->toBe($locale === 'sa' ? 'ar' : $locale);
    app()->setLocale('sa');
    app('translator')->setLocale('ar');
    session(['locale' => 'en']);
    startStorefrontTranslationQueryLog();
    $message = deliverLocalizedMail($mail);
    expect($message->getSubject())->toBe($subject)
        ->and($message->getHtmlBody())->toContain($heading, 'dir="'.($locale === 'en' ? 'ltr' : 'rtl').'"', 'Alice &lt;script&gt;', '<b>Help</b>', 'x=1&amp;y=2')
        ->not->toContain('<script>', 'delivery.', 'storefront.')
        ->and($message->getTextBody())->toContain($heading, 'Help', 'https://example.test/ticket?x=1&y=2')
        ->and(app()->getLocale())->toBe('sa')
        ->and(app('translator')->getLocale())->toBe('ar');
    expectNoStorefrontTranslationQueries();
})->with([
    ['en', 'Support ticket Code is:- T-123', 'ticket'],
    ['ar', 'رمز تذكرة الدعم هو: T-123', 'تذكرة دعم'],
    ['sa', 'رمز تذكرة الدعم هو: T-123', 'تذكرة دعم'],
]);

test('mail jobs for different recipients retain their own locales in one worker', function () {
    $payload = ['view' => 'shop.emails.newsletter', 'from' => 'support@example.test', 'subject_key' => 'delivery.mail.smtp_subject', 'content_key' => 'delivery.mail.smtp_body'];
    $english = new EmailManager($payload, new DeliveryLocaleRecipient('en'));
    $arabic = new EmailManager($payload, new DeliveryLocaleRecipient('ar'));
    app()->setLocale('en');
    expect(deliverLocalizedMail($arabic)->getSubject())->toBe('اختبار SMTP')
        ->and(deliverLocalizedMail($english)->getSubject())->toBe('SMTP Test')
        ->and(deliverLocalizedMail($arabic)->getTextBody())->toContain('هذه رسالة بريد إلكتروني تجريبية.')
        ->and(app()->getLocale())->toBe('en');
});

test('conversation and auction mail use safe interpolation while newsletter copy stays authored content', function () {
    startStorefrontTranslationQueryLog();
    $conversation = deliverLocalizedMail(new ConversationMailManager([
        'from' => 'support@example.test', 'subject_key' => 'storefront.support.sender_subject', 'subject_parameters' => ['name' => 'A & B'],
        'content_key' => 'storefront.support.received_message', 'content_parameters' => ['name' => '<img src=x onerror=alert(1)>'],
        'sender' => 'A & B', 'details' => '<script>message</script>', 'link' => 'https://example.test/chat',
    ]));
    expect($conversation->getSubject())->toContain('A & B')
        ->and($conversation->getHtmlBody())->toContain('&lt;img', '&lt;script&gt;message&lt;/script&gt;')->not->toContain('<script>', '<img src=x');

    $auction = deliverLocalizedMail(new AuctionBidMailManager([
        'from' => 'support@example.test', 'subject_key' => 'delivery.mail.auction_subject', 'content_key' => 'delivery.mail.outbid',
        'content_parameters' => ['product' => 'Rice & <b>Beans</b>', 'amount' => '125.50'], 'link' => 'https://example.test/product',
    ]));
    expect($auction->getSubject())->toBe('مزايدة على منتج')
        ->and($auction->getHtmlBody())->toContain('Rice &amp; &lt;b&gt;Beans&lt;/b&gt;', '125.50', 'lang="ar"');

    $newsletter = deliverLocalizedMail(new EmailManager([
        'view' => 'shop.emails.newsletter', 'subject' => 'Custom عروض', 'content' => '<p>Business <strong>content</strong></p>', 'from' => 'support@example.test',
    ]));
    expect($newsletter->getSubject())->toBe('Custom عروض')
        ->and($newsletter->getHtmlBody())->toContain('<strong>content</strong>')
        ->and($newsletter->getTextBody())->toContain('Business content');
    expectNoStorefrontTranslationQueries();
});

test('verification code mail defers static copy until queued delivery', function () {
    $mail = new SecondEmailVerifyMailManager([
        'from' => 'support@example.test', 'subject_key' => 'storefront.auth.password_reset_subject',
        'content_key' => 'storefront.auth.verification_code_message', 'content_parameters' => ['code' => '123456'],
    ], new DeliveryLocaleRecipient('en'));
    app()->setLocale('ar');
    $message = deliverLocalizedMail($mail);
    expect($message->getSubject())->toBe('Password Reset')
        ->and($message->getHtmlBody())->toContain('123456', 'lang="en"', 'dir="ltr"')
        ->and($message->getTextBody())->toContain('123456');
});

test('serialized auth notifications resolve a delivery locale without relying on a session', function (string $locale) {
    config(['app.ui_default_locale' => $locale]);
    $customer = createStoreUser();
    app()->setLocale($locale === 'ar' ? 'en' : 'ar');
    session(['locale' => $locale === 'ar' ? 'en' : 'ar']);
    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();
    foreach ([new AppEmailVerificationNotification('654321'), new EmailVerificationNotification, new TermsAgreementNotification, new PasswordResetRequest('reset-token')] as $notification) {
        $restored = unserialize(serialize($notification));
        $message = $restored->toMail($customer);
        $html = view($message->view['html'], $message->viewData)->render();
        $plain = view($message->view['text'], $message->viewData)->render();
        expect($message->subject)->not->toContain('storefront.', 'delivery.')
            ->and($html)->toContain('lang="'.$locale.'"', 'dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"')
            ->and($plain)->not->toBe('');
        if ($notification instanceof AppEmailVerificationNotification) {
            expect($html)->toContain('654321')->and($plain)->toContain('654321');
        }
        if ($notification instanceof PasswordResetRequest) {
            expect($html)->toContain('reset-token', $locale === 'ar' ? 'إعادة تعيين كلمة المرور' : 'Reset password');
        }
    }
    expectNoStorefrontTranslationQueries();
})->with(['ar', 'en']);

test('cart and order notification payloads keep identifiers and user authored historical copy', function () {
    $recipient = new DeliveryLocaleRecipient('ar');
    $cart = new UnorderedCartNotification('Alice', 123, 2);
    expect($cart->via($recipient))->toBe(['database'])
        ->and($cart->toArray($recipient))->toBe([
            'user_name' => 'Alice', 'user_id' => 123, 'cart_count' => 2,
            'message' => 'أضاف منتجين إلى سلته ولم يقدّم طلبًا منذ خمسة أيام.',
        ])
        ->and((new UnorderedCartNotification('Alice', 123, 2, 'Original stored content'))->toArray($recipient)['message'])->toBe('Original stored content');
    $data = ['order_id' => 1, 'order_code' => 'INV-42', 'user_id' => 2, 'seller_id' => 3, 'status' => 'paid'];
    $order = new OrderNotification($data);
    expect($order->via($recipient))->toBe(['database'])->and($order->toArray($recipient))->toBe($data);
    $mail = $order->toMail($recipient);
    expect(view($mail->view['html'], $mail->viewData)->render())->toContain('INV-42', 'مدفوع');
});

test('both delivery catalogs have matching keys and replacement tokens', function () {
    $english = Arr::dot(require lang_path('en/delivery.php'));
    $arabic = Arr::dot(require lang_path('ar/delivery.php'));
    expect(array_keys($english))->toBe(array_keys($arabic));
    foreach ($english as $key => $value) {
        preg_match_all('/:[a-z_]+/', $value, $enTokens);
        preg_match_all('/:[a-z_]+/', $arabic[$key], $arTokens);
        expect(array_values(array_unique($enTokens[0])))->toEqualCanonicalizing(array_values(array_unique($arTokens[0])));
    }
});

test('product spreadsheet headings remain the import contract in either locale', function (string $locale) {
    app()->setLocale($locale);
    expect((new ProductsExport)->headings())->toBe([
        'name', 'description', 'added_by', 'user_id', 'category_id', 'brand_id', 'video_provider', 'video_link',
        'unit_price', 'purchase_price', 'unit', 'current_stock', 'meta_title', 'meta_description',
    ]);
})->with(['ar', 'en']);

test('invoice HTML and PDF retain amounts identifiers and dynamic content in both languages', function (string $locale, string $orderLabel, string $totalLabel) {
    seedStoreCurrency();
    app()->setLocale('en');
    DB::table('business_settings')->insert(['type' => 'no_of_decimals', 'value' => '2']);
    $customer = createStoreUser();
    $order = createStoreOrder($customer, ['code' => 'INV-ABC-123', 'grand_total' => 37.50, 'company_tax_number' => 'NL123ABC']);
    $product = createStoreProduct(['name' => 'Original Rice']);
    $product->forceFill(['ar_name' => 'أرز أصلي', 'serial' => 987654321])->save();
    $detail = OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id, 'product_serial' => $product->serial,
        'price' => 37.50, 'tax' => 4.25, 'quantity' => 3, 'item_notes' => '<script>notes</script>',
    ]);
    $order->load('orderDetails.product');
    $data = ['documentLocale' => $locale, 'font_family' => 'dejavusans', 'order' => $order, 'cartResults' => []];
    startStorefrontTranslationQueryLog();
    $html = view('shop.backend.invoices.invoice', $data)->render();
    expect($html)->toContain('lang="'.$locale.'"', 'dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', $orderLabel, $totalLabel)
        ->toContain('Original Riceأرز أصلي', 'INV-ABC-123', 'NL123ABC', 'dir="ltr"', '12.50', '37.50', '&lt;script&gt;notes&lt;/script&gt;')
        ->not->toContain('<script>', '41.75', 'delivery.', 'storefront.');
    expectNoStorefrontTranslationQueries();
    expect($order->fresh()->grand_total)->toEqual(37.50)
        ->and($detail->fresh()->price)->toEqual(37.50)->and($detail->fresh()->tax)->toEqual(4.25);

    $pdf = PDF::loadView('shop.backend.invoices.invoice', $data)->output();
    expect($pdf)->toStartWith('%PDF-');
})->with([
    ['en', 'Order', 'Grand total'],
    ['ar', 'طلب', 'الإجمالي النهائي'],
]);

test('reference downloads keep content language while localizing human headings', function (string $locale, string $heading) {
    $category = Category::forceCreate(['name' => 'Base category']);
    $category->category_translations()->create(['lang' => 'sa', 'name' => 'تصنيف المحتوى القديم']);
    $category->category_translations()->create(['lang' => 'ar', 'name' => 'Different content']);
    $category->load('category_translations');
    app()->setLocale('sa');
    startStorefrontTranslationQueryLog();
    $html = view('shop.backend.downloads.category', ['documentLocale' => $locale, 'categories' => collect([$category])])->render();
    expect($html)->toContain($heading, 'تصنيف المحتوى القديم', 'lang="'.$locale.'"', 'dir="ltr"')
        ->not->toContain('Different content');
    expectNoStorefrontTranslationQueries();
    expect(app()->getLocale())->toBe('sa')->and(config('app.default_language'))->toBe('en');
})->with([
    ['en', 'Category name'],
    ['ar', 'اسم الفئة'],
]);

test('receipt labels and direction do not alter its monetary and identifier values', function (string $locale, string $label) {
    $payment = new RepresentativePayment;
    $payment->forceFill([
        'receipt_code' => 'R-001', 'invoice_code' => 42, 'amount' => '125.50', 'otajer_id' => 'ACC-5',
        'date' => '2026-10-08 10:00:00', 'notes' => '<b>Customer note</b>',
    ])->setRelation('rep', null)->setRelation('customer', null);
    startStorefrontTranslationQueryLog();
    $html = view('shop.frontend.user.export-rep-payments', ['documentLocale' => $locale, 'payment' => $payment, 'qr' => '<svg></svg>'])->render();
    expect($html)->toContain($label, 'R-001', '125.50', '2026-10-08', '&lt;b&gt;Customer note&lt;/b&gt;', 'lang="'.$locale.'"', 'dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"')
        ->not->toContain('<b>Customer note</b>');
    expectNoStorefrontTranslationQueries();
})->with([
    ['en', 'Receipt Code'],
    ['ar', 'رمز الإيصال'],
]);

test('the retained invoice email has localized HTML and text without changing price visibility', function (string $locale) {
    seedStoreCurrency();
    $order = createStoreOrder(createStoreUser(), ['code' => 'EMAIL-INV-12']);
    $message = deliverLocalizedMail(new InvoiceEmailManager([
        'view' => 'shop.emails.invoice', 'from' => 'support@example.test',
        'subject_key' => 'delivery.mail.order_placed_subject', 'subject_parameters' => ['code' => $order->code], 'order' => $order,
    ], new DeliveryLocaleRecipient($locale)));
    expect($message->getSubject())->toContain('EMAIL-INV-12')
        ->and($message->getHtmlBody())->toContain('lang="'.$locale.'"', 'EMAIL-INV-12')
        ->and($message->getTextBody())->toContain('EMAIL-INV-12');
})->with(['en', 'ar']);

test('stored static overrides cannot replace delivery catalog text', function () {
    DB::table('translations')->insert(['lang' => 'ar', 'lang_key' => 'ticket', 'lang_value' => 'Database override']);
    startStorefrontTranslationQueryLog();
    $message = deliverLocalizedMail(new SupportMailManager([
        'view' => 'shop.emails.support', 'from' => 'support@example.test',
        'subject_key' => 'storefront.support.ticket_subject', 'subject_parameters' => ['code' => 'T-1'],
        'content_key' => 'storefront.support.ticket_created', 'sender' => 'Alice', 'details' => 'Help', 'link' => 'https://example.test/ticket',
    ]));
    expect($message->getHtmlBody())->toContain('تذكرة دعم')->not->toContain('Database override');
    expectNoStorefrontTranslationQueries();
});

test('trip notifications resolve the driver delivery language and preserve their database contract', function (string $locale, string $title) {
    config(['app.ui_default_locale' => $locale]);
    $driver = createStoreUser('driver');
    $trip = (object) [
        'id' => 123, 'driver_id' => $driver->id, 'code' => 'TRIP-123',
        'truck' => (object) ['name' => 'Truck A', 'photos' => ''],
    ];
    Notification::fake();
    app()->setLocale($locale === 'ar' ? 'en' : 'ar');
    startStorefrontTranslationQueryLog();
    (new DriverService)->notifyDriver($trip);
    Notification::assertSentTo($driver, NewDriverTripNotification::class, function ($notification, array $channels) use ($driver, $title): bool {
        $restored = unserialize(serialize($notification));
        $data = $restored->toArray($driver);
        expect($channels)->toBe(['database'])->and(array_keys($data))->toBe(['message', 'body', 'trip_id', 'trip_code', 'truck_name', 'truck_images'])
            ->and($data['message'])->toBe($title)->and($data['body'])->toContain('TRIP-123')
            ->and($data['trip_id'])->toBe(123)->and($data['trip_code'])->toBe('TRIP-123')->and($data['truck_name'])->toBe('Truck A');

        return true;
    });
    expectNoStorefrontTranslationQueries();
})->with([
    ['en', 'New trip assigned!'],
    ['ar', 'تم إسناد رحلة جديدة إليك!'],
]);

test('CMR PDFs preserve customer identifiers beside localized labels', function (string $locale) {
    $pdf = new TcpdfFpdi;
    $pdf->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_language' => $locale, 'a_meta_dir' => 'ltr']);
    $pdf->AddPage();
    $customer = (object) [
        'company_name' => 'Example company', 'tax_number' => 'NL123ABC',
        'address' => 'Rotterdam', 'phone' => '+31123456789', 'email' => 'customer@example.test',
    ];
    $service = new TripService;
    (new ReflectionMethod($service, 'sectionTwo'))->invokeArgs($service, [&$pdf, $customer, $locale]);

    $text = (new Parser)->parseContent($pdf->Output('', 'S'))->getText();
    expect($text)->toContain('NL123ABC', '+31123456789', 'customer@example.test');
})->with(['en', 'ar']);

test('delivery and retained document sources do not call the static database translation helper', function () {
    $roots = [app_path('Mail'), app_path('Notifications'), resource_path('views/shop/emails'), resource_path('views/shop/backend/downloads'), resource_path('views/shop/backend/invoices')];
    foreach ($roots as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = preg_replace('/{{--.*?--}}/s', '', file_get_contents($file->getPathname()));
            expect($source)->not->toMatch('/\btranslate\s*\(/', $file->getPathname());
        }
    }
});

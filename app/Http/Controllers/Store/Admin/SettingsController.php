<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\TestSmtpRequest;
use App\Http\Requests\Store\Admin\UpdateStoreSettingsRequest;
use App\Mail\EmailManager;
use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller implements HasMiddleware
{
    /** @var array<string, array{permission: string, index: string, keys: list<string>}> */
    public const PAGES = [
        'activation' => [
            'permission' => 'features_activation',
            'index' => 'activation.index',
            'keys' => [
                'disable_image_optimization',
                'vendor_system_activation',
                'classified_product',
                'wallet_system',
                'coupon_system',
                'pickup_point',
                'conversation_system',
                'product_manage_by_admin',
                'product_approve_by_admin',
                'email_verification',
                'product_query_activation',
                'In_House_Products_Section',
                'Digital_Products_Section',
                'Product_Reviews_Section',
                'In_House_Orders_Section',
                'Pickup_Orders_Section',
                'Payouts_Section',
                'Payout_request_Section',
                'Seller_Commission_Section',
                'Seller_Verify_Form_Section',
                'Shop_Ratings',
                'Reports_Section',
                'In_House_Sales_Report',
                'Products_Stock_Report',
                'Products_Wishlist_Report',
                'User_Searches_Report',
                'Commission_History_Report',
                'Wallet_Recharge_History',
                'Blog_Section',
                'Marketing_Section',
                'Flash_deals',
                'Newsletters',
                'Support_Section',
                'website_setup',
                'website_setup_header',
                'website_setup_footer',
                'website_setup_pages',
                'website_setup_appearance',
                'Setup_configuration_section',
                'Languages_section',
                'currency_section',
                'VAT_TAX_section',
                'Setup_pickup_point_section',
                'SMTP_Section',
                'Order_configuration_section',
                'Socail_Media_logins_section',
                'Facebook_section',
                'Setup_Shipping_section',
                'qr_connection_system',
                'Staffs_section',
                'sslcommerz_payment',
                'voguepay',
                'payhere',
                'ngenius',
                'iyzico',
                'bkash',
                'nagad',
                'aamarpay',
                'cash_payment',
                'facebook_login',
                'google_login',
                'twitter_login',
                'apple_login',
            ],
        ],
        'smtp' => ['permission' => 'smtp_settings', 'index' => 'smtp_settings.index', 'keys' => []],
        'socialLogin' => ['permission' => 'social_media_logins', 'index' => 'social_login.index', 'keys' => []],
        'facebookChat' => ['permission' => 'facebook_chat', 'index' => 'facebook_chat.index', 'keys' => ['facebook_chat']],
        'facebookComments' => ['permission' => 'facebook_comment', 'index' => 'facebook-comment', 'keys' => ['facebook_comment']],
        'analytics' => ['permission' => 'analytics_tools_configuration', 'index' => 'google_analytics.index', 'keys' => ['google_analytics', 'facebook_pixel']],
        'recaptcha' => ['permission' => 'google_recaptcha_configuration', 'index' => 'google_recaptcha.index', 'keys' => ['google_recaptcha']],
        'firebase' => ['permission' => 'google_firebase_setting', 'index' => 'google-firebase.index', 'keys' => ['google_firebase']],
        'payments' => ['permission' => 'payment_methods_configurations', 'index' => 'payment_method.index', 'keys' => ['bkash_sandbox', 'sslcommerz_sandbox', 'aamarpay_sandbox', 'iyzico_sandbox', 'payhere_sandbox', 'voguepay_sandbox']],
        'filesystem' => ['permission' => 'file_system_&_cache_configuration', 'index' => 'file_system.index', 'keys' => []],
    ];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:features_activation', only: ['activation', 'updateActivation']),
            new Middleware('permission:smtp_settings', only: ['smtp', 'testSmtp']),
            new Middleware('permission:social_media_logins', only: ['socialLogin']),
            new Middleware('permission:facebook_chat', only: ['facebookChat', 'updateFacebookChat']),
            new Middleware('permission:facebook_comment', only: ['facebookComments', 'updateFacebookComments']),
            new Middleware('permission:analytics_tools_configuration', only: ['analytics', 'updateAnalytics']),
            new Middleware('permission:google_recaptcha_configuration', only: ['recaptcha', 'updateRecaptcha']),
            new Middleware('permission:google_firebase_setting', only: ['firebase', 'updateFirebase']),
            new Middleware('permission:payment_methods_configurations', only: ['payments', 'updatePayments']),
            new Middleware('permission:file_system_&_cache_configuration', only: ['filesystem']),
            new Middleware('RaizerSuperAdmin', only: ['activation', 'updateActivation']),
            new Middleware('permission:send_newsletter', only: ['testSmtp']),
        ];
    }

    public function activation(): Response
    {
        return $this->page('activation');
    }

    public function updateActivation(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'activation');
    }

    public function smtp(): Response
    {
        return $this->page('smtp');
    }

    public function socialLogin(): Response
    {
        return $this->page('socialLogin');
    }

    public function facebookChat(): Response
    {
        return $this->page('facebookChat');
    }

    public function updateFacebookChat(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'facebookChat');
    }

    public function facebookComments(): Response
    {
        return $this->page('facebookComments');
    }

    public function updateFacebookComments(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'facebookComments');
    }

    public function analytics(): Response
    {
        return $this->page('analytics');
    }

    public function updateAnalytics(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'analytics');
    }

    public function recaptcha(): Response
    {
        return $this->page('recaptcha');
    }

    public function updateRecaptcha(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'recaptcha');
    }

    public function firebase(): Response
    {
        return $this->page('firebase');
    }

    public function updateFirebase(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'firebase');
    }

    public function payments(): Response
    {
        return $this->page('payments');
    }

    public function updatePayments(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'payments');
    }

    public function filesystem(): Response
    {
        return $this->page('filesystem');
    }

    public function testSmtp(TestSmtpRequest $request): RedirectResponse
    {
        Mail::to($request->validated('email'))->queue(new EmailManager([
            'view' => 'shop.emails.newsletter',
            'subject' => 'SMTP Test',
            'from' => config('mail.from.address'),
            'content' => 'This is a test email.',
        ]));

        return redirect()->route('smtp_settings.index');
    }

    private function page(string $page): Response
    {
        $settings = BusinessSetting::query()->whereIn('type', self::PAGES[$page]['keys'])
            ->select(['type', 'value'])->pluck('value', 'type');

        return Inertia::render('Store/Admin/Settings/Index', [
            'section' => $page,
            'settings' => collect(self::PAGES[$page]['keys'])->mapWithKeys(
                fn (string $key): array => [$key => (string) $settings->get($key, '0') === '1']
            )->all(),
        ]);
    }

    private function save(UpdateStoreSettingsRequest $request, string $page): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('settings') as $key => $value) {
                $setting = BusinessSetting::query()->where('type', $key)->whereNull('lang')->first() ?? new BusinessSetting;
                $setting->type = $key;
                $setting->value = $value ? '1' : '0';
                $setting->save();
            }
        });
        Cache::forget('business_settings');

        return redirect()->route(self::PAGES[$page]['index']);
    }
}

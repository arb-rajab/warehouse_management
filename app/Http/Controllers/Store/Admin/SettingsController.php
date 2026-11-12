<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\ReadsHighlight;
use App\Http\Requests\Store\Admin\TestSmtpRequest;
use App\Http\Requests\Store\Admin\UpdateIntegrationIdsRequest;
use App\Http\Requests\Store\Admin\UpdatePaymentGatewaySettingsRequest;
use App\Http\Requests\Store\Admin\UpdateRecaptchaSettingsRequest;
use App\Http\Requests\Store\Admin\UpdateSmtpSettingsRequest;
use App\Http\Requests\Store\Admin\UpdateStoreSettingsRequest;
use App\Mail\EmailManager;
use App\Models\AdminAuditLog;
use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\StoreUser;
use App\Services\FcmPushService;
use App\Services\Store\PaymentGatewaySettingsChangeNotifier;
use App\Services\Store\SmtpSettingsChangeNotifier;
use App\Support\StoreIntegrationIds;
use App\Support\StoreLocale;
use App\Support\StoreMailSettings;
use App\Support\StorePaymentGateways;
use App\Support\StoreRecaptchaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SettingsController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use ReadsHighlight;

    /**
     * The SMTP page's fields a setup checklist link may highlight: the mailer,
     * the host and the username/password pair (SetupChecklist::TARGETS).
     *
     * @var list<string>
     */
    public const array SMTP_HIGHLIGHTS = ['mail_mailer', 'mail_host', 'mail_credentials'];

    /**
     * `keys` are the page's switches; `sections` are the other fields or blocks
     * a deep link may also highlight (the server values behind a setup check,
     * the ID fields). `component` is a dedicated page that replaces the plain
     * switch list; `ids` pairs a switch with the public ID it prints
     * (StoreIntegrationIds). The SMTP page is not one of these pages: it edits
     * StoreMailSettings. The payments page's `keys` are its sandbox switches;
     * it edits them with every gateway's settings (StorePaymentGateways) and
     * marks the keys of paymentHighlights().
     *
     * @var array<string, array{permission: string, index: string, keys: list<string>, sections?: list<string>, component?: string, ids?: array<string, string>}>
     */
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
        'socialLogin' => ['permission' => 'social_media_logins', 'index' => 'social_login.index', 'keys' => []],
        'facebookChat' => [
            'permission' => 'facebook_chat', 'index' => 'facebook_chat.index', 'keys' => ['facebook_chat'],
            'sections' => ['facebook_page_id'], 'component' => 'Store/Admin/Settings/FacebookChat',
            'ids' => ['facebook_chat' => 'facebook_page_id'],
        ],
        'facebookComments' => [
            'permission' => 'facebook_comment', 'index' => 'facebook-comment', 'keys' => ['facebook_comment'],
            'sections' => ['facebook_app_id'], 'component' => 'Store/Admin/Settings/FacebookComments',
            'ids' => ['facebook_comment' => 'facebook_app_id'],
        ],
        'analytics' => [
            'permission' => 'analytics_tools_configuration', 'index' => 'google_analytics.index', 'keys' => ['google_analytics', 'facebook_pixel'],
            'sections' => ['google_analytics_tracking_id', 'facebook_pixel_id'], 'component' => 'Store/Admin/Settings/Analytics',
            'ids' => ['google_analytics' => 'google_analytics_tracking_id', 'facebook_pixel' => 'facebook_pixel_id'],
        ],
        'recaptcha' => [
            'permission' => 'google_recaptcha_configuration', 'index' => 'google_recaptcha.index', 'keys' => ['google_recaptcha'],
            'sections' => ['recaptcha_site_key', 'recaptcha_secret_key'], 'component' => 'Store/Admin/Settings/Recaptcha',
        ],
        'firebase' => ['permission' => 'google_firebase_setting', 'index' => 'google-firebase.index', 'keys' => ['google_firebase'], 'sections' => ['firebase_server']],
        // The six sandbox switches are edited per gateway with the gateway's
        // credentials (StorePaymentGateways), not as a switch list.
        'payments' => [
            'permission' => 'payment_methods_configurations', 'index' => 'payment_method.index',
            'keys' => ['bkash_sandbox', 'sslcommerz_sandbox', 'aamarpay_sandbox', 'iyzico_sandbox', 'payhere_sandbox', 'voguepay_sandbox'],
            'component' => 'Store/Admin/Settings/Payments',
        ],
        'filesystem' => ['permission' => 'file_system_&_cache_configuration', 'index' => 'file_system.index', 'keys' => []],
    ];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:features_activation', only: ['activation', 'updateActivation']),
            new Middleware('permission:smtp_settings', only: ['smtp', 'updateSmtp', 'testSmtp']),
            new Middleware('permission:social_media_logins', only: ['socialLogin']),
            new Middleware('permission:facebook_chat', only: ['facebookChat', 'updateFacebookChat']),
            new Middleware('permission:facebook_comment', only: ['facebookComments', 'updateFacebookComments']),
            new Middleware('permission:analytics_tools_configuration', only: ['analytics', 'updateAnalytics']),
            new Middleware('permission:google_recaptcha_configuration', only: ['recaptcha', 'updateRecaptcha']),
            new Middleware('permission:google_firebase_setting', only: ['firebase', 'updateFirebase']),
            new Middleware('permission:payment_methods_configurations', only: ['payments', 'updatePayments']),
            new Middleware('permission:file_system_&_cache_configuration', only: ['filesystem']),
            new Middleware('permission:send_newsletter', only: ['testSmtp']),
        ];
    }

    public function activation(Request $request): Response
    {
        return $this->page('activation', $request);
    }

    public function updateActivation(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'activation');
    }

    /**
     * The SMTP settings form and where each effective value comes from. The
     * saved password and the `.env` username and password are never sent:
     * only whether each is set. `auditEntries` are the last 10 SMTP saves and
     * refused current passwords (AdminAuditLog): who, when, the IP and the
     * field names with how each changed; never a value or the user agent.
     */
    public function smtp(Request $request, StoreMailSettings $mail): Response
    {
        $stored = $mail->stored();
        $effective = $mail->effective();

        return Inertia::render('Store/Admin/Settings/Smtp', [
            'highlight' => $this->highlight($request, self::SMTP_HIGHLIGHTS),
            'smtp' => [
                'mailer' => $stored['mailer'],
                'host' => $stored['host'],
                'port' => $stored['port'] === null ? null : (int) $stored['port'],
                'scheme' => $stored['scheme'],
                'username' => $stored['username'],
                'from_address' => $stored['from_address'],
                'from_name' => $stored['from_name'],
                'password_set' => $stored['password'] !== null,
                'password_unreadable' => $mail->passwordUnreadable(),
                'environment_mailer' => $mail->environmentMailer(),
            ],
            'effective' => [
                'mailer' => $this->shown($effective['mailer']),
                'host' => $this->shown($effective['host']),
                'port' => $this->shown($effective['port']),
                'scheme' => $this->shown($effective['scheme']),
                'username' => ['set' => filled($effective['username']['value']), 'source' => $effective['username']['source']],
                'password' => ['set' => filled($effective['password']['value']), 'source' => $effective['password']['source']],
                'from_address' => $this->shown($effective['from_address']),
                'from_name' => $this->shown($effective['from_name']),
            ],
            'auditEntries' => $this->auditEntries([AdminAuditLog::SMTP_SETTINGS_UPDATED, AdminAuditLog::SMTP_SETTINGS_PASSWORD_FAILED]),
        ]);
    }

    /**
     * The last 10 rows of these audit actions: who, when, the IP and the field
     * names with how each changed; never a value or the user agent.
     *
     * @param  list<string>  $actions
     * @return list<array<string, mixed>>
     */
    private function auditEntries(array $actions): array
    {
        return AdminAuditLog::recent($actions)
            ->map(fn (AdminAuditLog $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'changed_fields' => (object) $entry->changed_fields,
                'user_name' => $entry->user->name ?? $entry->user_name,
                'user_email' => $entry->user->email ?? $entry->user_email,
                'user_deleted' => $entry->user === null,
                'user_id' => $entry->user_id,
                'ip_address' => $entry->ip_address,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Saves the page once the staff member's own password checked out
     * (UpdateSmtpSettingsRequest). When a field changed, the audit row is
     * written in the save's transaction, then every Super Admin (and the
     * previous from-address when that changed) is mailed through the settings
     * as they were before; a mail that fails is logged and never undoes the
     * save. A save that changes nothing records and mails nothing.
     */
    public function updateSmtp(UpdateSmtpSettingsRequest $request, StoreMailSettings $mail, SmtpSettingsChangeNotifier $notifier): RedirectResponse
    {
        /** @var StoreUser $actor */
        $actor = $request->user('store');
        $previous = $mail->effective();

        $changes = $mail->save(
            $request->safe()->except(['password_clear', 'current_password']),
            $request->boolean('password_clear'),
            fn (array $changes) => AdminAuditLog::record($actor, AdminAuditLog::SMTP_SETTINGS_UPDATED, $changes, $request),
        );

        if ($changes !== []) {
            $notifier->notify($actor, $changes, $previous, $request->ip(), now());
        }

        $this->flashSuccess('smtp_settings_have_been_updated_successfully');

        return redirect()->route('smtp_settings.index');
    }

    public function socialLogin(): Response
    {
        return $this->page('socialLogin');
    }

    public function facebookChat(Request $request): Response
    {
        return $this->page('facebookChat', $request);
    }

    public function updateFacebookChat(UpdateIntegrationIdsRequest $request): RedirectResponse
    {
        return $this->save($request, 'facebookChat');
    }

    public function facebookComments(Request $request): Response
    {
        return $this->page('facebookComments', $request);
    }

    public function updateFacebookComments(UpdateIntegrationIdsRequest $request): RedirectResponse
    {
        return $this->save($request, 'facebookComments');
    }

    public function analytics(Request $request): Response
    {
        return $this->page('analytics', $request);
    }

    public function updateAnalytics(UpdateIntegrationIdsRequest $request): RedirectResponse
    {
        return $this->save($request, 'analytics');
    }

    public function recaptcha(Request $request): Response
    {
        return $this->page('recaptcha', $request);
    }

    /**
     * The switch and the site key and secret pair. The secret is encrypted by
     * StoreRecaptchaSettings and never leaves it; an empty secret keeps the
     * saved one. A request without the pair (a switch-only post) leaves it alone.
     */
    public function updateRecaptcha(UpdateRecaptchaSettingsRequest $request, StoreRecaptchaSettings $recaptcha): RedirectResponse
    {
        DB::transaction(function () use ($request, $recaptcha): void {
            $this->saveToggles($request->validated('settings'));

            if ($request->has('recaptcha_site_key')) {
                $recaptcha->save([
                    'site_key' => $request->validated('recaptcha_site_key'),
                    'secret' => $request->validated('recaptcha_secret_key'),
                ], $request->boolean('recaptcha_secret_clear'));
            }
        });
        Cache::forget('business_settings');
        $this->flashSuccess('google_recaptcha_settings_have_been_updated_successfully');

        return redirect()->route(self::PAGES['recaptcha']['index']);
    }

    public function firebase(Request $request): Response
    {
        return $this->page('firebase', $request);
    }

    public function updateFirebase(UpdateStoreSettingsRequest $request): RedirectResponse
    {
        return $this->save($request, 'firebase');
    }

    /**
     * The Payment methods page: where the enabled list comes from and, per
     * gateway, its switch, mode, currency and credentials with the effective
     * values and their source. Saved secrets are never sent: only whether each
     * is saved and readable, and whether one is in effect.
     */
    public function payments(Request $request): Response
    {
        return $this->page('payments', $request);
    }

    /**
     * Saves the page once the staff member's own password checked out
     * (UpdatePaymentGatewaySettingsRequest). When something changed, the audit
     * row is written in the save's transaction and every Super Admin is mailed
     * (field names only); a mail that fails is logged and never undoes the
     * save. A save that changes nothing records and mails nothing.
     */
    public function updatePayments(UpdatePaymentGatewaySettingsRequest $request, PaymentGatewaySettingsChangeNotifier $notifier): RedirectResponse
    {
        /** @var StoreUser $actor */
        $actor = $request->user('store');

        $changes = StorePaymentGateways::save(
            $request->safe()->except(['current_password']),
            (int) $actor->getKey(),
            fn (array $changes) => AdminAuditLog::record($actor, AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_UPDATED, $changes, $request),
        );

        if ($changes !== []) {
            $notifier->notify($actor, $changes, $request->ip(), now());
        }

        $this->flashSuccess('payment_method_settings_have_been_updated_successfully');

        return redirect()->route('payment_method.index');
    }

    public function filesystem(): Response
    {
        return $this->page('filesystem');
    }

    /**
     * Sent at once, not queued, so a wrong host, port or login shows on the
     * page. The error names no setting value: the mail server's reply can
     * quote the username, and only the exception class is logged.
     */
    public function testSmtp(TestSmtpRequest $request): RedirectResponse
    {
        try {
            Mail::to($request->validated('email'))->send(new EmailManager([
                'view' => 'shop.emails.newsletter',
                'subject_key' => 'delivery.mail.smtp_subject',
                'locale' => StoreLocale::documentLocale(),
                'from' => config('mail.from.address'),
                'content_key' => 'delivery.mail.smtp_body',
            ]));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('The SMTP test email could not be sent.', ['exception' => $exception::class]);

            throw ValidationException::withMessages(['email' => __('store_admin.test_email_could_not_be_sent')]);
        }
        $this->flashSuccess('test_email_has_been_sent_successfully');

        return redirect()->route('smtp_settings.index');
    }

    /**
     * `?highlight=<setting key>` makes the page scroll to that option and mark it,
     * so a button that sends an admin here to change one option lands on it. Only
     * a key or section this page holds is passed on; anything else is ignored.
     */
    private function page(string $page, ?Request $request = null): Response
    {
        $sections = $page === 'payments' ? self::paymentHighlights() : (self::PAGES[$page]['sections'] ?? []);
        $highlight = $request === null ? null
            : $this->highlight($request, [...self::PAGES[$page]['keys'], ...$sections]);

        $settings = BusinessSetting::query()->whereIn('type', self::PAGES[$page]['keys'])
            ->select(['type', 'value'])->pluck('value', 'type');

        return Inertia::render(self::PAGES[$page]['component'] ?? 'Store/Admin/Settings/Index', [
            'section' => $page,
            'highlight' => $highlight,
            'settings' => collect(self::PAGES[$page]['keys'])->mapWithKeys(
                fn (string $key): array => [$key => (string) $settings->get($key, '0') === '1']
            )->all(),
            'server' => $this->server($page),
            ...$this->integration($page),
        ]);
    }

    /**
     * What a dedicated page adds to its switches. The IDs are public and shown
     * in full, each with the effective value and its source. The reCAPTCHA
     * secret is never sent: only whether one is saved, readable and in effect.
     *
     * @return array<string, mixed>
     */
    private function integration(string $page): array
    {
        if ($page === 'payments') {
            return [
                'payments' => [
                    'enabledSource' => StorePaymentGateways::enabledSource(),
                    'environmentEnabled' => StorePaymentGateways::environmentEnabled(),
                    'gateways' => $this->paymentGateways(),
                ],
                'auditEntries' => $this->auditEntries([AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_UPDATED, AdminAuditLog::PAYMENT_GATEWAY_SETTINGS_PASSWORD_FAILED]),
            ];
        }

        if ($page === 'recaptcha') {
            $recaptcha = app(StoreRecaptchaSettings::class);
            $stored = $recaptcha->stored();

            return [
                'recaptcha' => [
                    'site_key' => $stored['site_key'],
                    'secret_set' => $stored['secret'] !== null,
                    'secret_unreadable' => $recaptcha->secretUnreadable(),
                ],
                'effective' => $recaptcha->effective(),
            ];
        }

        $ids = self::PAGES[$page]['ids'] ?? null;

        if ($ids === null) {
            return [];
        }

        return ['ids' => collect($ids)->map(fn (string $field): array => [
            'name' => $field,
            'value' => StoreIntegrationIds::stored($field),
            'effective' => StoreIntegrationIds::effective($field),
        ])->all()];
    }

    /**
     * The keys the Payment methods page can mark: the enabled-list source and
     * every gateway's switch, mode and fields (`<gateway>.<field>`, the setup
     * checklist's targets).
     *
     * @return list<string>
     */
    public static function paymentHighlights(): array
    {
        $keys = ['enabled_source'];

        foreach (StorePaymentGateways::GATEWAYS as $gateway => $spec) {
            $keys[] = "{$gateway}.".StorePaymentGateways::ENABLED;
            $keys[] = "{$gateway}.mode";
            foreach (array_keys($spec['fields']) as $field) {
                $keys[] = "{$gateway}.{$field}";
            }
            foreach (array_keys(StorePaymentGateways::ENV_ONLY[$gateway] ?? []) as $field) {
                $keys[] = "{$gateway}.{$field}";
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Every gateway as the Payment methods page shows it. Public values are
     * sent in full (what is saved, and the `.env` value as a hint); a secret
     * only as saved / unreadable, and in `effective` as set or not set.
     *
     * @return list<array<string, mixed>>
     */
    private function paymentGateways(): array
    {
        $enabled = StorePaymentGateways::enabled();
        $offered = BusinessSetting::query()
            ->whereIn('type', array_values(array_filter(array_column(StorePaymentGateways::GATEWAYS, 'offered'))))
            ->orderByRaw('lang IS NOT NULL')->orderBy('id')->get(['type', 'value'])
            ->unique('type')->pluck('value', 'type');
        $rates = Currency::query()->where('status', 1)->pluck('code')
            ->map(fn ($code): string => strtoupper((string) $code))->all();
        $gateways = [];

        foreach (StorePaymentGateways::GATEWAYS as $gateway => $spec) {
            $stored = StorePaymentGateways::stored()[$gateway] ?? [];
            $fields = [];

            foreach ($spec['fields'] as $field => $fieldSpec) {
                $environment = config($fieldSpec['config']);

                $fields[] = [
                    'key' => $field,
                    'secret' => $fieldSpec['secret'],
                    'required' => $fieldSpec['required'],
                    'textarea' => ! empty($fieldSpec['textarea']),
                    'max' => $fieldSpec['max'] ?? null,
                    'options' => isset($fieldSpec['modes']) ? StorePaymentGateways::MODES : ($fieldSpec['in'] ?? null),
                    'env' => $fieldSpec['env'],
                    'value' => $fieldSpec['secret'] ? null : ($stored[$field]['value'] ?? null),
                    'set' => $fieldSpec['secret'] && isset($stored[$field]),
                    'unreadable' => $fieldSpec['secret'] && StorePaymentGateways::secretUnreadable($gateway, $field),
                    'environment' => match (true) {
                        $fieldSpec['secret'] => null,
                        isset($fieldSpec['modes']) => StorePaymentGateways::normalizeMode($gateway, $environment),
                        default => filled($environment) ? (string) $environment : null,
                    },
                ];
            }

            $currency = isset($spec['fields']['currency']) ? StorePaymentGateways::currency($gateway) : ($spec['fixed_currency'] ?? '');
            $modeKind = isset($spec['mode']['setting']) ? 'setting' : (isset($spec['mode']['derived']) ? 'derived' : 'field');

            $gateways[] = [
                'key' => $gateway,
                'name' => $spec['name'],
                'flow' => $spec['flow'],
                'identifying' => $spec['identifying'],
                'source' => StorePaymentGateways::usesAdmin($gateway) ? 'admin' : 'env',
                'enabled' => in_array($gateway, $enabled, true),
                'offered_setting' => $spec['offered'],
                'offered' => $spec['offered'] === null ? null : (string) $offered->get($spec['offered'], '0') === '1',
                'mode_kind' => $modeKind,
                'mode' => StorePaymentGateways::mode($gateway),
                'fixed_currency' => $spec['fixed_currency'] ?? null,
                'currency_without_rate' => $currency !== '' && ! in_array($currency, $rates, true) ? $currency : null,
                'fields' => $fields,
                'effective' => StorePaymentGateways::effective($gateway),
                'missing' => StorePaymentGateways::missing($gateway),
                'env_only' => collect(StorePaymentGateways::ENV_ONLY[$gateway] ?? [])
                    ->map(fn (array $envSpec, string $field): array => ['key' => $field, 'env' => $envSpec[0], 'set' => StorePaymentGateways::envOnlySet($envSpec[1])])
                    ->values()->all(),
                'live_unconfirmed' => StorePaymentGateways::liveUnconfirmed($gateway),
            ];
        }

        return $gateways;
    }

    /**
     * A non-secret effective mail value and where it comes from.
     *
     * @param  array{value: mixed, source: 'admin'|'env'}  $value
     * @return array{value: string|null, source: 'admin'|'env'}
     */
    private function shown(array $value): array
    {
        return ['value' => filled($value['value']) ? (string) $value['value'] : null, 'source' => $value['source']];
    }

    /**
     * Whether each server value a setup check reads is there, never the value
     * itself: Firebase push (FIREBASE_PROJECT, the FIREBASE_CREDENTIALS file).
     * Only the server environment sets them, so the page shows them read-only.
     *
     * @return array<string, bool>|null
     */
    private function server(string $page): ?array
    {
        if ($page === 'firebase') {
            $push = app(FcmPushService::class);
            $path = $push->credentialsPath();

            return [
                'project_set' => $push->project() !== null,
                'credentials_found' => $path !== null && is_file($path),
            ];
        }

        return null;
    }

    /** @param  array<string, mixed>  $settings  the validated `settings` switches */
    private function saveToggles(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $setting = BusinessSetting::query()->where('type', $key)->whereNull('lang')->first() ?? new BusinessSetting;
            $setting->type = $key;
            $setting->value = $value ? '1' : '0';
            $setting->save();
        }
    }

    private function save(UpdateStoreSettingsRequest|UpdateIntegrationIdsRequest $request, string $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $page) {
            $this->saveToggles($request->validated('settings'));

            // The IDs a dedicated page edits; an ID the request did not carry is left alone.
            if (isset(self::PAGES[$page]['ids'])) {
                StoreIntegrationIds::save($request->safe()->only(array_values(self::PAGES[$page]['ids'])));
            }
        });
        Cache::forget('business_settings');
        $this->flashSuccess(match ($page) {
            'activation' => 'feature_activation_settings_have_been_updated_successfully',
            'facebookChat' => 'facebook_chat_settings_have_been_updated_successfully',
            'facebookComments' => 'facebook_comments_settings_have_been_updated_successfully',
            'analytics' => 'google_analytics_settings_have_been_updated_successfully',
            'firebase' => 'google_firebase_settings_have_been_updated_successfully',
            default => 'payment_method_settings_have_been_updated_successfully',
        });

        return redirect()->route(self::PAGES[$page]['index']);
    }
}

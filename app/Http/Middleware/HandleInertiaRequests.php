<?php

namespace App\Http\Middleware;

use App\Http\Resources\Store\AdminNotificationResource;
use App\Models\BusinessSetting;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\Shop;
use App\Models\StoreUser;
use App\Support\StoreLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The business settings the store back office's sidebar reads to hide a
     * section the shop has switched off (resources/js/lib/storeAdminMenu.ts).
     * StoreInertiaSharedPropsTest fails when the two lists drift apart.
     *
     * @var list<string>
     */
    public const array STORE_MENU_SETTINGS = [
        'Blog_Section',
        'Commission_History_Report',
        'Digital_Products_Section',
        'Facebook_section',
        'Flash_deals',
        'In_House_Products_Section',
        'In_House_Sales_Report',
        'Languages_section',
        'Marketing_Section',
        'Newsletters',
        'Offer_deals',
        'Order_configuration_section',
        'Payout_request_Section',
        'Payouts_Section',
        'Pickup_Orders_Section',
        'Product_Reviews_Section',
        'Products_Stock_Report',
        'Products_Wishlist_Report',
        'Reports_Section',
        'SMTP_Section',
        'Seller_Commission_Section',
        'Seller_Verify_Form_Section',
        'Setup_Shipping_section',
        'Setup_configuration_section',
        'Setup_pickup_point_section',
        'Socail_Media_logins_section',
        'Staffs_section',
        'Support_Section',
        'User_Searches_Report',
        'VAT_TAX_section',
        'Wallet_Recharge_History',
        'classified_product',
        'conversation_system',
        'coupon_system',
        'currency_section',
        'product_query_activation',
        'vendor_system_activation',
        'website_setup',
        'website_setup_appearance',
        'website_setup_footer',
        'website_setup_header',
        'website_setup_pages',
    ];

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($this->isStoreRequest($request)) {
            return [...parent::share($request), ...$this->storeProps($request)];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'fallbackLocale' => config('app.fallback_locale'),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ],
        ];
    }

    /**
     * Whether this route runs on the store's guard: the /temp web routes,
     * whose groups start with UseStoreAuthGuard (RouteServiceProvider::map()).
     * Read from the route rather than the current default guard, which only
     * becomes `store` once that middleware has run.
     */
    private function isStoreRequest(Request $request): bool
    {
        return in_array(UseStoreAuthGuard::class, $request->route()?->gatherMiddleware() ?? [], true);
    }

    /**
     * The store back office's shared props. `auth.user` keeps WMS's narrow
     * shape (.ai/rules/middleware.md). The rest are closures because this
     * middleware runs before the store's Language middleware has set the
     * locale; Inertia resolves them when the page is built.
     *
     * `permissions` and `isSuperAdmin` drive the sidebar the way `@can` drove
     * the Blade one: a link shows when the user holds its permission, or is a
     * Super Admin, whom Gate::before() grants every ability whether or not
     * the permission row exists (AuthServiceProvider). They hide links only;
     * every route still checks its own permission.
     *
     * @return array<string, mixed>
     */
    private function storeProps(Request $request): array
    {
        return [
            'name' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'fallbackLocale' => config('app.fallback_locale'),
            'auth' => [
                'user' => fn () => $request->user('store')?->only(['id', 'name', 'email']),
            ],
            'store' => fn () => [
                'rtl' => StoreLocale::isRtl(),
                'permissions' => $request->user('store')?->getAllPermissions()->pluck('name')->sort()->values()->all() ?? [],
                'isSuperAdmin' => (bool) $request->user('store')?->hasRole(Role::SUPER_ADMIN, 'store'),
                'isRaizerAdmin' => (bool) $request->user('store')?->raizer_admin,
                'settings' => $this->storeMenuSettings(),
                'counts' => $this->storeCounts($request),
                'notifications' => $this->storeNotifications($request),
            ],
        ];
    }

    /** @return array<string, int> */
    private function storeCounts(Request $request): array
    {
        $user = $request->user('store');

        return [
            'sellers' => $user?->can('view_all_seller') ? Shop::query()->where('verification_status', 0)->whereNotNull('verification_info')->count() : 0,
            'tickets' => $user?->can('view_all_support_tickets') ? DB::table('tickets')->where('viewed', 0)->count() : 0,
            'conversations' => $user?->can('view_all_product_conversations') ? Conversation::query()->where('receiver_id', $user->id)->where('receiver_viewed', 0)->count() : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function storeNotifications(Request $request): array
    {
        $user = $request->user('store');
        if (! $user instanceof StoreUser || ! in_array($user->user_type, ['admin', 'staff', 'accountant'], true)) {
            return ['unread_count' => 0, 'items' => []];
        }

        return ['unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()->select(['id', 'type', 'data', 'created_at'])->orderByDesc('created_at')->orderBy('id')->limit(20)->get()->map(function ($notification) use ($request): array {
                $item = (new AdminNotificationResource($notification))->toArray($request);

                return ['id' => $item['id'], 'kind' => $item['kind'], 'order_code' => $item['order_code'],
                    'status' => $item['status'], 'user_name' => $item['customer_name'], 'message' => $item['message'], 'created_at' => $item['created_at']];
            })->all()];
    }

    /**
     * Each of STORE_MENU_SETTINGS as on or off, read in one query: get_setting()
     * caches nothing (its TTL is 0) and loads the whole table per call. Like
     * get_setting(), a key held in several rows (per language) takes the
     * first: ordered newest first, pluck() leaves the oldest row's value.
     *
     * @return array<string, bool>
     */
    private function storeMenuSettings(): array
    {
        $values = BusinessSetting::query()
            ->whereIn('type', self::STORE_MENU_SETTINGS)
            ->orderByDesc('id')
            ->pluck('value', 'type');

        return collect(self::STORE_MENU_SETTINGS)
            ->mapWithKeys(fn (string $key) => [$key => $values->get($key) == 1])
            ->all();
    }
}

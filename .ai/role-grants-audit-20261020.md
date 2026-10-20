# Store back-office permissions and role grants audit (2026-10-20)

Read-only audit of the store back office's spatie permissions and role grants
after the Blade to Inertia migration (guide §§1–39). Nothing in application
code, migrations, seeders or any database was changed. Deliverables:

- this report;
- [role-grants-audit-20261020.sql](role-grants-audit-20261020.sql): read-only
  `SELECT` statements for the owner to run on production;
- [role-grants-audit-20261020.json](role-grants-audit-20261020.json): the
  machine-readable inventory (permission name → definitions, enforcement sites),
  for a later session to diff.

Audited tree: `6ec65e405f1270255b8f01e5235b7238f1838aee` (`origin/develop`,
PR #450 merge). Shipped in PR #TBD.

## 0. Summary

| Headline | Count |
| --- | --- |
| Permission names the store back office **enforces** (route middleware, controller `HasMiddleware`, inline checks; every Vue gate name is also enforced in the backend) | **181** |
| Permission names the repository **defines** (migrations; seeders define none) | **38** |
| **Enforced but never defined** anywhere in the repository | **144** |
| Defined but never enforced (dead) | 1 (`delete_truck`) |
| `/temp` routes / permission-gated routes / `RaizerSuperAdmin` routes | 1,046 / 432 / 4 |
| `/temp/admin` routes with no permission and no `RaizerSuperAdmin` | 9 |
| Routes reachable by a non-Super Admin **only** through one of the 35 brand-new names | 94 |

All 144 enforced-but-undefined names were already referenced by the legacy
store's own code when it was mounted (`c3f6422e`), so they are presumably rows
of the legacy 213-permission vocabulary; whether production holds each of them
is unknown here and is query Q2 of the SQL file. The 35 names the repository
added (§B) appear nowhere in the legacy code, and no migration grants them, so
until the owner grants them only a Super Admin passes their gates.

**Top risks, in priority order** (details in §§E and G):

1. **Brand-new gates with no grants.** 94 routes need one of 35 permissions that
   only this repository's migrations create, none granted to any role: logistics
   (trips, trucks, drivers, trip orders, checkpoints, map picker), representative
   packages/payments/visits, the media library and uploads, order entry, order
   tracking/notes/price edits, the offers catalogue, and customer editing
   (`edit_customer`, 20 routes). On deploy, every staff member who is not a
   Super Admin loses them. Uploads matter beyond their own screen: product,
   blog and website forms upload through `upload_files`/`view_uploads`.
2. **The post-login landing page now needs `admin_dashboard`.** Staff and admins
   are redirected to `shop.admin.dashboard` after sign-in
   (`app/Http/Controllers/Auth/LoginController.php:216-217`), which §33 gated
   (`app/Http/Controllers/Store/Admin/DashboardController.php:28`). A staff
   member without it gets a 403 straight after logging in.
3. **144 enforced names exist only in production data, if at all.** A name
   missing from `wms_permissions` makes every route gated only by it
   unreachable for everyone but a Super Admin, and the role form cannot offer
   it. Run Q2 before go-live.
4. **`products.fetch` is a write with no backend permission**
   (`routes/shop-admin.php:142`). It queues the `import:all-products-v2`
   catalogue import (`app/Console/Commands/CheckForCommands.php:72`); only the
   Vue button is gated (`add_new_product`,
   `resources/js/pages/Store/Admin/Product/Products/Index.vue:234`). Any admin,
   staff or accountant account can trigger it.
5. **Order handling split into new names.** Order detail and status writes moved
   from the any-of `view_*_orders` list gate to `view_order_details`,
   `update_order_delivery_status`, `update_order_payment_status` and the brand-new
   `update_order_tracking`, `update_order_notes`, `edit_order_price`
   (`app/Http/Controllers/Store/Admin/OrderController.php:36-43`). Order staff
   holding only a list permission lose order details and every status write.
6. **Role and staff editors can escalate.** `edit_staff_role` can tick any store
   permission onto any non-Super Admin role, including the editor's own
   (`app/Http/Controllers/Store/Admin/RoleController.php:135`); `edit_staff`
   can change the editor's own role (`StaffController::update` blocks only
   Super Admin rows, `app/Http/Controllers/Store/Admin/StaffController.php:107`;
   only `destroy` refuses one's own account, `:135`). These two permissions are
   equivalent to holding every permission except Super Admin.
7. **Customer editing moved from `view_all_customers` to `edit_customer`.**
   Holders of `view_all_customers` (who could edit customers at
   `8e3f82b6`) lose create/edit/update/password/notes/verify, representative
   creation and address management
   (`app/Http/Controllers/Store/Admin/CustomerController.php:48`,
   `app/Http/Controllers/Store/Admin/AddressController.php:26`).
8. **Replies split from reads.** Ticket replies now need `reply_to_support_tickets`
   and product-query replies `reply_to_product_queries`
   (`app/Http/Controllers/Store/Admin/SupportTicketController.php:27`,
   `app/Http/Controllers/Store/Admin/ProductQueryController.php:28`); holders of
   only the view permissions lose replying.
9. **Money and impersonation gates have existing holders only if production
   granted them.** `pay_to_seller`, `seller_payment_history`,
   `approve_representative_payment`, `login_as_seller`, `login_as_customer`,
   `ban_*`, `delete_*`: grants are owner decisions (§F); none should follow
   automatically from "the role used to run that screen".
10. **Writes that legacy left open are now gated by their page's permission**
    (about 120 routes, mostly store/update/status/bulk writes, from the hardening
    before the migration). Staff
    who relied on posting a write without the page's permission lose it; that is
    the intended fix, but it is an access change to confirm per role (Q6).

**What this audit could not verify: production data.** There is no production
database here. Which permission rows exist, which roles exist, who holds what,
whether legacy rows were moved to guard `store` with `model_type`
`App\Models\StoreUser`, and how many staff each change affects are all open,
and are the questions the SQL file answers. Every LOSES/GAINS verdict below is
reasoning from code under the stated assumption, not a statement about
production.

## 1. Method

All inventories were gathered by scripts (kept in the session scratchpad, not
committed; their outputs are the JSON file):

- **Effective route gates:** `php artisan route:list --json` (which resolves
  controller `HasMiddleware` per method) at HEAD and, in throwaway worktrees
  sharing this checkout's `vendor/`, at three baselines:

  | Baseline | Commit | Meaning |
  | --- | --- | --- |
  | Legacy mount | `c3f6422e` (2026-09-30) | the legacy store as first mounted under `/temp`: the gates production roles were configured against |
  | Pre-reconciliation | `8e3f82b6` (2026-10-07) | after the legacy hardening (writes share their page's permission; offer deals, notifications and logistics gated), before the Blade→Inertia reconciliation |
  | Pre-§11 | `018dd180` (2026-10-12) | PR #377 merge; every §§11–39 module was still Blade |

- **Source sites:** each gated route was traced to the `new Middleware(...)` line
  of its controller (matching `only`/`except`) or its route-file line; inline
  checks (`->can()`, `Gate::*`, `hasPermissionTo`) were scanned in `app/` and
  `routes/` (policy abilities excluded); Blade `@can`/`@canany`/`->can()` in
  `resources/views`; Vue `can('…')`, `permission:` fields and permission maps in
  `resources/js` (tests and generated Wayfinder files excluded).
- **Definitions:** a regex pass over `database/migrations` and
  `database/seeders`, cross-checked by `php artisan migrate` plus
  `StoreRoleSeeder`/`StoreUserSeeder` on a scratch SQLite file: 38 store-guard
  rows, roles `Super Admin` and `Clerk`, **0** role–permission grants, one
  pivot `App\Models\StoreUser` → Super Admin.
- **Menu and page cross-check:** every `storeAdminMenu.ts` entry was resolved
  through its Wayfinder import to the route it opens and compared with that
  route's gate; every store Vue page's `can()` names were compared with the
  gates of the actions it imports.

Semantics confirmed in installed source (spatie/laravel-permission 8.3.0):
`permission:a|b` is any-of (`vendor/spatie/laravel-permission/src/Middleware/PermissionMiddleware.php`,
`canAny`); two `permission:` middleware on one route are both required
(`test.smtp`); spatie's own `Gate::before` returns `null` for a missing or
unheld permission (`checkPermissionTo` catches `PermissionDoesNotExist`), so the
app's `Gate::before` (`app/Providers/AuthServiceProvider.php:26-27`) passes a
Super Admin through every permission gate, **including one whose row does not
exist**. It does not pass `RaizerSuperAdmin`, which is plain middleware
(`app/Http/Middleware/RaizerSuperAdmin.php:27`: `user_type = 'admin'` and
`raizer_admin`). `config/permission.php:20,31` register spatie's base models;
`StoreUser::$guard_name = 'store'` (`app/Models/StoreUser.php:70`) pins lookups
to the store guard.

## A. Permissions the store back office enforces

Guard: `store` for every name (no `permission:` middleware sits on a non-`/temp`
route). 181 names; 432 gated routes. No Blade view in `resources/views` checks a
permission any more (the seven retained templates, §5.1 of the guide, have no
`@can`). Every name checked in Vue is also enforced by a backend route.

**Special cases and switches:**

- **Super Admin.** `Gate::before` grants every ability to
  `hasRole('Super Admin', 'store')` (`app/Providers/AuthServiceProvider.php:26-27`).
  The Inertia shell shares the same decision as `store.isSuperAdmin`
  (`app/Http/Middleware/HandleInertiaRequests.php:138`), and
  `storeAccess().can()` returns true for it
  (`resources/js/lib/storeAccess.ts:21-23`). Record invariants (Super Admin
  rows, one's own account) are controller `abort_if`s
  (`app/Http/Controllers/Store/Admin/RoleController.php:111,126,148`, `app/Http/Controllers/Store/Admin/StaffController.php:97,107,135`), and the
  staff role picker excludes Super Admin
  (`app/Http/Requests/Concerns/ValidatesStoreStaff.php:31`).
- **`isRaizerAdmin`.** The prop is `(bool) raizer_admin`
  (`app/Http/Middleware/HandleInertiaRequests.php:139`); the menu hides
  `raizerAdmin` entries and lets the vendor account pass every `setting` gate
  (`resources/js/lib/storeAdminMenu.ts:911-918`). The backend's
  `RaizerSuperAdmin` middleware also requires `user_type = 'admin'`
  (`app/Http/Middleware/RaizerSuperAdmin.php:27`). Its 4 routes:
  `activation.index` and `business_settings.update.activation`
  (`app/Http/Controllers/Store/Admin/SettingsController.php:117`, together with
  `features_activation`), `business_settings.seller_commission.activation`
  (`routes/shop-admin.php:349`, with `features_activation` from
  `app/Http/Controllers/BusinessSettingsController.php:24`) and `cache.clear`
  (`routes/shop-admin.php:685`).
- **`IsAdmin`** (`app/Http/Middleware/IsAdmin.php:22`) admits unbanned
  `admin`, `staff` and `accountant` accounts to `/temp/admin`; everything else
  gets 404 before any permission check.
- **`STORE_MENU_SETTINGS`** (`app/Http/Middleware/HandleInertiaRequests.php:25`)
  are business settings that switch sidebar sections on; they hide links only and
  gate no route. Sections switched by a setting: Products (in-house, seller,
  digital), Product reviews, Sales (seller and pickup-point orders), classified
  products/packages, Sellers (whole group; payouts, payout requests, commission,
  verification form), order configuration, Reports (each report), Blog, Marketing
  (flash deals, offer deals, popups, newsletters, subscribers, coupons), Support
  (conversations, product queries), Website setup (each page), Setup (languages,
  currency, VAT, pickup points, SMTP, social logins, Facebook, shipping) and Staff.
  `StoreInertiaSharedPropsTest` keeps the two lists in sync.
- **Inline backend checks** (beside route middleware): `delete_customer`
  (`app/Http/Controllers/Store/Admin/CustomerController.php:75`, bulk delete of
  representatives' customers), `assign_representative_package` (`:103`, the
  package list on the customer form), `edit_trip`
  (`app/Http/Controllers/Store/Admin/TripMapController.php:126`, map writes with
  a `trip_id`), `smtp_settings` (`app/Http/Controllers/Store/Admin/DashboardController.php:61`, the SMTP alert),
  `seller_payment_history` (`app/Http/Resources/Store/SellerResource.php:36`),
  the sidebar badge counts `view_all_seller`, `view_all_support_tickets`,
  `view_all_product_conversations`
  (`app/Http/Middleware/HandleInertiaRequests.php:153-155`), and `view_uploads`
  in six upload-reference validators and previews
  (`app/Http/Controllers/Concerns/ProvidesStoreUploadPreviews.php:19`,
  `app/Http/Requests/Concerns/ValidatesStoreUploadReference.php:93`,
  `app/Http/Requests/Concerns/ValidatesStoreTruck.php:36`,
  `app/Http/Requests/Concerns/ValidatesWebsiteSettings.php:19,39`,
  `app/Http/Controllers/Store/Admin/TruckController.php:131`).
- **Dynamic check:** `OrderController::all_orders` picks its permission per
  route name (`app/Http/Controllers/Store/Admin/OrderController.php:50-54`) on top
  of the any-of route gate (`:36`): `inhouse_orders.index` needs
  `view_inhouse_orders`, `seller_orders.index` `view_seller_orders`,
  `pick_up_point.index` `view_pickup_point_orders`, `all_orders.index`
  `view_all_orders`.
- **Settings page map:** `resources/js/pages/Store/Admin/Settings/Index.vue:48-59`
  maps each settings section to its permission for the save button
  (mirroring `app/Http/Controllers/Store/Admin/SettingsController.php:92-101` and its middleware `:107-118`).
- **Policies** (`app/Policies/UploadPolicy.php` etc.) check user type and
  ownership, never a permission name; `Gate::before` still passes a Super Admin
  through them.
- **Menu:** 92 sidebar links, **0** whose gate disagrees with the route it opens.

Per-permission inventory (backend sites are the `new Middleware(...)` or
route-file line; the full per-route list, with methods and URIs, is in the JSON):

| Permission | Backend sites (file:line) | Routes gated | Other backend checks | Vue (file:lines) | Menu | Defined in repo |
| --- | --- | --- | --- | --- | --- | --- |
| `add_blog` | `app/Http/Controllers/Store/Admin/BlogController.php:31` | 2: `blog.create`, `blog.store` | – | `resources/js/pages/Store/Admin/Blog/Posts/Index.vue:46` | – | no |
| `add_blog_category` | `app/Http/Controllers/Store/Admin/BlogCategoryController.php:25` | 2: `blog-category.create`, `blog-category.store` | – | `resources/js/pages/Store/Admin/Blog/Categories/Index.vue:49` | – | no |
| `add_brand` | `app/Http/Controllers/Store/Admin/BrandController.php:39` | 1: `brands.store` | – | `resources/js/pages/Store/Admin/Product/Brands/Index.vue:117` | – | no |
| `add_coupon` | `app/Http/Controllers/Store/Admin/CouponController.php:31,34` | 3: `coupon.create`, `coupon.product-options (any-of)`, `coupon.store` | – | `resources/js/pages/Store/Admin/Marketing/Coupons/Index.vue:39` | – | no |
| `add_digital_product` | `app/Http/Controllers/Store/Admin/DigitalProductController.php:57` | 2: `digitalproducts.create`, `digitalproducts.store` | – | `resources/js/pages/Store/Admin/Product/DigitalProducts/Index.vue:58` | – | no |
| `add_driver` | `app/Http/Controllers/Store/Admin/DriverController.php:28` | 2: `drivers.create`, `drivers.store` | – | `resources/js/pages/Store/Admin/Trips/Drivers/Index.vue:55` | `resources/js/lib/storeAdminMenu.ts:500` | yes |
| `add_dynamic_popups` | `app/Http/Controllers/Store/Admin/DynamicPopupController.php:35` | 2: `dynamic-popups.create`, `dynamic-popups.store` | – | `resources/js/pages/Store/Admin/Marketing/DynamicPopups/Index.vue:72` | – | no |
| `add_flash_deal` | `app/Http/Controllers/Store/Admin/CampaignProductController.php:19`; `app/Http/Controllers/Store/Admin/FlashDealController.php:31` | 4: `admin.campaign-products.brands (any-of)`, `admin.campaign-products.index (any-of)`, `flash_deals.create`, `flash_deals.store` | – | `resources/js/pages/Store/Admin/Marketing/FlashDeals/Index.vue:43` | – | no |
| `add_new_product` | `app/Http/Controllers/Store/Admin/ProductController.php:69`; `app/Http/Controllers/Store/Admin/ProductSearchController.php:22` | 4: `products.add-more-choice-option (any-of)`, `products.create`, `products.store`, `products.variant_combinations` | – | `resources/js/pages/Store/Admin/Product/Products/Index.vue:234` | `resources/js/lib/storeAdminMenu.ts:193` | no |
| `add_notification` | `app/Http/Controllers/Store/Admin/CustomerNotificationController.php:27` | 3: `admin.notifications.all`, `admin.notifications.create`, `admin.notifications.send` | – | `resources/js/pages/Store/Admin/Notifications/Customers/Index.vue:40` | `resources/js/lib/storeAdminMenu.ts:331` | no |
| `add_offer_deal` | `app/Http/Controllers/Store/Admin/CampaignProductController.php:19`; `app/Http/Controllers/Store/Admin/OfferController.php:28` | 5: `admin.campaign-products.brands (any-of)`, `admin.campaign-products.index (any-of)`, `offer_deals.brands.create`, `offer_deals.create`, `offer_deals.store` | – | `resources/js/pages/Store/Admin/Marketing/Offers/Index.vue:44,47` | – | no |
| `add_offers_catalog` | `app/Http/Controllers/Store/Admin/OffersCatalogController.php:29` | 1: `offers.store` | – | `resources/js/pages/Store/Admin/Catalogue/Offers/Index.vue:51` | – | yes |
| `add_order` | `app/Http/Controllers/Store/Admin/OrderEntryController.php:32,34` | 5: `feat-order.create`, `feat-order.store`, `new.feat-order.trip.create`, `new.feat-order.trip.store`, `orders.search-options (any-of)` | – | `resources/js/pages/Store/Admin/Sales/Orders/Index.vue:68`; `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:123`; `resources/js/pages/Store/Admin/Trips/Trips/Index.vue:136` | `resources/js/lib/storeAdminMenu.ts:287` | yes |
| `add_product_category` | `app/Http/Controllers/Store/Admin/CategoryController.php:44` | 2: `categories.create`, `categories.store` | – | `resources/js/pages/Store/Admin/Product/Categories/Index.vue:55` | – | no |
| `add_representative_package` | `app/Http/Controllers/Store/Admin/RepresentativePackageController.php:31` | 2: `rep.packages.create`, `rep.packages.store` | – | `resources/js/pages/Store/Admin/Representatives/Packages/Index.vue:41` | – | yes |
| `add_staff` | `app/Http/Controllers/Store/Admin/StaffController.php:39` | 2: `staffs.create`, `staffs.store` | – | `resources/js/pages/Store/Admin/Staff/Staffs/Index.vue:61` | – | no |
| `add_staff_role` | `app/Http/Controllers/Store/Admin/RoleController.php:63` | 2: `roles.create`, `roles.store` | – | `resources/js/pages/Store/Admin/Staff/Roles/Index.vue:47` | – | no |
| `add_trip` | `app/Http/Controllers/Store/Admin/TripController.php:57`; `app/Http/Controllers/Store/Admin/TripMapController.php:51`; `routes/shop-admin.php:305,306` | 6: `trips.available-vehicles (any-of)`, `trips.create`, `trips.create-from-map`, `trips.store`, `trips.store-from-map`, `trips.user-coordinates (any-of)` | – | `resources/js/pages/Store/Admin/Trips/Trips/Index.vue:141` | `resources/js/lib/storeAdminMenu.ts:470,476` | yes |
| `add_truck` | `app/Http/Controllers/Store/Admin/TruckController.php:37,39` | 3: `trucks.create`, `trucks.photos (any-of)`, `trucks.store` | – | `resources/js/pages/Store/Admin/Trips/Trucks/Index.vue:52` | `resources/js/lib/storeAdminMenu.ts:447` | yes |
| `add_website_page` | `app/Http/Controllers/Store/Admin/WebsitePageController.php:42` | 2: `custom-pages.create`, `custom-pages.store` | – | `resources/js/pages/Store/Admin/Website/Pages/Index.vue:44` | – | no |
| `admin_dashboard` | `app/Http/Controllers/Store/Admin/DashboardController.php:28` | 1: `shop.admin.dashboard` | – | `resources/js/components/StoreDashboardChart.vue:46`; `resources/js/pages/Store/Admin/Dashboard/Index.vue:61` | `resources/js/lib/storeAdminMenu.ts:184` | no |
| `analytics_tools_configuration` | `app/Http/Controllers/Store/Admin/SettingsController.php:112` | 3: `facebook_pixel.update`, `google_analytics.index`, `google_analytics.update` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:54` | `resources/js/lib/storeAdminMenu.ts:823` | no |
| `approve_representative_payment` | `app/Http/Controllers/Store/Admin/RepresentativePaymentController.php:29` | 2: `admin.rep.payments.accept`, `admin.rep.payments.reject` | – | `resources/js/pages/Store/Admin/Representatives/Payments/Index.vue:147` | – | yes |
| `assign_representative_package` | `app/Http/Controllers/Store/Admin/RepresentativePackageController.php:34` | 1: `rep.set-package` | `app/Http/Controllers/Store/Admin/CustomerController.php:103` | `resources/js/pages/Store/Admin/Customers/Customers/Index.vue:238` | – | yes |
| `ban_customer` | `app/Http/Controllers/Store/Admin/CustomerController.php:50` | 1: `customers.ban` | – | `resources/js/pages/Store/Admin/Customers/Customers/Index.vue:295` | – | no |
| `ban_seller` | `app/Http/Controllers/Store/Admin/SellerController.php:48` | 1: `sellers.ban` | – | `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:294` | – | no |
| `commission_history_report` | `app/Http/Controllers/Store/Admin/ReportController.php:39` | 1: `commission-log.index` | – | – | `resources/js/lib/storeAdminMenu.ts:579` | no |
| `currency_setup` | `app/Http/Controllers/Store/Admin/CurrencyController.php:28` | 7: `currency.create`, `currency.edit`, `currency.index`, `currency.settings.update`, `currency.store`, `currency.update_status`, `your_currency.update` | – | `resources/js/pages/Store/Admin/Setup/Currencies/Index.vue:88,129,141,158,163` | `resources/js/lib/storeAdminMenu.ts:752` | no |
| `delete_all_uploads` | `app/Http/Controllers/Store/Admin/UploadController.php:33` | 1: `all-file` | – | – | – | yes |
| `delete_blog` | `app/Http/Controllers/Store/Admin/BlogController.php:33` | 1: `blog.destroy` | – | `resources/js/pages/Store/Admin/Blog/Posts/Index.vue:147` | – | no |
| `delete_blog_category` | `app/Http/Controllers/Store/Admin/BlogCategoryController.php:27` | 1: `blog-category.destroy` | – | `resources/js/pages/Store/Admin/Blog/Categories/Index.vue:99` | – | no |
| `delete_brand` | `app/Http/Controllers/Store/Admin/BrandController.php:41` | 1: `brands.destroy` | – | `resources/js/pages/Store/Admin/Product/Brands/Index.vue:98` | – | no |
| `delete_classified_package` | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:37` | 1: `customer_packages.destroy` | – | `resources/js/pages/Store/Admin/Customers/Packages/Index.vue:81` | – | no |
| `delete_classified_product` | `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:26` | 1: `classified_products.destroy` | – | `resources/js/pages/Store/Admin/Customers/ClassifiedProducts/Index.vue:170` | – | no |
| `delete_color` | `app/Http/Controllers/Store/Admin/ColorController.php:27` | 1: `colors.destroy` | – | `resources/js/pages/Store/Admin/Product/Colors/Index.vue:96` | – | no |
| `delete_coupon` | `app/Http/Controllers/Store/Admin/CouponController.php:33` | 1: `coupon.destroy` | – | `resources/js/pages/Store/Admin/Marketing/Coupons/Index.vue:87` | – | no |
| `delete_customer` | `app/Http/Controllers/Store/Admin/CustomerController.php:49` | 4: `bulk-customer-delete`, `customers.archive`, `customers.destroy`, `customers.restore` | `app/Http/Controllers/Store/Admin/CustomerController.php:75` | `resources/js/pages/Store/Admin/Customers/Customers/Index.vue:144,179,218,314,324` | `resources/js/lib/storeAdminMenu.ts:325` | no |
| `delete_digital_product` | `app/Http/Controllers/Store/Admin/DigitalProductController.php:59` | 1: `digitalproducts.destroy` | – | `resources/js/pages/Store/Admin/Product/DigitalProducts/Index.vue:167` | – | no |
| `delete_driver` | `app/Http/Controllers/Store/Admin/DriverController.php:30` | 1: `drivers.destroy` | – | `resources/js/pages/Store/Admin/Trips/Drivers/Index.vue:97` | – | yes |
| `delete_dynamic_popups` | `app/Http/Controllers/Store/Admin/DynamicPopupController.php:37` | 2: `bulk-dynamic-popup-delete`, `dynamic-popups.destroy` | – | `resources/js/pages/Store/Admin/Marketing/DynamicPopups/Index.vue:97,129,143,213,228` | – | no |
| `delete_flash_deal` | `app/Http/Controllers/Store/Admin/FlashDealController.php:31` | 1: `flash_deals.destroy` | – | `resources/js/pages/Store/Admin/Marketing/FlashDeals/Index.vue:147` | – | no |
| `delete_notification` | `app/Http/Controllers/Store/Admin/CustomerNotificationController.php:28` | 1: `admin.notifications.delete` | – | `resources/js/pages/Store/Admin/Notifications/Customers/Index.vue:67` | – | no |
| `delete_offer_deal` | `app/Http/Controllers/Store/Admin/OfferController.php:28` | 1: `offer_deals.destroy` | – | `resources/js/pages/Store/Admin/Marketing/Offers/Index.vue:188` | – | no |
| `delete_offers_catalog` | `app/Http/Controllers/Store/Admin/OffersCatalogController.php:30` | 1: `offers.destroy` | – | `resources/js/pages/Store/Admin/Catalogue/Offers/Index.vue:112` | – | yes |
| `delete_order` | `app/Http/Controllers/Store/Admin/OrderController.php:38` | 2: `bulk-order-delete`, `orders.destroy` | – | `resources/js/pages/Store/Admin/Sales/Orders/Index.vue:181,210,221`; `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:51,244` | – | no |
| `delete_product_attribute` | `app/Http/Controllers/Store/Admin/AttributeController.php:30` | 1: `attributes.destroy` | – | `resources/js/pages/Store/Admin/Product/Attributes/Index.vue:89` | – | no |
| `delete_product_attribute_value` | `app/Http/Controllers/Store/Admin/AttributeValueController.php:28` | 1: `destroy-attribute-value` | – | `resources/js/pages/Store/Admin/Product/AttributeValues/Index.vue:87` | – | no |
| `delete_product_category` | `app/Http/Controllers/Store/Admin/CategoryController.php:46` | 1: `categories.destroy` | – | `resources/js/pages/Store/Admin/Product/Categories/Index.vue:149` | – | no |
| `delete_product_conversations` | `app/Http/Controllers/ConversationController.php:30`; `app/Http/Controllers/Store/Admin/ConversationController.php:28` | 3: `(unnamed) DELETE temp/conversations/{conversation}`, `conversations.admin_destroy`, `conversations.destroy` | – | `resources/js/pages/Store/Admin/Support/Conversations/Index.vue:79` | – | no |
| `delete_representative_package` | `app/Http/Controllers/Store/Admin/RepresentativePackageController.php:33` | 1: `rep.packages.destroy` | – | `resources/js/pages/Store/Admin/Representatives/Packages/Index.vue:68` | – | yes |
| `delete_representative_visit` | `app/Http/Controllers/Store/Admin/VisitController.php:31` | 2: `admin.rep.visits.destroy`, `bulk-visit-delete` | – | `resources/js/pages/Store/Admin/Representatives/Visits/Index.vue:102,127,182` | – | yes |
| `delete_seller` | `app/Http/Controllers/Store/Admin/SellerController.php:47` | 2: `bulk-seller-delete`, `sellers.destroy` | – | `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:128,146,173,308` | – | no |
| `delete_staff` | `app/Http/Controllers/Store/Admin/StaffController.php:41` | 1: `staffs.destroy` | – | `resources/js/pages/Store/Admin/Staff/Staffs/Index.vue:40` | – | no |
| `delete_staff_role` | `app/Http/Controllers/Store/Admin/RoleController.php:65` | 1: `roles.destroy` | – | `resources/js/pages/Store/Admin/Staff/Roles/Index.vue:77` | – | no |
| `delete_subscriber` | `app/Http/Controllers/Store/Admin/SubscriberController.php:23`; `app/Http/Controllers/SubscriberController.php:25` | 2: `subscriber.destroy`, `subscribers.destroy` | – | `resources/js/pages/Store/Admin/Marketing/Subscribers/Index.vue:70` | – | no |
| `delete_trip` | `app/Http/Controllers/Store/Admin/TripController.php:59` | 2: `trips.bulk-trip-delete`, `trips.destroy` | – | `resources/js/pages/Store/Admin/Trips/Trips/CreateFromMap.vue:465`; `resources/js/pages/Store/Admin/Trips/Trips/Index.vue:190,222,241,375,397` | – | yes |
| `delete_uploads` | `app/Http/Controllers/Store/Admin/UploadController.php:32` | 2: `bulk-uploaded-files-delete`, `uploaded-files.destroy` | – | `resources/js/components/StoreUploadManager.vue:334,368,411` | – | yes |
| `delete_website_page` | `app/Http/Controllers/Store/Admin/WebsitePageController.php:44` | 1: `custom-pages.destroy` | – | `resources/js/pages/Store/Admin/Website/Pages/Index.vue:98` | – | no |
| `download_digital_product` | `app/Http/Controllers/Store/Admin/DigitalProductController.php:60` | 1: `digitalproducts.download` | – | `resources/js/pages/Store/Admin/Product/DigitalProducts/Index.vue:159` | – | no |
| `edit_blog` | `app/Http/Controllers/Store/Admin/BlogController.php:32` | 2: `blog.edit`, `blog.update` | – | `resources/js/pages/Store/Admin/Blog/Posts/Index.vue:140` | – | no |
| `edit_blog_category` | `app/Http/Controllers/Store/Admin/BlogCategoryController.php:26` | 2: `blog-category.edit`, `blog-category.update` | – | `resources/js/pages/Store/Admin/Blog/Categories/Index.vue:92` | – | no |
| `edit_brand` | `app/Http/Controllers/Store/Admin/BrandController.php:40` | 2: `brands.edit`, `brands.update` | – | `resources/js/pages/Store/Admin/Product/Brands/Index.vue:89` | – | no |
| `edit_classified_package` | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` | 4: `customer_packages.create`, `customer_packages.edit`, `customer_packages.store`, `customer_packages.update` | – | `resources/js/pages/Store/Admin/Customers/Packages/Index.vue:42,72` | – | no |
| `edit_color` | `app/Http/Controllers/Store/Admin/ColorController.php:26` | 3: `colors.edit`, `colors.store`, `colors.update` | – | `resources/js/pages/Store/Admin/Product/Colors/Index.vue:89,116` | – | no |
| `edit_coupon` | `app/Http/Controllers/Store/Admin/CouponController.php:32,34` | 3: `coupon.edit`, `coupon.product-options (any-of)`, `coupon.update` | – | `resources/js/pages/Store/Admin/Marketing/Coupons/Index.vue:80` | – | no |
| `edit_customer` | `app/Http/Controllers/Store/Admin/AddressController.php:26`; `app/Http/Controllers/Store/Admin/CustomerController.php:48` | 20: `admin.addresses.cities`, `admin.addresses.states`, `admin.customers.addresses.default`, `admin.customers.addresses.destroy`, `admin.customers.addresses.store`, `admin.customers.addresses.update`, `change_password`, `customers.change_activity_status`, `customers.create`, `customers.edit`, `customers.important`, `customers.not_important`, `customers.sendAgreement`, `customers.store`, `customers.update`, `customers.update_customer_notes`, `customers.verify`, `representatives.create`, `representatives.store`, `update_password` | – | `resources/js/pages/Store/Admin/Customers/Customers/Index.vue:131,263,273,282`; `resources/js/pages/Store/Admin/Representatives/Visits/Show.vue:70`; `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:337`; `resources/js/pages/Store/Admin/Trips/Trips/CreateFromMap.vue:411`; `resources/js/pages/Store/Admin/Trips/Trips/Show.vue:312` | `resources/js/lib/storeAdminMenu.ts:319` | yes |
| `edit_digital_product` | `app/Http/Controllers/Store/Admin/DigitalProductController.php:58` | 2: `digitalproducts.edit`, `digitalproducts.update` | – | `resources/js/pages/Store/Admin/Product/DigitalProducts/Index.vue:148` | – | no |
| `edit_driver` | `app/Http/Controllers/Store/Admin/DriverController.php:29` | 2: `drivers.edit`, `drivers.update` | – | `resources/js/pages/Store/Admin/Trips/Drivers/Index.vue:90`; `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:359`; `resources/js/pages/Store/Admin/Trips/Trips/Index.vue:263`; `resources/js/pages/Store/Admin/Trips/Trips/Show.vue:177` | – | yes |
| `edit_dynamic_popups` | `app/Http/Controllers/Store/Admin/DynamicPopupController.php:36` | 2: `dynamic-popups.edit`, `dynamic-popups.update` | – | `resources/js/pages/Store/Admin/Marketing/DynamicPopups/Index.vue:206` | – | no |
| `edit_flash_deal` | `app/Http/Controllers/Store/Admin/CampaignProductController.php:19`; `app/Http/Controllers/Store/Admin/FlashDealController.php:31` | 5: `admin.campaign-products.brands (any-of)`, `admin.campaign-products.index (any-of)`, `flash_deals.edit`, `flash_deals.update`, `flash_deals.update_status` | – | `resources/js/pages/Store/Admin/Marketing/FlashDeals/Index.vue:84,141` | – | no |
| `edit_offer_deal` | `app/Http/Controllers/Store/Admin/CampaignProductController.php:19`; `app/Http/Controllers/Store/Admin/OfferController.php:28` | 7: `admin.campaign-products.brands (any-of)`, `admin.campaign-products.index (any-of)`, `offer_deals.edit`, `offer_deals.update`, `offer_deals.update_multiple_usage`, `offer_deals.update_repeatable`, `offer_deals.update_status` | – | `resources/js/pages/Store/Admin/Marketing/Offers/Index.vue:89,117,149,182` | – | no |
| `edit_order` | `app/Http/Controllers/Store/Admin/OrderEntryController.php:33,34` | 3: `new.feat-order.trip.edit`, `new.feat-order.trip.update`, `orders.search-options (any-of)` | – | `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:76`; `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:499` | – | yes |
| `edit_order_price` | `app/Http/Controllers/Store/Admin/OrderController.php:43` | 1: `order.item.edit_price` | – | `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:219` | – | yes |
| `edit_product_attribute` | `app/Http/Controllers/Store/Admin/AttributeController.php:29` | 3: `attributes.edit`, `attributes.store`, `attributes.update` | – | `resources/js/pages/Store/Admin/Product/Attributes/Index.vue:82,109` | – | no |
| `edit_product_attribute_value` | `app/Http/Controllers/Store/Admin/AttributeValueController.php:27` | 3: `edit-attribute-value`, `store-attribute-value`, `update-attribute-value` | – | `resources/js/pages/Store/Admin/Product/AttributeValues/Index.vue:78,108` | – | no |
| `edit_product_category` | `app/Http/Controllers/Store/Admin/CategoryController.php:45` | 3: `categories.edit`, `categories.featured`, `categories.update` | – | `resources/js/pages/Store/Admin/Product/Categories/Index.vue:89,121,140` | – | no |
| `edit_representative_package` | `app/Http/Controllers/Store/Admin/RepresentativePackageController.php:32` | 2: `rep.packages.edit`, `rep.packages.update` | – | `resources/js/pages/Store/Admin/Representatives/Packages/Index.vue:61` | – | yes |
| `edit_seller` | `app/Http/Controllers/Store/Admin/SellerController.php:46` | 7: `sellers.approve`, `sellers.approved`, `sellers.create`, `sellers.edit`, `sellers.reject`, `sellers.store`, `sellers.update` | – | `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:97,204,289`; `resources/js/pages/Store/Admin/Sellers/Sellers/Verification.vue:72` | – | no |
| `edit_staff` | `app/Http/Controllers/Store/Admin/StaffController.php:40` | 2: `staffs.edit`, `staffs.update` | – | `resources/js/pages/Store/Admin/Staff/Staffs/Index.vue:35` | – | no |
| `edit_staff_role` | `app/Http/Controllers/Store/Admin/RoleController.php:64` | 2: `roles.edit`, `roles.update` | – | `resources/js/pages/Store/Admin/Staff/Roles/Index.vue:70` | – | no |
| `edit_trip` | `app/Http/Controllers/Store/Admin/TripCheckpointController.php:41`; `app/Http/Controllers/Store/Admin/TripController.php:58`; `app/Http/Controllers/Store/Admin/TripOrderController.php:49`; `routes/shop-admin.php:305,306` | 11: `checkpoints.update-notes`, `checkpoints.update-status`, `trips.available-vehicles (any-of)`, `trips.edit`, `trips.orders.bulk-change-delivery-status`, `trips.orders.update_invoice_number`, `trips.orders.update_order_status_to_delivered`, `trips.update`, `trips.update-notes`, `trips.update-status`, `trips.user-coordinates (any-of)` | `app/Http/Controllers/Store/Admin/TripMapController.php:126` | `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:51,129,202,403,478`; `resources/js/pages/Store/Admin/Trips/Trips/CreateFromMap.vue:96,249`; `resources/js/pages/Store/Admin/Trips/Trips/Index.vue:298,334,368,413`; `resources/js/pages/Store/Admin/Trips/Trips/Show.vue:133,331,376` | – | yes |
| `edit_truck` | `app/Http/Controllers/Store/Admin/TruckController.php:38,39` | 3: `trucks.edit`, `trucks.photos (any-of)`, `trucks.update` | – | `resources/js/pages/Store/Admin/Trips/Orders/Index.vue:370`; `resources/js/pages/Store/Admin/Trips/Trips/Index.vue:273`; `resources/js/pages/Store/Admin/Trips/Trips/Show.vue:192`; `resources/js/pages/Store/Admin/Trips/Trucks/Index.vue:105` | – | yes |
| `edit_website_page` | `app/Http/Controllers/Store/Admin/WebsiteHomeController.php:25`; `app/Http/Controllers/Store/Admin/WebsitePageController.php:43` | 4: `custom-pages.edit`, `custom-pages.update`, `website.home.edit`, `website.home.update` | – | `resources/js/pages/Store/Admin/Website/Pages/Index.vue:79,89` | `resources/js/lib/storeAdminMenu.ts:518` | no |
| `export_representative_payment` | `app/Http/Controllers/Store/Admin/RepresentativePaymentController.php:30` | 1: `admin.rep.payments.export` | – | `resources/js/pages/Store/Admin/Representatives/Payments/Index.vue:175` | – | yes |
| `facebook_chat` | `app/Http/Controllers/Store/Admin/SettingsController.php:110` | 2: `facebook_chat.index`, `facebook_chat.update` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:52` | `resources/js/lib/storeAdminMenu.ts:805` | no |
| `facebook_comment` | `app/Http/Controllers/Store/Admin/SettingsController.php:111` | 2: `facebook-comment`, `facebook-comment.update` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:53` | `resources/js/lib/storeAdminMenu.ts:811` | no |
| `features_activation` | `app/Http/Controllers/BusinessSettingsController.php:24`; `app/Http/Controllers/Store/Admin/SettingsController.php:107` | 3: `activation.index`, `business_settings.seller_commission.activation`, `business_settings.update.activation` | – | `resources/js/pages/Store/Admin/Sellers/Commission/Index.vue:26`; `resources/js/pages/Store/Admin/Settings/Index.vue:49` | `resources/js/lib/storeAdminMenu.ts:738` | no |
| `file_system_&_cache_configuration` | `app/Http/Controllers/Store/Admin/SettingsController.php:116` | 1: `file_system.index` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:58` | `resources/js/lib/storeAdminMenu.ts:787` | no |
| `footer_setup` | `app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29` | 2: `website.footer`, `website.footer.update` | – | – | `resources/js/lib/storeAdminMenu.ts:704` | no |
| `general_settings` | `app/Http/Controllers/Store/Admin/GeneralSettingsController.php:25` | 2: `general_setting.index`, `general_setting.update` | – | – | `resources/js/lib/storeAdminMenu.ts:732` | no |
| `google_firebase_setting` | `app/Http/Controllers/Store/Admin/SettingsController.php:114` | 2: `google-firebase.index`, `google-firebase.update` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:56` | `resources/js/lib/storeAdminMenu.ts:841` | no |
| `google_map_setting` | `app/Http/Controllers/Store/Admin/GoogleMapController.php:21` | 2: `google-map.index`, `google-map.update` | – | – | `resources/js/lib/storeAdminMenu.ts:835` | yes |
| `google_recaptcha_configuration` | `app/Http/Controllers/Store/Admin/SettingsController.php:113` | 2: `google_recaptcha.index`, `google_recaptcha.update` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:55` | `resources/js/lib/storeAdminMenu.ts:829` | no |
| `header_setup` | `app/Http/Controllers/Store/Admin/WebsiteHeaderController.php:23` | 2: `website.header`, `website.header.update` | – | – | `resources/js/lib/storeAdminMenu.ts:697` | no |
| `in_house_product_sale_report` | `app/Http/Controllers/Store/Admin/ReportController.php:34` | 1: `in_house_sale_report.index` | – | – | `resources/js/lib/storeAdminMenu.ts:544` | no |
| `language_setup` | `app/Http/Controllers/Store/Admin/LanguageController.php:29` | 14: `app-translations.export`, `app-translations.import`, `app-translations.show`, `app-translations.store`, `languages.create`, `languages.destroy`, `languages.edit`, `languages.index`, `languages.key_value_store`, `languages.show`, `languages.store`, `languages.update`, `languages.update-status`, `languages.update_rtl_status` | – | `resources/js/pages/Store/Admin/Languages/Index.vue:49,77,107,147,189`; `resources/js/pages/Store/Admin/Languages/Translations.vue:87,95,128` | `resources/js/lib/storeAdminMenu.ts:745` | no |
| `login_as_customer` | `app/Http/Controllers/Store/Admin/CustomerController.php:51` | 1: `customers.login` | – | `resources/js/pages/Store/Admin/Customers/Customers/Index.vue:305` | – | no |
| `login_as_seller` | `app/Http/Controllers/Store/Admin/SellerController.php:45` | 1: `sellers.login` | – | `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:260` | – | no |
| `manage_carriers` | `app/Http/Controllers/Store/Admin/CarrierController.php:35` | 7: `carriers.create`, `carriers.destroy`, `carriers.edit`, `carriers.index`, `carriers.store`, `carriers.update`, `carriers.update_status` | – | `resources/js/pages/Store/Admin/Setup/Carriers/Index.vue:56,99,114`; `resources/js/pages/Store/Admin/Setup/ShippingConfiguration/Index.vue:146` | `resources/js/lib/storeAdminMenu.ts:883` | no |
| `manage_shipping_cities` | `app/Http/Controllers/Store/Admin/CityController.php:32` | 6: `cities.destroy`, `cities.edit`, `cities.index`, `cities.status`, `cities.store`, `cities.update` | – | `resources/js/pages/Store/Admin/Setup/Cities/Index.vue:100,108,137`; `resources/js/pages/Store/Admin/Setup/ShippingConfiguration/Index.vue:140` | `resources/js/lib/storeAdminMenu.ts:871` | no |
| `manage_shipping_states` | `app/Http/Controllers/Store/Admin/StateController.php:27` | 6: `states.destroy`, `states.edit`, `states.index`, `states.status`, `states.store`, `states.update` | – | `resources/js/pages/Store/Admin/Setup/States/Index.vue:98,106,135` | `resources/js/lib/storeAdminMenu.ts:865` | no |
| `manage_zones` | `app/Http/Controllers/Store/Admin/ZoneController.php:26` | 6: `zones.create`, `zones.destroy`, `zones.edit`, `zones.index`, `zones.store`, `zones.update` | – | `resources/js/pages/Store/Admin/Setup/Zones/Index.vue:40,69` | `resources/js/lib/storeAdminMenu.ts:877` | no |
| `order_configuration` | `app/Http/Controllers/Store/Admin/OrderConfigurationController.php:24`; `app/Http/Controllers/Store/Admin/ProductSearchController.php:21` | 4: `order_configuration.index`, `order_configuration.product-search`, `order_configuration.representative.update`, `order_configuration.update` | – | `resources/js/pages/Store/Admin/Setup/OrderConfiguration/Index.vue:102` | `resources/js/lib/storeAdminMenu.ts:525` | no |
| `pay_to_seller` | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:30` | 2: `commissions.pay_to_seller`, `sellers.payment_modal` | – | `resources/js/components/StoreSellerPaymentDialog.vue:45,93`; `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:268`; `resources/js/pages/Store/Admin/Sellers/Withdrawals/Index.vue:80` | – | no |
| `payment_methods_configurations` | `app/Http/Controllers/Store/Admin/SettingsController.php:115` | 2: `payment_method.index`, `payment_method.update` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:57` | `resources/js/lib/storeAdminMenu.ts:780` | no |
| `pickup_point_setup` | `app/Http/Controllers/Store/Admin/PickupPointController.php:30` | 6: `pick_up_points.create`, `pick_up_points.destroy`, `pick_up_points.edit`, `pick_up_points.index`, `pick_up_points.store`, `pick_up_points.update` | – | `resources/js/pages/Store/Admin/Setup/PickupPoints/Index.vue:54,109` | `resources/js/lib/storeAdminMenu.ts:766` | no |
| `product_bulk_export` | `app/Http/Controllers/Store/Admin/ProductBulkUploadController.php:31` | 1: `product_bulk_export.index` | – | – | `resources/js/lib/storeAdminMenu.ts:232` | no |
| `product_bulk_import` | `app/Http/Controllers/Store/Admin/ProductBulkUploadController.php:30` | 5: `bulk_product_upload`, `pdf.download_brand`, `pdf.download_category`, `pdf.download_seller`, `product_bulk_upload.index` | – | – | `resources/js/lib/storeAdminMenu.ts:227` | no |
| `product_delete` | `app/Http/Controllers/Store/Admin/ProductController.php:78` | 4: `bulk-product-delete`, `products.destroy`, `products.list.bulk_destroy`, `products.list.destroy` | – | `resources/js/pages/Store/Admin/Product/Products/Index.vue:422,454,471,606,621` | – | no |
| `product_duplicate` | `app/Http/Controllers/Store/Admin/ProductController.php:77` | 2: `products.duplicate`, `products.list.duplicate` | – | `resources/js/pages/Store/Admin/Product/Products/Index.vue:597` | – | no |
| `product_edit` | `app/Http/Controllers/Store/Admin/BrandDiscountController.php:29`; `app/Http/Controllers/Store/Admin/DigitalProductController.php:63`; `app/Http/Controllers/Store/Admin/ProductController.php:71,76`; `app/Http/Controllers/Store/Admin/ProductSearchController.php:22` | 17: `digitalproducts.featured`, `digitalproducts.published`, `digitalproducts.todays_deal`, `products.add-more-choice-option (any-of)`, `products.admin.edit`, `products.approved`, `products.edit_variant_combinations`, `products.featured`, `products.list.featured`, `products.list.for_sale`, `products.list.published`, `products.published`, `products.seller.edit`, `products.todays_deal`, `products.update`, `products.update_sale`, `set_product_discount` | – | `resources/js/pages/Store/Admin/Product/BrandDiscounts/Index.vue:78`; `resources/js/pages/Store/Admin/Product/DigitalProducts/Index.vue:132`; `resources/js/pages/Store/Admin/Product/Products/Index.vue:552,586` | – | no |
| `product_wishlist_report` | `app/Http/Controllers/Store/Admin/ReportController.php:37` | 1: `wish_report.index` | – | – | `resources/js/lib/storeAdminMenu.ts:565` | no |
| `products_stock_report` | `app/Http/Controllers/Store/Admin/ReportController.php:36` | 1: `stock_report.index` | – | – | `resources/js/lib/storeAdminMenu.ts:558` | no |
| `publish_blog` | `app/Http/Controllers/Store/Admin/BlogController.php:34` | 1: `blog.change-status` | – | `resources/js/pages/Store/Admin/Blog/Posts/Index.vue:91` | – | yes |
| `publish_classified_product` | `app/Http/Controllers/CustomerProductController.php:30`; `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:25` | 2: `classified_products.published`, `customer_products.published` | – | `resources/js/pages/Store/Admin/Customers/ClassifiedProducts/Index.vue:92` | – | no |
| `publish_dynamic_popups` | `app/Http/Controllers/Store/Admin/DynamicPopupController.php:38` | 1: `dynamic-popups.update-status` | – | `resources/js/pages/Store/Admin/Marketing/DynamicPopups/Index.vue:175` | – | no |
| `publish_flash_deal` | `app/Http/Controllers/Store/Admin/FlashDealController.php:31` | 1: `flash_deals.update_featured` | – | `resources/js/pages/Store/Admin/Marketing/FlashDeals/Index.vue:112` | – | no |
| `publish_product_review` | `app/Http/Controllers/Store/Admin/ReviewController.php:27` | 1: `reviews.published` | – | `resources/js/pages/Store/Admin/Product/Reviews/Index.vue:97` | – | no |
| `reply_to_product_conversations` | `app/Http/Controllers/Store/Admin/ConversationController.php:27` | 1: `conversations.admin_reply` | – | `resources/js/pages/Store/Admin/Support/Conversations/Show.vue:81` | – | no |
| `reply_to_product_queries` | `app/Http/Controllers/Store/Admin/ProductQueryController.php:28` | 1: `product_query.reply` | – | `resources/js/pages/Store/Admin/Support/ProductQueries/Show.vue:56` | – | no |
| `reply_to_support_tickets` | `app/Http/Controllers/Store/Admin/SupportTicketController.php:27` | 1: `support_ticket.admin_store` | – | `resources/js/pages/Store/Admin/Support/Tickets/Show.vue:108` | – | yes |
| `seller_commission_configuration` | `app/Http/Controllers/Store/Admin/SellerCommissionController.php:21` | 2: `business_settings.vendor_commission`, `business_settings.vendor_commission.update` | – | `resources/js/pages/Store/Admin/Sellers/Commission/Index.vue:20` | `resources/js/lib/storeAdminMenu.ts:417` | no |
| `seller_payment_history` | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:31` | 2: `sellers.payment_histories`, `sellers.payment_history` | `app/Http/Resources/Store/SellerResource.php:36` | `resources/js/pages/Store/Admin/Sellers/Payments/Index.vue:37,61`; `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:281`; `resources/js/pages/Store/Admin/Sellers/Withdrawals/Index.vue:106` | `resources/js/lib/storeAdminMenu.ts:401` | no |
| `seller_products_sale_report` | `app/Http/Controllers/Store/Admin/ReportController.php:35` | 1: `seller_sale_report.index` | – | – | `resources/js/lib/storeAdminMenu.ts:551` | no |
| `seller_verification_form_configuration` | `app/Http/Controllers/Store/Admin/SellerVerificationFormController.php:21` | 2: `seller_verification_form.index`, `seller_verification_form.update` | – | `resources/js/pages/Store/Admin/Sellers/VerificationForm/Index.vue:86` | `resources/js/lib/storeAdminMenu.ts:424` | no |
| `send_newsletter` | `app/Http/Controllers/Store/Admin/NewsletterController.php:26`; `app/Http/Controllers/Store/Admin/SettingsController.php:118` | 4: `newsletters.index`, `newsletters.recipients`, `newsletters.send`, `test.smtp` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:115` | `resources/js/lib/storeAdminMenu.ts:640` | no |
| `shipping_configuration` | `app/Http/Controllers/Store/Admin/ShippingConfigurationController.php:21` | 2: `shipping_configuration.index`, `shipping_configuration.update` | – | `resources/js/pages/Store/Admin/Setup/ShippingConfiguration/Index.vue:37` | `resources/js/lib/storeAdminMenu.ts:853` | no |
| `shipping_country_setting` | `app/Http/Controllers/Store/Admin/CountryController.php:21` | 2: `countries.index`, `countries.status` | – | `resources/js/pages/Store/Admin/Setup/Countries/Index.vue:78` | `resources/js/lib/storeAdminMenu.ts:859` | no |
| `show_all_products` | `app/Http/Controllers/Store/Admin/ProductController.php:73` | 1: `products.all` | – | `resources/js/pages/Store/Admin/Product/Products/Index.vue:265` | `resources/js/lib/storeAdminMenu.ts:200` | no |
| `show_digital_products` | `app/Http/Controllers/Store/Admin/DigitalProductController.php:56` | 1: `digitalproducts.index` | – | – | `resources/js/lib/storeAdminMenu.ts:220` | no |
| `show_in_house_products` | `app/Http/Controllers/Store/Admin/ProductController.php:72` | 1: `products.admin` | – | – | `resources/js/lib/storeAdminMenu.ts:206` | no |
| `show_seller_products` | `app/Http/Controllers/Store/Admin/ProductController.php:74` | 1: `products.seller` | – | – | `resources/js/lib/storeAdminMenu.ts:213` | no |
| `smtp_settings` | `app/Http/Controllers/Store/Admin/SettingsController.php:108` | 2: `smtp_settings.index`, `test.smtp` | `app/Http/Controllers/Store/Admin/DashboardController.php:61` | `resources/js/pages/Store/Admin/Dashboard/Index.vue:63`; `resources/js/pages/Store/Admin/Settings/Index.vue:50,114` | `resources/js/lib/storeAdminMenu.ts:773` | no |
| `social_media_logins` | `app/Http/Controllers/Store/Admin/SettingsController.php:109` | 1: `social_login.index` | – | `resources/js/pages/Store/Admin/Settings/Index.vue:51` | `resources/js/lib/storeAdminMenu.ts:794` | no |
| `update_order_delivery_status` | `app/Http/Controllers/Store/Admin/OrderController.php:39` | 1: `orders.update_delivery_status` | – | `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:43` | – | no |
| `update_order_notes` | `app/Http/Controllers/Store/Admin/OrderController.php:42` | 1: `orders.update_manager_notes` | – | `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:176` | – | yes |
| `update_order_payment_status` | `app/Http/Controllers/Store/Admin/OrderController.php:40` | 1: `orders.update_payment_status` | – | `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:63` | – | no |
| `update_order_tracking` | `app/Http/Controllers/Store/Admin/OrderController.php:41` | 1: `orders.update_tracking_code` | – | `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:158` | – | yes |
| `upload_files` | `app/Http/Controllers/Store/Admin/UploadController.php:31` | 2: `uploaded-files.create`, `uploaded-files.store` | – | `resources/js/components/StoreUploadManager.vue:253,453` | – | yes |
| `user_search_report` | `app/Http/Controllers/Store/Admin/ReportController.php:38` | 1: `user_search_report.index` | – | – | `resources/js/lib/storeAdminMenu.ts:572` | no |
| `vat_&_tax_setup` | `app/Http/Controllers/Store/Admin/TaxController.php:25` | 6: `tax.destroy`, `tax.edit`, `tax.index`, `tax.store`, `tax.update`, `taxes.tax-status` | – | `resources/js/pages/Store/Admin/Setup/Taxes/Index.vue:55,63,91` | `resources/js/lib/storeAdminMenu.ts:759` | no |
| `view_all_brands` | `app/Http/Controllers/Store/Admin/BrandController.php:38`; `app/Http/Controllers/Store/Admin/BrandDiscountController.php:28` | 2: `brands.index`, `brands_wise_product_discount` | – | – | `resources/js/lib/storeAdminMenu.ts:244,249` | no |
| `view_all_coupons` | `app/Http/Controllers/Store/Admin/CouponController.php:30` | 1: `coupon.index` | – | – | `resources/js/lib/storeAdminMenu.ts:654` | no |
| `view_all_customers` | `app/Http/Controllers/Store/Admin/CustomerController.php:47` | 5: `customers.index`, `customers.page-views`, `customers.whatsapp_contact`, `download.trade_license`, `representatives.index` | – | `resources/js/pages/Store/Admin/Customers/Customers/Index.vue:268` | `resources/js/lib/storeAdminMenu.ts:313,352,363` | no |
| `view_all_drivers` | `app/Http/Controllers/Store/Admin/DriverController.php:27` | 1: `drivers.index` | – | – | `resources/js/lib/storeAdminMenu.ts:494` | yes |
| `view_all_dynamic_popups` | `app/Http/Controllers/Store/Admin/DynamicPopupController.php:34` | 1: `dynamic-popups.index` | – | – | `resources/js/lib/storeAdminMenu.ts:633` | no |
| `view_all_flash_deals` | `app/Http/Controllers/Store/Admin/FlashDealController.php:31` | 1: `flash_deals.index` | – | – | `resources/js/lib/storeAdminMenu.ts:619` | no |
| `view_all_offer_deals` | `app/Http/Controllers/Store/Admin/OfferController.php:28` | 1: `offer_deals.index` | – | – | `resources/js/lib/storeAdminMenu.ts:626` | no |
| `view_all_orders` | `app/Http/Controllers/Store/Admin/OrderController.php:36` | 4: `all_orders.index (any-of)`, `inhouse_orders.index (any-of)`, `pick_up_point.index (any-of)`, `seller_orders.index (any-of)` | – | – | `resources/js/lib/storeAdminMenu.ts:281` | no |
| `view_all_product_conversations` | `app/Http/Controllers/Store/Admin/ConversationController.php:26` | 2: `conversations.admin_index`, `conversations.admin_show` | `app/Http/Middleware/HandleInertiaRequests.php:155` | – | `resources/js/lib/storeAdminMenu.ts:676` | no |
| `view_all_product_queries` | `app/Http/Controllers/ProductQueryController.php:22`; `app/Http/Controllers/Store/Admin/ProductQueryController.php:27` | 4: `product-queries.index`, `product-queries.show`, `product_query.index`, `product_query.show` | – | – | `resources/js/lib/storeAdminMenu.ts:683` | no |
| `view_all_seller` | `app/Http/Controllers/Store/Admin/SellerController.php:43` | 3: `sellers.index`, `sellers.show_verification_request`, `sellers.verification_file` | `app/Http/Middleware/HandleInertiaRequests.php:153` | – | `resources/js/lib/storeAdminMenu.ts:395` | no |
| `view_all_staffs` | `app/Http/Controllers/Store/Admin/StaffController.php:38` | 1: `staffs.index` | – | – | `resources/js/lib/storeAdminMenu.ts:897` | no |
| `view_all_subscribers` | `app/Http/Controllers/Store/Admin/SubscriberController.php:22`; `app/Http/Controllers/SubscriberController.php:24` | 2: `admin.subscribers.index`, `subscribers.index` | – | – | `resources/js/lib/storeAdminMenu.ts:647` | no |
| `view_all_support_tickets` | `app/Http/Controllers/Store/Admin/SupportTicketController.php:27` | 2: `support_ticket.admin_index`, `support_ticket.admin_show` | `app/Http/Middleware/HandleInertiaRequests.php:154` | – | `resources/js/lib/storeAdminMenu.ts:669` | no |
| `view_all_trips` | `app/Http/Controllers/Store/Admin/TripCheckpointController.php:40`; `app/Http/Controllers/Store/Admin/TripController.php:56`; `app/Http/Controllers/Store/Admin/TripOrderController.php:48` | 5: `checkpoints.print-cmr`, `trips.index`, `trips.orders`, `trips.show`, `trips.today-trips` | – | `resources/js/pages/Store/Admin/Trips/Trucks/Index.vue:90` | `resources/js/lib/storeAdminMenu.ts:458,464,482` | yes |
| `view_all_trucks` | `app/Http/Controllers/Store/Admin/TruckController.php:36` | 2: `trucks.index`, `trucks.qr` | – | – | `resources/js/lib/storeAdminMenu.ts:441` | yes |
| `view_all_website_pages` | `app/Http/Controllers/Store/Admin/WebsitePageController.php:41` | 1: `website.pages` | – | – | `resources/js/lib/storeAdminMenu.ts:711` | no |
| `view_blog_categories` | `app/Http/Controllers/Store/Admin/BlogCategoryController.php:24` | 1: `blog-category.index` | – | – | `resources/js/lib/storeAdminMenu.ts:606` | no |
| `view_blogs` | `app/Http/Controllers/Store/Admin/BlogController.php:30` | 1: `blog.index` | – | – | `resources/js/lib/storeAdminMenu.ts:600` | no |
| `view_classified_packages` | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:35` | 1: `customer_packages.index` | – | – | `resources/js/lib/storeAdminMenu.ts:344` | no |
| `view_classified_products` | `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:24` | 1: `classified_products` | – | – | `resources/js/lib/storeAdminMenu.ts:337` | no |
| `view_colors` | `app/Http/Controllers/Store/Admin/ColorController.php:25` | 1: `colors` | – | – | `resources/js/lib/storeAdminMenu.ts:262` | no |
| `view_inhouse_orders` | `app/Http/Controllers/Store/Admin/OrderController.php:36` | 4: `all_orders.index (any-of)`, `inhouse_orders.index (any-of)`, `pick_up_point.index (any-of)`, `seller_orders.index (any-of)` | – | – | – | no |
| `view_offers_catalog` | `app/Http/Controllers/Store/Admin/OffersCatalogController.php:28` | 2: `(unnamed) GET\|HEAD temp/admin/offers/download`, `offers.index` | – | – | `resources/js/lib/storeAdminMenu.ts:531` | yes |
| `view_order_details` | `app/Http/Controllers/Store/Admin/OrderController.php:37` | 6: `all_orders.show`, `inhouse_orders.show`, `orders.details`, `orders.show`, `pick_up_point.order_show`, `seller_orders.show` | – | `resources/js/pages/Store/Admin/Representatives/Visits/Show.vue:111`; `resources/js/pages/Store/Admin/Sales/Orders/Index.vue:203`; `resources/js/pages/Store/Admin/Sales/Orders/Show.vue:109`; `resources/js/pages/Store/Admin/Trips/Trips/Show.vue:286` | – | no |
| `view_pickup_point_orders` | `app/Http/Controllers/Store/Admin/OrderController.php:36` | 4: `all_orders.index (any-of)`, `inhouse_orders.index (any-of)`, `pick_up_point.index (any-of)`, `seller_orders.index (any-of)` | – | – | `resources/js/lib/storeAdminMenu.ts:300` | no |
| `view_product_attribute_values` | `app/Http/Controllers/Store/Admin/AttributeValueController.php:26` | 1: `attributes.show` | – | `resources/js/pages/Store/Admin/Product/Attributes/Index.vue:74` | – | no |
| `view_product_attributes` | `app/Http/Controllers/Store/Admin/AttributeController.php:28` | 1: `attributes.index` | – | – | `resources/js/lib/storeAdminMenu.ts:256` | no |
| `view_product_categories` | `app/Http/Controllers/Store/Admin/CategoryController.php:43` | 1: `categories.index` | – | – | `resources/js/lib/storeAdminMenu.ts:238` | no |
| `view_product_reviews` | `app/Http/Controllers/Store/Admin/ReviewController.php:26` | 2: `admin.reviews.index`, `reviews.index` | – | – | `resources/js/lib/storeAdminMenu.ts:268` | no |
| `view_representative_packages` | `app/Http/Controllers/Store/Admin/RepresentativePackageController.php:30` | 1: `rep.packages.index` | – | – | `resources/js/lib/storeAdminMenu.ts:352,369` | yes |
| `view_representative_payments` | `app/Http/Controllers/Store/Admin/RepresentativePaymentController.php:28` | 1: `admin.rep.payments.index` | – | – | `resources/js/lib/storeAdminMenu.ts:352,375` | yes |
| `view_representative_visits` | `app/Http/Controllers/Store/Admin/VisitController.php:30`; `routes/shop-admin.php:252` | 4: `admin.rep.visits.index`, `admin.rep.visits.note`, `admin.rep.visits.rep-show`, `admin.rep.visits.show` | – | `resources/js/pages/Store/Admin/Representatives/Visits/Show.vue:49,169` | `resources/js/lib/storeAdminMenu.ts:352,381` | yes |
| `view_seller_orders` | `app/Http/Controllers/Store/Admin/OrderController.php:36` | 4: `all_orders.index (any-of)`, `inhouse_orders.index (any-of)`, `pick_up_point.index (any-of)`, `seller_orders.index (any-of)` | – | – | `resources/js/lib/storeAdminMenu.ts:293` | no |
| `view_seller_payout_requests` | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` | 9: `withdraw_request.message_modal`, `withdraw_request.payment_modal`, `withdraw_requests.create`, `withdraw_requests.destroy`, `withdraw_requests.edit`, `withdraw_requests.index`, `withdraw_requests.show`, `withdraw_requests.update`, `withdraw_requests_all` | – | `resources/js/pages/Store/Admin/Sellers/Withdrawals/Index.vue:93` | `resources/js/lib/storeAdminMenu.ts:410` | no |
| `view_seller_profile` | `app/Http/Controllers/Store/Admin/SellerController.php:44` | 1: `sellers.profile_modal` | – | `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue:250,335` | – | no |
| `view_staff_roles` | `app/Http/Controllers/Store/Admin/RoleController.php:62` | 1: `roles.index` | – | – | `resources/js/lib/storeAdminMenu.ts:903` | no |
| `view_uploads` | `app/Http/Controllers/Store/Admin/UploadController.php:30` | 3: `uploaded-files.files`, `uploaded-files.index`, `uploaded-files.info` | `app/Http/Controllers/Concerns/ProvidesStoreUploadPreviews.php:19`; `app/Http/Controllers/Store/Admin/TruckController.php:131`; `app/Http/Requests/Concerns/ValidatesStoreTruck.php:36`; `app/Http/Requests/Concerns/ValidatesStoreUploadReference.php:93`; `app/Http/Requests/Concerns/ValidatesWebsiteSettings.php:19,39` | `resources/js/components/StoreRichTextField.vue:740`; `resources/js/components/StoreUploadField.vue:49,98,127`; `resources/js/components/StoreUploadManager.vue:67,161,241,264` | `resources/js/lib/storeAdminMenu.ts:509` | yes |
| `wallet_transaction_report` | `app/Http/Controllers/Store/Admin/ReportController.php:40` | 1: `wallet-history.index` | – | – | `resources/js/lib/storeAdminMenu.ts:586` | no |
| `website_appearance` | `app/Http/Controllers/Store/Admin/LegacyWebsiteScriptsController.php:18`; `app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29` | 3: `website.appearance`, `website.appearance.update`, `website.scripts.compatibility.update` | – | – | `resources/js/lib/storeAdminMenu.ts:718` | no |

## B. Permissions the repository defines

**Seeders:** no seeder creates a permission. `StoreRoleSeeder` creates roles
`Super Admin` and `Clerk` on guard `store`
(`database/seeders/StoreRoleSeeder.php:18-19`); `StoreUserSeeder` gives
`store-admin@example.com` the Super Admin role
(`database/seeders/StoreUserSeeder.php:31`). Neither grants a permission to a
role.

**Test helpers:** `tests/Pest.php` defines no fixed list. `createStoreStaff()`
calls `Permission::findOrCreate($name, 'store')` for whatever names a test passes
(`tests/Pest.php:255-261`), `createStoreAdmin()` creates the Super Admin role
(`:240-246`), and `createStaffRow()` a role, default `Clerk` (`:270-273`). Test
fixtures are not production definitions; the JSON counts per name how many test
sites create it (`test_fixture_sites`).

**Migrations:**

| Migration | Names (section) | Idempotent | Rollback deletes rows | Assigns to a role | Flushes spatie cache |
| --- | --- | --- | --- | --- | --- |
| `database/migrations/2026_08_09_114507_create_permission_tables.php` | creates the `wms_*` tables (no rows) | – | drops tables | no | – |
| `database/migrations/2026_09_28_006000_add_section_to_wms_permissions_table.php` | adds nullable `section` varchar(50) (`:24`) | no (plain `Schema::table`) | drops the column (`:34`) | no | – |
| `database/migrations/2026_10_08_090745_add_store_upload_permissions.php` | `view_uploads`, `upload_files`, `delete_uploads`, `delete_all_uploads` (`uploads`) | yes, `insertOrIgnore` (`:12`) | no (`:20-25`) | no | yes (`:17`) |
| `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php` | `edit_customer` (`customer`); 10 representative names (`representatives`) | yes (`:29`) | no (`:42`, empty) | no | yes (`:38`) |
| `database/migrations/2026_10_08_211642_add_offers_catalog_permissions.php` | `view_offers_catalog`, `add_offers_catalog`, `delete_offers_catalog` (`offers_catalog`) | yes (`:12`) | no (`:24-27`) | no | yes (`:21`) |
| `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php` | 4 trip, 4 truck, 4 driver names; `view_representative_visits`, `delete_representative_visit` again (`representatives`); `google_map_setting` (`setup_configurations`) | yes (`:24`) | no (`:40-44`) | no | yes (`:34`) |
| `database/migrations/2026_10_10_035933_add_order_entry_permissions.php` | `add_order`, `edit_order`, `update_order_tracking`, `update_order_notes`, `edit_order_price` (`sales`) | yes (`:11`) | no (`:15-18`) | no | **no** (see §G) |
| `database/migrations/2026_10_11_235900_define_store_blog_and_support_action_permissions.php` | `publish_blog` (`blog`), `reply_to_support_tickets` (`support`) | yes (`:12`) | no (`:20-23`) | no | yes (`:17`) |

`2026_09_08_000000_rename_colliding_tables_to_wms_prefix.php` and
`2026_09_28_004007_create_role_translations_table.php` mention permissions only in
table names and comments. As the guide says (§4.1), no definition migration
assigns a permission to a role and none deletes rows on rollback. Two names are
defined twice (`view_representative_visits`, `delete_representative_visit`, in
the customer-administration and logistics migrations); `insertOrIgnore` makes the
second a no-op, and the `(name, guard_name)` unique key keeps one row.

Three of the 38 were already in the legacy code (`google_map_setting`,
`publish_blog`, `reply_to_support_tickets`), so production may hold them with
existing grants; the other 35 are brand-new.

| Permission | Section | Defined at | Enforced now |
| --- | --- | --- | --- |
| `add_driver` | `drivers` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:17` | yes |
| `add_offers_catalog` | `offers_catalog` | `database/migrations/2026_10_08_211642_add_offers_catalog_permissions.php:11` | yes |
| `add_order` | `sales` | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10` | yes |
| `add_representative_package` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:16` | yes |
| `add_trip` | `trips` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:15` | yes |
| `add_truck` | `trucks` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:16` | yes |
| `approve_representative_payment` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:21` | yes |
| `assign_representative_package` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:19` | yes |
| `delete_all_uploads` | `uploads` | `database/migrations/2026_10_08_090745_add_store_upload_permissions.php:11` | yes |
| `delete_driver` | `drivers` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:17` | yes |
| `delete_offers_catalog` | `offers_catalog` | `database/migrations/2026_10_08_211642_add_offers_catalog_permissions.php:11` | yes |
| `delete_representative_package` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:18` | yes |
| `delete_representative_visit` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:24` | yes |
| `delete_representative_visit` | `representatives` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:18` | yes |
| `delete_trip` | `trips` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:15` | yes |
| `delete_truck` | `trucks` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:16` | **no (dead)** |
| `delete_uploads` | `uploads` | `database/migrations/2026_10_08_090745_add_store_upload_permissions.php:11` | yes |
| `edit_customer` | `customer` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:13` | yes |
| `edit_driver` | `drivers` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:17` | yes |
| `edit_order` | `sales` | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10` | yes |
| `edit_order_price` | `sales` | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10` | yes |
| `edit_representative_package` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:17` | yes |
| `edit_trip` | `trips` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:15` | yes |
| `edit_truck` | `trucks` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:16` | yes |
| `export_representative_payment` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:22` | yes |
| `google_map_setting` | `setup_configurations` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:19` | yes |
| `publish_blog` | `blog` | `database/migrations/2026_10_11_235900_define_store_blog_and_support_action_permissions.php:11` | yes |
| `reply_to_support_tickets` | `support` | `database/migrations/2026_10_11_235900_define_store_blog_and_support_action_permissions.php:11` | yes |
| `update_order_notes` | `sales` | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10` | yes |
| `update_order_tracking` | `sales` | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10` | yes |
| `upload_files` | `uploads` | `database/migrations/2026_10_08_090745_add_store_upload_permissions.php:11` | yes |
| `view_all_drivers` | `drivers` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:17` | yes |
| `view_all_trips` | `trips` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:15` | yes |
| `view_all_trucks` | `trucks` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:16` | yes |
| `view_offers_catalog` | `offers_catalog` | `database/migrations/2026_10_08_211642_add_offers_catalog_permissions.php:11` | yes |
| `view_representative_packages` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:15` | yes |
| `view_representative_payments` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:20` | yes |
| `view_representative_visits` | `representatives` | `database/migrations/2026_10_08_102608_add_customer_administration_permissions.php:23` | yes |
| `view_representative_visits` | `representatives` | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:18` | yes |
| `view_uploads` | `uploads` | `database/migrations/2026_10_08_090745_add_store_upload_permissions.php:11` | yes |

## C. Enforced versus defined

### C.1 Enforced but never defined in the repository (144): needs production check

Every name below is enforced by code, created by no migration or seeder, and
was referenced by the legacy store at mount (`c3f6422e`). **If a row is absent
in production, the routes listed are unreachable for everyone but a Super
Admin** (a route listed under "any-of only" stays reachable through its other
names). Q2 of the SQL file lists the absent ones.

| Permission | First backend-enforced at | Routes unreachable for non-Super Admin if the row is absent |
| --- | --- | --- |
| `add_blog` | `c3f6422e` | `blog.create`, `blog.store` |
| `add_blog_category` | `c3f6422e` | `blog-category.create`, `blog-category.store` |
| `add_brand` | `c3f6422e` | `brands.store` |
| `add_coupon` | `c3f6422e` | `coupon.create`, `coupon.store`; any-of only: `coupon.product-options` |
| `add_digital_product` | `c3f6422e` | `digitalproducts.create`, `digitalproducts.store` |
| `add_dynamic_popups` | `c3f6422e` | `dynamic-popups.create`, `dynamic-popups.store` |
| `add_flash_deal` | `c3f6422e` | `flash_deals.create`, `flash_deals.store`; any-of only: `admin.campaign-products.brands`, `admin.campaign-products.index` |
| `add_new_product` | `c3f6422e` | `products.create`, `products.store`, `products.variant_combinations`; any-of only: `products.add-more-choice-option` |
| `add_notification` | `8e3f82b6` | `admin.notifications.all`, `admin.notifications.create`, `admin.notifications.send` |
| `add_offer_deal` | `8e3f82b6` | `offer_deals.brands.create`, `offer_deals.create`, `offer_deals.store`; any-of only: `admin.campaign-products.brands`, `admin.campaign-products.index` |
| `add_product_category` | `c3f6422e` | `categories.create`, `categories.store` |
| `add_staff` | `c3f6422e` | `staffs.create`, `staffs.store` |
| `add_staff_role` | `c3f6422e` | `roles.create`, `roles.store` |
| `add_website_page` | `c3f6422e` | `custom-pages.create`, `custom-pages.store` |
| `admin_dashboard` | `6ec65e40` | `shop.admin.dashboard` |
| `analytics_tools_configuration` | `c3f6422e` | `facebook_pixel.update`, `google_analytics.index`, `google_analytics.update` |
| `ban_customer` | `c3f6422e` | `customers.ban` |
| `ban_seller` | `c3f6422e` | `sellers.ban` |
| `commission_history_report` | `c3f6422e` | `commission-log.index` |
| `currency_setup` | `c3f6422e` | `currency.create`, `currency.edit`, `currency.index`, `currency.settings.update`, `currency.store`, `currency.update_status`, `your_currency.update` |
| `delete_blog` | `c3f6422e` | `blog.destroy` |
| `delete_blog_category` | `c3f6422e` | `blog-category.destroy` |
| `delete_brand` | `c3f6422e` | `brands.destroy` |
| `delete_classified_package` | `c3f6422e` | `customer_packages.destroy` |
| `delete_classified_product` | `c3f6422e` | `classified_products.destroy` |
| `delete_color` | `c3f6422e` | `colors.destroy` |
| `delete_coupon` | `c3f6422e` | `coupon.destroy` |
| `delete_customer` | `c3f6422e` | `bulk-customer-delete`, `customers.archive`, `customers.destroy`, `customers.restore` |
| `delete_digital_product` | `c3f6422e` | `digitalproducts.destroy` |
| `delete_dynamic_popups` | `c3f6422e` | `bulk-dynamic-popup-delete`, `dynamic-popups.destroy` |
| `delete_flash_deal` | `c3f6422e` | `flash_deals.destroy` |
| `delete_notification` | `8e3f82b6` | `admin.notifications.delete` |
| `delete_offer_deal` | `8e3f82b6` | `offer_deals.destroy` |
| `delete_order` | `c3f6422e` | `bulk-order-delete`, `orders.destroy` |
| `delete_product_attribute` | `c3f6422e` | `attributes.destroy` |
| `delete_product_attribute_value` | `c3f6422e` | `destroy-attribute-value` |
| `delete_product_category` | `c3f6422e` | `categories.destroy` |
| `delete_product_conversations` | `c3f6422e` | `(unnamed) DELETE temp/conversations/{conversation}`, `conversations.admin_destroy`, `conversations.destroy` |
| `delete_seller` | `c3f6422e` | `bulk-seller-delete`, `sellers.destroy` |
| `delete_staff` | `c3f6422e` | `staffs.destroy` |
| `delete_staff_role` | `c3f6422e` | `roles.destroy` |
| `delete_subscriber` | `c3f6422e` | `subscriber.destroy`, `subscribers.destroy` |
| `delete_website_page` | `c3f6422e` | `custom-pages.destroy` |
| `download_digital_product` | `c3f6422e` | `digitalproducts.download` |
| `edit_blog` | `c3f6422e` | `blog.edit`, `blog.update` |
| `edit_blog_category` | `c3f6422e` | `blog-category.edit`, `blog-category.update` |
| `edit_brand` | `c3f6422e` | `brands.edit`, `brands.update` |
| `edit_classified_package` | `c3f6422e` | `customer_packages.create`, `customer_packages.edit`, `customer_packages.store`, `customer_packages.update` |
| `edit_color` | `c3f6422e` | `colors.edit`, `colors.store`, `colors.update` |
| `edit_coupon` | `c3f6422e` | `coupon.edit`, `coupon.update`; any-of only: `coupon.product-options` |
| `edit_digital_product` | `c3f6422e` | `digitalproducts.edit`, `digitalproducts.update` |
| `edit_dynamic_popups` | `c3f6422e` | `dynamic-popups.edit`, `dynamic-popups.update` |
| `edit_flash_deal` | `c3f6422e` | `flash_deals.edit`, `flash_deals.update`, `flash_deals.update_status`; any-of only: `admin.campaign-products.brands`, `admin.campaign-products.index` |
| `edit_offer_deal` | `8e3f82b6` | `offer_deals.edit`, `offer_deals.update`, `offer_deals.update_multiple_usage`, `offer_deals.update_repeatable`, `offer_deals.update_status`; any-of only: `admin.campaign-products.brands`, `admin.campaign-products.index` |
| `edit_product_attribute` | `c3f6422e` | `attributes.edit`, `attributes.store`, `attributes.update` |
| `edit_product_attribute_value` | `c3f6422e` | `edit-attribute-value`, `store-attribute-value`, `update-attribute-value` |
| `edit_product_category` | `c3f6422e` | `categories.edit`, `categories.featured`, `categories.update` |
| `edit_seller` | `c3f6422e` | `sellers.approve`, `sellers.approved`, `sellers.create`, `sellers.edit`, `sellers.reject`, `sellers.store`, `sellers.update` |
| `edit_staff` | `c3f6422e` | `staffs.edit`, `staffs.update` |
| `edit_staff_role` | `c3f6422e` | `roles.edit`, `roles.update` |
| `edit_website_page` | `c3f6422e` | `custom-pages.edit`, `custom-pages.update`, `website.home.edit`, `website.home.update` |
| `facebook_chat` | `c3f6422e` | `facebook_chat.index`, `facebook_chat.update` |
| `facebook_comment` | `c3f6422e` | `facebook-comment`, `facebook-comment.update` |
| `features_activation` | `c3f6422e` | `activation.index`, `business_settings.seller_commission.activation`, `business_settings.update.activation` |
| `file_system_&_cache_configuration` | `c3f6422e` | `file_system.index` |
| `footer_setup` | `c3f6422e` | `website.footer`, `website.footer.update` |
| `general_settings` | `c3f6422e` | `general_setting.index`, `general_setting.update` |
| `google_firebase_setting` | `c3f6422e` | `google-firebase.index`, `google-firebase.update` |
| `google_recaptcha_configuration` | `c3f6422e` | `google_recaptcha.index`, `google_recaptcha.update` |
| `header_setup` | `c3f6422e` | `website.header`, `website.header.update` |
| `in_house_product_sale_report` | `c3f6422e` | `in_house_sale_report.index` |
| `language_setup` | `c3f6422e` | `app-translations.export`, `app-translations.import`, `app-translations.show`, `app-translations.store`, `languages.create`, `languages.destroy`, `languages.edit`, `languages.index`, `languages.key_value_store`, `languages.show`, `languages.store`, `languages.update`, `languages.update-status`, `languages.update_rtl_status` |
| `login_as_customer` | `c3f6422e` | `customers.login` |
| `login_as_seller` | `c3f6422e` | `sellers.login` |
| `manage_carriers` | `c3f6422e` | `carriers.create`, `carriers.destroy`, `carriers.edit`, `carriers.index`, `carriers.store`, `carriers.update`, `carriers.update_status` |
| `manage_shipping_cities` | `c3f6422e` | `cities.destroy`, `cities.edit`, `cities.index`, `cities.status`, `cities.store`, `cities.update` |
| `manage_shipping_states` | `c3f6422e` | `states.destroy`, `states.edit`, `states.index`, `states.status`, `states.store`, `states.update` |
| `manage_zones` | `c3f6422e` | `zones.create`, `zones.destroy`, `zones.edit`, `zones.index`, `zones.store`, `zones.update` |
| `order_configuration` | `c3f6422e` | `order_configuration.index`, `order_configuration.product-search`, `order_configuration.representative.update`, `order_configuration.update` |
| `pay_to_seller` | `c3f6422e` | `commissions.pay_to_seller`, `sellers.payment_modal` |
| `payment_methods_configurations` | `c3f6422e` | `payment_method.index`, `payment_method.update` |
| `pickup_point_setup` | `c3f6422e` | `pick_up_points.create`, `pick_up_points.destroy`, `pick_up_points.edit`, `pick_up_points.index`, `pick_up_points.store`, `pick_up_points.update` |
| `product_bulk_export` | `c3f6422e` | `product_bulk_export.index` |
| `product_bulk_import` | `c3f6422e` | `bulk_product_upload`, `pdf.download_brand`, `pdf.download_category`, `pdf.download_seller`, `product_bulk_upload.index` |
| `product_delete` | `c3f6422e` | `bulk-product-delete`, `products.destroy`, `products.list.bulk_destroy`, `products.list.destroy` |
| `product_duplicate` | `c3f6422e` | `products.duplicate`, `products.list.duplicate` |
| `product_edit` | `c3f6422e` | `digitalproducts.featured`, `digitalproducts.published`, `digitalproducts.todays_deal`, `products.admin.edit`, `products.approved`, `products.edit_variant_combinations`, `products.featured`, `products.list.featured`, `products.list.for_sale`, `products.list.published`, `products.published`, `products.seller.edit`, `products.todays_deal`, `products.update`, `products.update_sale`, `set_product_discount`; any-of only: `products.add-more-choice-option` |
| `product_wishlist_report` | `c3f6422e` | `wish_report.index` |
| `products_stock_report` | `c3f6422e` | `stock_report.index` |
| `publish_classified_product` | `c3f6422e` | `classified_products.published`, `customer_products.published` |
| `publish_dynamic_popups` | `c3f6422e` | `dynamic-popups.update-status` |
| `publish_flash_deal` | `c3f6422e` | `flash_deals.update_featured` |
| `publish_product_review` | `c3f6422e` | `reviews.published` |
| `reply_to_product_conversations` | `018dd180` | `conversations.admin_reply` |
| `reply_to_product_queries` | `018dd180` | `product_query.reply` |
| `seller_commission_configuration` | `c3f6422e` | `business_settings.vendor_commission`, `business_settings.vendor_commission.update` |
| `seller_payment_history` | `c3f6422e` | `sellers.payment_histories`, `sellers.payment_history` |
| `seller_products_sale_report` | `c3f6422e` | `seller_sale_report.index` |
| `seller_verification_form_configuration` | `c3f6422e` | `seller_verification_form.index`, `seller_verification_form.update` |
| `send_newsletter` | `c3f6422e` | `newsletters.index`, `newsletters.recipients`, `newsletters.send`, `test.smtp` |
| `shipping_configuration` | `c3f6422e` | `shipping_configuration.index`, `shipping_configuration.update` |
| `shipping_country_setting` | `c3f6422e` | `countries.index`, `countries.status` |
| `show_all_products` | `c3f6422e` | `products.all` |
| `show_digital_products` | `c3f6422e` | `digitalproducts.index` |
| `show_in_house_products` | `c3f6422e` | `products.admin` |
| `show_seller_products` | `c3f6422e` | `products.seller` |
| `smtp_settings` | `c3f6422e` | `smtp_settings.index`, `test.smtp` |
| `social_media_logins` | `c3f6422e` | `social_login.index` |
| `update_order_delivery_status` | `018dd180` | `orders.update_delivery_status` |
| `update_order_payment_status` | `018dd180` | `orders.update_payment_status` |
| `user_search_report` | `c3f6422e` | `user_search_report.index` |
| `vat_&_tax_setup` | `c3f6422e` | `tax.destroy`, `tax.edit`, `tax.index`, `tax.store`, `tax.update`, `taxes.tax-status` |
| `view_all_brands` | `c3f6422e` | `brands.index`, `brands_wise_product_discount` |
| `view_all_coupons` | `c3f6422e` | `coupon.index` |
| `view_all_customers` | `c3f6422e` | `customers.index`, `customers.page-views`, `customers.whatsapp_contact`, `download.trade_license`, `representatives.index` |
| `view_all_dynamic_popups` | `c3f6422e` | `dynamic-popups.index` |
| `view_all_flash_deals` | `c3f6422e` | `flash_deals.index` |
| `view_all_offer_deals` | `8e3f82b6` | `offer_deals.index` |
| `view_all_orders` | `c3f6422e` | any-of only: `all_orders.index`, `inhouse_orders.index`, `pick_up_point.index`, `seller_orders.index` |
| `view_all_product_conversations` | `c3f6422e` | `conversations.admin_index`, `conversations.admin_show` |
| `view_all_product_queries` | `8e3f82b6` | `product-queries.index`, `product-queries.show`, `product_query.index`, `product_query.show` |
| `view_all_seller` | `c3f6422e` | `sellers.index`, `sellers.show_verification_request`, `sellers.verification_file` |
| `view_all_staffs` | `c3f6422e` | `staffs.index` |
| `view_all_subscribers` | `c3f6422e` | `admin.subscribers.index`, `subscribers.index` |
| `view_all_support_tickets` | `c3f6422e` | `support_ticket.admin_index`, `support_ticket.admin_show` |
| `view_all_website_pages` | `c3f6422e` | `website.pages` |
| `view_blog_categories` | `c3f6422e` | `blog-category.index` |
| `view_blogs` | `c3f6422e` | `blog.index` |
| `view_classified_packages` | `c3f6422e` | `customer_packages.index` |
| `view_classified_products` | `c3f6422e` | `classified_products` |
| `view_colors` | `c3f6422e` | `colors` |
| `view_inhouse_orders` | `c3f6422e` | any-of only: `all_orders.index`, `inhouse_orders.index`, `pick_up_point.index`, `seller_orders.index` |
| `view_order_details` | `c3f6422e` | `all_orders.show`, `inhouse_orders.show`, `orders.details`, `orders.show`, `pick_up_point.order_show`, `seller_orders.show` |
| `view_pickup_point_orders` | `c3f6422e` | any-of only: `all_orders.index`, `inhouse_orders.index`, `pick_up_point.index`, `seller_orders.index` |
| `view_product_attribute_values` | `c3f6422e` | `attributes.show` |
| `view_product_attributes` | `c3f6422e` | `attributes.index` |
| `view_product_categories` | `c3f6422e` | `categories.index` |
| `view_product_reviews` | `c3f6422e` | `admin.reviews.index`, `reviews.index` |
| `view_seller_orders` | `c3f6422e` | any-of only: `all_orders.index`, `inhouse_orders.index`, `pick_up_point.index`, `seller_orders.index` |
| `view_seller_payout_requests` | `c3f6422e` | `withdraw_request.message_modal`, `withdraw_request.payment_modal`, `withdraw_requests.create`, `withdraw_requests.destroy`, `withdraw_requests.edit`, `withdraw_requests.index`, `withdraw_requests.show`, `withdraw_requests.update`, `withdraw_requests_all` |
| `view_seller_profile` | `c3f6422e` | `sellers.profile_modal` |
| `view_staff_roles` | `c3f6422e` | `roles.index` |
| `wallet_transaction_report` | `c3f6422e` | `wallet-history.index` |
| `website_appearance` | `c3f6422e` | `website.appearance`, `website.appearance.update`, `website.scripts.compatibility.update` |

### C.2 Defined or once enforced, but enforced nowhere now (dead)

- **Defined but never enforced:** `delete_truck` (section `trucks`,
  `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:16`).
  Its routes (`trucks.destroy`, `trucks.bulk-truck-delete`, gated by it at
  `8e3f82b6`) were dropped in `de582875` (drivers/trucks migration, PR #377); no
  truck delete exists now. Either a removed feature (cleanup) or a missing one
  (owner decision).
- **Enforced at legacy mount, enforced nowhere now (50):** cleanup candidates,
  listed in §D.3.

### C.3 Near-duplicate spellings of one idea (report only; nothing merged)

| Idea | Spellings in use | Note |
| --- | --- | --- |
| In-house | `show_in_house_products`, `in_house_product_sale_report` vs `view_inhouse_orders` | `in_house` vs `inhouse` |
| Verb–noun order for products | `product_edit`, `product_delete`, `product_duplicate` vs `add_new_product`, `add_digital_product`, `edit_digital_product`, `delete_digital_product` | noun-first only for physical products |
| Attribute values | `view_product_attribute_values` (plural), legacy `add_product_attribute_values` (plural) vs `edit_product_attribute_value`, `delete_product_attribute_value` (singular) | |
| Popups | `add_dynamic_popups`, `edit_dynamic_popups`, `delete_dynamic_popups`, `publish_dynamic_popups` (plural) vs `add_flash_deal`, `delete_offer_deal` (singular) | |
| "All" listings | `view_all_seller` (singular), `view_all_staffs` (plural), `view_all_customers`, `view_all_brands` vs `view_blogs`, `view_colors`, `view_blog_categories`, `view_product_categories` (no `all`) | |
| Setting/configuration/setup suffix | `google_map_setting`, `google_firebase_setting`, `shipping_country_setting`; `google_recaptcha_configuration`, `analytics_tools_configuration`, `payment_methods_configurations` (plural), `seller_commission_configuration`; `language_setup`, `currency_setup`, `header_setup`, `footer_setup`, `pickup_point_setup`, `vat_&_tax_setup`; `manage_zones`, `manage_carriers`, `manage_shipping_states`, `manage_shipping_cities`; `general_settings`, `smtp_settings` | five suffixes for "may configure X" |
| Special characters | `vat_&_tax_setup`, `file_system_&_cache_configuration` | `&` in a permission name; works, but must be quoted in any shell or URL |
| Seller money | `pay_to_seller`, `seller_payment_history`, `view_seller_payout_requests` | payment vs payout for the same flow |
| Add vs edit (live gate vs dead Blade name) | `edit_color` / `add_color`; `edit_product_attribute` / `add_product_attribute`; `edit_product_attribute_value` / `add_product_attribute_values`; `edit_classified_package` / `add_classified_package`; `edit_seller` / `approve_seller`; `view_all_brands`+`product_edit` / `set_brand_wise_discount` | the live gate is the first; the second is enforced nowhere (§D.3) |
| Uploads scope | `delete_uploads` vs `delete_all_uploads` | different scopes (selected files vs everything), close names |
| Customer packages | route names `customer_packages.*` vs permissions `*_classified_package(s)` | |

## D. Screens that moved or were retired

### D.1 Every route of the §§11–39 modules: gate now, before §11, at legacy mount

"Changed" compares HEAD with `018dd180` (PR #377 merge, the state just before
§11). `(no permission; IsAdmin)` means any admin, staff or accountant account
passed. `(route absent)` means the route did not exist at that commit.

| § | Route | Now | Pre-§11 (`018dd180`) | Legacy mount (`c3f6422e`) | Changed since pre-§11 | Current gate site |
| --- | --- | --- | --- | --- | --- | --- |
| 11 | `carriers.create` | manage_carriers | manage_carriers | manage_carriers | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 11 | `carriers.destroy` | manage_carriers | manage_carriers | manage_carriers | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 11 | `carriers.edit` | manage_carriers | manage_carriers | manage_carriers | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 11 | `carriers.index` | manage_carriers | manage_carriers | manage_carriers | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 11 | `carriers.show` | (route absent) | (no permission; IsAdmin) | (no permission; IsAdmin) | **yes** | – |
| 11 | `carriers.store` | manage_carriers | manage_carriers | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 11 | `carriers.update` | manage_carriers | manage_carriers | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 11 | `carriers.update_status` | manage_carriers | manage_carriers | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CarrierController.php:35` |
| 12 | `customer_packages.create` | edit_classified_package | edit_classified_package | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` |
| 12 | `customer_packages.destroy` | delete_classified_package | delete_classified_package | delete_classified_package | no | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:37` |
| 12 | `customer_packages.edit` | edit_classified_package | edit_classified_package | edit_classified_package | no | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` |
| 12 | `customer_packages.index` | view_classified_packages | view_classified_packages | view_classified_packages | no | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:35` |
| 12 | `customer_packages.purchase` | (no permission) | (no permission) | (no permission) | no | – |
| 12 | `customer_packages.show` | (route absent) | view_classified_packages | (no permission; IsAdmin) | **yes** | – |
| 12 | `customer_packages.store` | edit_classified_package | edit_classified_package | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` |
| 12 | `customer_packages.update` | edit_classified_package | edit_classified_package | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` |
| 13 | `categories.all` | (no permission) | (no permission) | (no permission) | no | – |
| 13 | `categories.create` | add_product_category | add_product_category | add_product_category | no | `app/Http/Controllers/Store/Admin/CategoryController.php:44` |
| 13 | `categories.destroy` | delete_product_category | delete_product_category | delete_product_category | no | `app/Http/Controllers/Store/Admin/CategoryController.php:46` |
| 13 | `categories.edit` | edit_product_category | edit_product_category | edit_product_category | no | `app/Http/Controllers/Store/Admin/CategoryController.php:45` |
| 13 | `categories.featured` | edit_product_category | edit_product_category | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CategoryController.php:45` |
| 13 | `categories.index` | view_product_categories | view_product_categories | view_product_categories | no | `app/Http/Controllers/Store/Admin/CategoryController.php:43` |
| 13 | `categories.show` | (route absent) | view_product_categories | (no permission; IsAdmin) | **yes** | – |
| 13 | `categories.store` | add_product_category | add_product_category | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CategoryController.php:44` |
| 13 | `categories.update` | edit_product_category | edit_product_category | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/CategoryController.php:45` |
| 14 | `brands.all` | (no permission) | (no permission) | (no permission) | no | – |
| 14 | `brands.create` | (route absent) | add_brand | add_brand | **yes** | – |
| 14 | `brands.destroy` | delete_brand | delete_brand | delete_brand | no | `app/Http/Controllers/Store/Admin/BrandController.php:41` |
| 14 | `brands.edit` | edit_brand | edit_brand | edit_brand | no | `app/Http/Controllers/Store/Admin/BrandController.php:40` |
| 14 | `brands.index` | view_all_brands | view_all_brands | view_all_brands | no | `app/Http/Controllers/Store/Admin/BrandController.php:38` |
| 14 | `brands.show` | (route absent) | view_all_brands | (no permission; IsAdmin) | **yes** | – |
| 14 | `brands.store` | add_brand | add_brand | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/BrandController.php:39` |
| 14 | `brands.update` | edit_brand | edit_brand | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/BrandController.php:40` |
| 15 | `digitalproducts.destroy` | delete_digital_product | delete_digital_product | delete_digital_product | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:59` |
| 15 | `digitalproducts.download` | download_digital_product | download_digital_product | download_digital_product | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:60` |
| 15 | `digitalproducts.featured` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/DigitalProductController.php:63` |
| 15 | `digitalproducts.index` | show_digital_products | show_digital_products | show_digital_products | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:56` |
| 15 | `digitalproducts.published` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/DigitalProductController.php:63` |
| 15 | `digitalproducts.todays_deal` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/DigitalProductController.php:63` |
| 16 | `products.admin` | show_in_house_products | show_in_house_products | show_in_house_products | no | `app/Http/Controllers/Store/Admin/ProductController.php:72` |
| 16 | `products.all` | show_all_products | show_all_products | show_all_products | no | `app/Http/Controllers/Store/Admin/ProductController.php:73` |
| 16 | `products.list.bulk_destroy` | product_delete | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:78` |
| 16 | `products.list.destroy` | product_delete | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:78` |
| 16 | `products.list.duplicate` | product_duplicate | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:77` |
| 16 | `products.list.featured` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 16 | `products.list.for_sale` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 16 | `products.list.published` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 17 | `general_setting.index` | general_settings | general_settings | general_settings | no | `app/Http/Controllers/Store/Admin/GeneralSettingsController.php:25` |
| 17 | `general_setting.update` | general_settings | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/GeneralSettingsController.php:25` |
| 18 | `blog.create` | add_blog | add_blog | add_blog | no | `app/Http/Controllers/Store/Admin/BlogController.php:31` |
| 18 | `blog.edit` | edit_blog | edit_blog | edit_blog | no | `app/Http/Controllers/Store/Admin/BlogController.php:32` |
| 18 | `blog.store` | add_blog | add_blog | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/BlogController.php:31` |
| 18 | `blog.update` | edit_blog | edit_blog | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/BlogController.php:32` |
| 19 | `newsletters.index` | send_newsletter | send_newsletter | send_newsletter | no | `app/Http/Controllers/Store/Admin/NewsletterController.php:26` |
| 19 | `newsletters.recipients` | send_newsletter | send_newsletter | (route absent) | no | `app/Http/Controllers/Store/Admin/NewsletterController.php:26` |
| 19 | `newsletters.send` | send_newsletter | send_newsletter | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/NewsletterController.php:26` |
| 19 | `support_ticket.admin_show` | view_all_support_tickets | view_all_support_tickets | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SupportTicketController.php:27` |
| 19 | `support_ticket.admin_store` | reply_to_support_tickets | reply_to_support_tickets | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SupportTicketController.php:27` |
| 20 | `business_settings.update` | (route absent) | general_settings\|footer_setup\|website_appearance\|edit_website_page | (no permission; IsAdmin) | **yes** | – |
| 20 | `custom-pages.create` | add_website_page | add_website_page | add_website_page | no | `app/Http/Controllers/Store/Admin/WebsitePageController.php:42` |
| 20 | `custom-pages.destroy` | delete_website_page | delete_website_page | delete_website_page | no | `app/Http/Controllers/Store/Admin/WebsitePageController.php:44` |
| 20 | `custom-pages.edit` | edit_website_page | edit_website_page | edit_website_page | no | `app/Http/Controllers/Store/Admin/WebsitePageController.php:43` |
| 20 | `custom-pages.store` | add_website_page | add_website_page | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/WebsitePageController.php:42` |
| 20 | `custom-pages.update` | edit_website_page | edit_website_page | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/WebsitePageController.php:43` |
| 20 | `website.appearance` | website_appearance | website_appearance | website_appearance | no | `app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29` |
| 20 | `website.appearance.update` | website_appearance | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29` |
| 20 | `website.footer` | footer_setup | footer_setup | footer_setup | no | `app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29` |
| 20 | `website.footer.update` | footer_setup | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29` |
| 20 | `website.pages` | view_all_website_pages | view_all_website_pages | view_all_website_pages | no | `app/Http/Controllers/Store/Admin/WebsitePageController.php:41` |
| 20 | `website.scripts.compatibility.update` | website_appearance | website_appearance | (route absent) | no | `app/Http/Controllers/Store/Admin/LegacyWebsiteScriptsController.php:18` |
| 21 | `digitalproducts.create` | add_digital_product | add_digital_product | add_digital_product | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:57` |
| 21 | `digitalproducts.edit` | edit_digital_product | edit_digital_product | edit_digital_product | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:58` |
| 21 | `digitalproducts.store` | add_digital_product | add_digital_product | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:57` |
| 21 | `digitalproducts.update` | edit_digital_product | edit_digital_product | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/DigitalProductController.php:58` |
| 22 | `products.create` | add_new_product | add_new_product | add_new_product | no | `app/Http/Controllers/Store/Admin/ProductController.php:69` |
| 22 | `products.store` | add_new_product | add_new_product | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:69` |
| 22 | `products.variant_combinations` | add_new_product | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:69` |
| 23 | `products.admin.edit` | product_edit | product_edit | product_edit | no | `app/Http/Controllers/Store/Admin/ProductController.php:71` |
| 23 | `products.edit_variant_combinations` | product_edit | (route absent) | (route absent) | **yes** | `app/Http/Controllers/Store/Admin/ProductController.php:71` |
| 23 | `products.sku_combination_edit` | (route absent) | (no permission; IsAdmin) | (no permission; IsAdmin) | **yes** | – |
| 23 | `products.update` | product_edit | product_edit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:71` |
| 24 | `trips.bulk-trip-delete` | delete_trip | delete_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:59` |
| 24 | `trips.destroy` | delete_trip | delete_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:59` |
| 24 | `trips.index` | view_all_trips | view_all_trips | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:56` |
| 24 | `trips.today-trips` | view_all_trips | view_all_trips | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:56` |
| 24 | `trips.update-notes` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:58` |
| 24 | `trips.update-status` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:58` |
| 25 | `trips.available-vehicles` | add_trip\|edit_trip | add_trip\|edit_trip | (route absent) | no | `routes/shop-admin.php:305` |
| 25 | `trips.create` | add_trip | add_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:57` |
| 25 | `trips.edit` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:58` |
| 25 | `trips.store` | add_trip | add_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:57` |
| 25 | `trips.update` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripController.php:58` |
| 25 | `trips.user-coordinates` | add_trip\|edit_trip | add_trip\|edit_trip | (route absent) | no | `routes/shop-admin.php:306` |
| 26 | `trips.orders` | view_all_trips | view_all_trips | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripOrderController.php:48` |
| 26 | `trips.orders.bulk-change-delivery-status` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripOrderController.php:49` |
| 26 | `trips.orders.update_invoice_number` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripOrderController.php:49` |
| 26 | `trips.orders.update_order_status_to_delivered` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripOrderController.php:49` |
| 27 | `checkpoints.print-cmr` | view_all_trips | view_all_trips | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripCheckpointController.php:40` |
| 27 | `checkpoints.update-notes` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripCheckpointController.php:41` |
| 27 | `checkpoints.update-status` | edit_trip | edit_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripCheckpointController.php:41` |
| 27 | `trips.show` | view_all_trips | view_all_trips | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripCheckpointController.php:40` |
| 28 | `admin.reviews.index` | view_product_reviews | view_product_reviews | (route absent) | no | `app/Http/Controllers/Store/Admin/ReviewController.php:26` |
| 28 | `reviews.index` | view_product_reviews | view_product_reviews | view_product_reviews | no | `app/Http/Controllers/Store/Admin/ReviewController.php:26` |
| 28 | `reviews.published` | publish_product_review | publish_product_review | publish_product_review | no | `app/Http/Controllers/Store/Admin/ReviewController.php:27` |
| 29 | `system_server` | (route absent) | RaizerSuperAdmin | RaizerSuperAdmin | **yes** | – |
| 30 | `trips.create-from-map` | add_trip | add_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripMapController.php:51` |
| 30 | `trips.store-from-map` | add_trip | add_trip | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/TripMapController.php:51` |
| 31 | `admin.rep.visits.destroy` | delete_representative_visit | delete_representative_visit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/VisitController.php:31` |
| 31 | `admin.rep.visits.index` | view_representative_visits | view_representative_visits | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/VisitController.php:30` |
| 31 | `admin.rep.visits.note` | view_representative_visits | (route absent) | (route absent) | **yes** | `routes/shop-admin.php:252` |
| 31 | `admin.rep.visits.rep-show` | view_representative_visits | view_representative_visits | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/VisitController.php:30` |
| 31 | `admin.rep.visits.show` | view_representative_visits | view_representative_visits | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/VisitController.php:30` |
| 31 | `bulk-visit-delete` | delete_representative_visit | delete_representative_visit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/VisitController.php:31` |
| 31 | `visits.note` | (no permission) | (no permission) | (no permission) | no | – |
| 32 | `google-map.index` | google_map_setting | google_map_setting | google_map_setting | no | `app/Http/Controllers/Store/Admin/GoogleMapController.php:21` |
| 32 | `google-map.update` | google_map_setting | google_map_setting | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/GoogleMapController.php:21` |
| 33 | `cache.clear` | RaizerSuperAdmin | RaizerSuperAdmin | (no permission; IsAdmin) | no | `routes/shop-admin.php:685` |
| 33 | `shop.admin.dashboard` | admin_dashboard | (no permission; IsAdmin) | (no permission; IsAdmin) | **yes** | `app/Http/Controllers/Store/Admin/DashboardController.php:28` |
| 34 | `bulk-seller-delete` | delete_seller | delete_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:47` |
| 34 | `sellers.approve` | edit_seller | edit_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.approved` | edit_seller | edit_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.ban` | ban_seller | ban_seller | ban_seller | no | `app/Http/Controllers/Store/Admin/SellerController.php:48` |
| 34 | `sellers.create` | edit_seller | edit_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.destroy` | delete_seller | delete_seller | delete_seller | no | `app/Http/Controllers/Store/Admin/SellerController.php:47` |
| 34 | `sellers.edit` | edit_seller | edit_seller | edit_seller | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.index` | view_all_seller | view_all_seller | view_all_seller | no | `app/Http/Controllers/Store/Admin/SellerController.php:43` |
| 34 | `sellers.login` | login_as_seller | login_as_seller | login_as_seller | no | `app/Http/Controllers/Store/Admin/SellerController.php:45` |
| 34 | `sellers.profile_modal` | view_seller_profile | view_seller_profile | view_seller_profile | no | `app/Http/Controllers/Store/Admin/SellerController.php:44` |
| 34 | `sellers.reject` | edit_seller | edit_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.show` | (route absent) | view_all_seller | (no permission; IsAdmin) | **yes** | – |
| 34 | `sellers.show_verification_request` | view_all_seller | view_all_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:43` |
| 34 | `sellers.store` | edit_seller | edit_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.update` | edit_seller | edit_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 34 | `sellers.verification_file` | view_all_seller | view_all_seller | (route absent) | no | `app/Http/Controllers/Store/Admin/SellerController.php:43` |
| 35 | `commissions.pay_to_seller` | pay_to_seller | pay_to_seller | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:30` |
| 35 | `sellers.payment_histories` | seller_payment_history | seller_payment_history | seller_payment_history | no | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:31` |
| 35 | `sellers.payment_history` | seller_payment_history | seller_payment_history | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:31` |
| 35 | `sellers.payment_modal` | pay_to_seller | pay_to_seller | pay_to_seller | no | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:30` |
| 35 | `withdraw_request.message_modal` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_request.payment_modal` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests.create` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests.destroy` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests.edit` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests.index` | view_seller_payout_requests | view_seller_payout_requests | view_seller_payout_requests | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests.show` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests.update` | view_seller_payout_requests | view_seller_payout_requests | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 35 | `withdraw_requests_all` | view_seller_payout_requests | view_seller_payout_requests | view_seller_payout_requests | no | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 36 | `business_settings.seller_commission.activation` | features_activation AND RaizerSuperAdmin | features_activation AND RaizerSuperAdmin | (route absent) | no | `app/Http/Controllers/BusinessSettingsController.php:24`, `routes/shop-admin.php:349` |
| 36 | `business_settings.vendor_commission` | seller_commission_configuration | seller_commission_configuration | seller_commission_configuration | no | `app/Http/Controllers/Store/Admin/SellerCommissionController.php:21` |
| 36 | `business_settings.vendor_commission.update` | seller_commission_configuration | seller_commission_configuration | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerCommissionController.php:21` |
| 36 | `seller_verification_form.index` | seller_verification_form_configuration | seller_verification_form_configuration | seller_verification_form_configuration | no | `app/Http/Controllers/Store/Admin/SellerVerificationFormController.php:21` |
| 36 | `seller_verification_form.update` | seller_verification_form_configuration | seller_verification_form_configuration | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/SellerVerificationFormController.php:21` |
| 37 | `classified_products` | view_classified_products | view_classified_products | view_classified_products | no | `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:24` |
| 37 | `classified_products.destroy` | delete_classified_product | delete_classified_product | delete_classified_product | no | `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:26` |
| 37 | `classified_products.published` | publish_classified_product | publish_classified_product | publish_classified_product | no | `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:25` |
| 38 | `bulk-product-delete` | product_delete | product_delete | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:78` |
| 38 | `products.approved` | product_edit | product_edit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 38 | `products.destroy` | product_delete | product_delete | product_delete | no | `app/Http/Controllers/Store/Admin/ProductController.php:78` |
| 38 | `products.duplicate` | product_duplicate | product_duplicate | product_duplicate | no | `app/Http/Controllers/Store/Admin/ProductController.php:77` |
| 38 | `products.featured` | product_edit | product_edit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 38 | `products.published` | product_edit | product_edit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 38 | `products.seller` | show_seller_products | show_seller_products | show_seller_products | no | `app/Http/Controllers/Store/Admin/ProductController.php:74` |
| 38 | `products.seller.edit` | product_edit | product_edit | product_edit | no | `app/Http/Controllers/Store/Admin/ProductController.php:71` |
| 38 | `products.todays_deal` | product_edit | product_edit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| 38 | `products.update_sale` | product_edit | product_edit | (no permission; IsAdmin) | no | `app/Http/Controllers/Store/Admin/ProductController.php:76` |

Summary: across 165 route rows, the only gate that changed on a route that
existed both before and after §11 is **`shop.admin.dashboard`: none →
`admin_dashboard` (§33)**. Every other row marked "yes" is a route the module
added (with its page's existing permission) or removed. 106 rows differ from
the legacy mount: those changes came from the hardening before the migration
(`8e3f82b6`) and the reconciliation (PR #377), and are analysed in §E.

### D.2 Deliberate gate changes the guide records (UI and route)

| § | Screen | Before | After | Kind | Proof |
| --- | --- | --- | --- | --- | --- |
| 2.2 | Customer archive (sidebar link) | Blade link under `view_all_customers` | `delete_customer`, the route's own | link only; route unchanged since `8e3f82b6` | `resources/js/lib/storeAdminMenu.ts:325` |
| 2.2 | Customer notifications (sidebar link) | Blade link under `view_all_customers` | `add_notification` | link only | `resources/js/lib/storeAdminMenu.ts:331` |
| 2.2 | Brand discount (sidebar link) | Blade link under `set_brand_wise_discount` | `view_all_brands`; the save needs `product_edit` | link only; route `brands_wise_product_discount` gated since `8e3f82b6` | `resources/js/lib/storeAdminMenu.ts:249`, `app/Http/Controllers/Store/Admin/BrandDiscountController.php:28-29` |
| 2.2 | Home Banners (sidebar link) | link outside `/temp` | `edit_website_page`, linking `website.home.edit` | link and route (new route at PR #377) | `resources/js/lib/storeAdminMenu.ts:518`, `app/Http/Controllers/Store/Admin/WebsiteHomeController.php:25` |
| 12 | Classified package "Add" button | Blade `add_classified_package` | `edit_classified_package` (create/store were already edit-gated) | button only | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` |
| 20 | Settings writer `business_settings.update` | any of `general_settings`, `footer_setup`, `website_appearance`, `edit_website_page` (8 names at `8e3f82b6`) wrote any key | retired; typed writers `general_setting.update` (`general_settings`), `website.footer.update` (`footer_setup`), `website.appearance.update` (`website_appearance`) | route | `app/Http/Controllers/Store/Admin/GeneralSettingsController.php:25`, `app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29`, `app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29` |
| 24–25 | Trips edit/status/notes/delete | legacy: ungated | `edit_trip` / `delete_trip` (gated in hardening `5da32748`, kept) | route | `app/Http/Controllers/Store/Admin/TripController.php:58-59` |
| 26 | Trip orders bulk status, invoice number, mark delivered | Blade bulk menu under `delete_order`, delivered button for everyone | `edit_trip`, the routes' own; delete selection keeps `delete_order` | buttons; routes unchanged since `8e3f82b6` | `app/Http/Controllers/Store/Admin/TripOrderController.php:49` |
| 28 | Reviews | Blade list `reviews.index` (`view_product_reviews`) | Inertia `admin.reviews.index`, same permission; `reviews.index` redirects | route moved, gate unchanged | `app/Http/Controllers/Store/Admin/ReviewController.php:26` |
| 29 | Server status | `system_server`, `RaizerSuperAdmin`; sidebar `@can('server_status')` | deleted | route and permission retired | – |
| 33 | Dashboard | no permission | `admin_dashboard` | **route** | `app/Http/Controllers/Store/Admin/DashboardController.php:28` |
| 34 | Seller approval switch | Blade `approve_seller` | `edit_seller`, which the route always required | button only | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| 35 | Seller payouts | Blade buttons | message view `view_seller_payout_requests`, recording money `pay_to_seller` | buttons; routes unchanged | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:30`, `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| 37 | Classified products | `view_classified_products` / `publish_classified_product` / `delete_classified_product` | same | unchanged | `app/Http/Controllers/Store/Admin/ClassifiedProductController.php:24-26` |
| 38 | Seller product list approval | `products.approved`: `product_edit` (legacy: ungated) | `product_edit` | unchanged since `8e3f82b6` | `app/Http/Controllers/Store/Admin/ProductController.php:76` |

### D.3 Permissions whose last enforcement site was deleted (cleanup candidates)

Enforced at legacy mount (`c3f6422e`), enforced nowhere at HEAD. The JSON lists
each one's legacy sites (`legacy_mount_sites_at_c3f6422e`). Deleting rows is an
owner decision and a production data change; this audit proposes none.

- **Retired screens or replaced button names (8):** `server_status` (§29;
  sidebar only), `approve_seller` (→ `edit_seller`), `set_brand_wise_discount`
  (→ `view_all_brands` + `product_edit`), `add_classified_package`
  (→ `edit_classified_package`), `add_color` (→ `edit_color`),
  `add_product_attribute` (→ `edit_product_attribute`),
  `add_product_attribute_values` (→ `edit_product_attribute_value`),
  `assign_delivery_boy_for_orders` (delivery-boy addon, order detail button).
- **Removed addons (42), hidden from the role form by
  `RoleController::REMOVED_ADDON_SECTIONS`
  (`app/Http/Controllers/Store/Admin/RoleController.php:41-53`):**
  `add_auction_product`, `add_delivery_boy`, `add_wholesale_product`, `affiliate_configurations`, `affiliate_registration_form_config`, `african_pg_configuration`, `african_pg_credentials_configuration`, `asian_payment_gateway_configuration`, `club_point_configurations`, `collected_histories_from_delivery_boy`, `delivery_boy_configuration`, `delivery_boy_payment_history`, `order_cancle_request_by_delivery_boy`, `otp_configurations`, `pos_configuration`, `pos_manager`, `refund_request_configuration`, `send_bulk_sms`, `set_club_points`, `sms_providers_configurations`, `sms_templates`, `view_affiliate_logs`, `view_affiliate_users`, `view_affiliate_withdraw_requests`, `view_all_auction_products`, `view_all_delivery_boy`, `view_all_manual_payment_methods`, `view_all_offline_customer_package_payments`, `view_all_offline_seller_package_payments`, `view_all_offline_wallet_recharges`, `view_all_referral_users`, `view_all_seller_packages`, `view_all_wholesale_products`, `view_approved_refund_requests`, `view_auction_product_orders`, `view_inhouse_auction_products`, `view_inhouse_wholesale_products`, `view_refund_requests`, `view_rejected_refund_requests`, `view_seller_auction_products`, `view_sellers_wholesale_products`, `view_users_club_points`.
- **Defined, never enforced:** `delete_truck` (§C.2).

Saving a role in the new role form syncs only the permissions it shows
(`app/Http/Controllers/Store/Admin/RoleController.php:135`, `:169`), so editing a role silently drops any
removed-addon permission it held. Harmless for dead names, but it is a data
change the owner should expect.

## E. Role-grant impact analysis

**Assumption:** production roles hold only the permissions they held before the
migration, i.e. the legacy store's grants, configured against the legacy gates
(`c3f6422e`). No production role name is assumed beyond the seeded
`Super Admin` and `Clerk`. **Super Admin** passes every permission gate through
`Gate::before` (`app/Providers/AuthServiceProvider.php:26-27`), so its verdict is
NO CHANGE everywhere except routes behind `RaizerSuperAdmin`, which also needs
the vendor flag. "Roles holding neither" covers staff and accountant accounts
whose roles hold neither the old nor the new permission, plus back-office
accounts with no role (Q7).

"Introduced" names where the change landed: **H** = legacy hardening
(`c3f6422e`→`8e3f82b6`), **R** = reconciliation PR #377
(`8e3f82b6`→`018dd180`), **M** = §§11–39 (`018dd180`→HEAD).

| # | Gate change (routes) | Introduced | Super Admin | Roles holding the old permission | Roles holding neither | Proof |
| --- | --- | --- | --- | --- | --- | --- |
| E1 | Dashboard `shop.admin.dashboard`: none → `admin_dashboard` | M (§33) | NO CHANGE | – (no old permission; holders of `admin_dashboard`: NO CHANGE) | **LOSES ACCESS**, and the post-login redirect lands on a 403 | `app/Http/Controllers/Store/Admin/DashboardController.php:28`; `app/Http/Controllers/Auth/LoginController.php:216-217` |
| E2 | Logistics, 35 routes (`trips.*`, `trucks.*`, `drivers.*`, `checkpoints.*`, `trips.orders.*`, map picker, vehicle lookups): none → `view_all_trips`, `add_trip`, `edit_trip`, `delete_trip`, `*_truck`, `*_driver` | H (`5da32748`), names defined R | NO CHANGE | – | **LOSES ACCESS** (names are brand-new, no role can hold them yet) | `app/Http/Controllers/Store/Admin/TripController.php:56-59`; `app/Http/Controllers/Store/Admin/TripOrderController.php:48-49`; `app/Http/Controllers/Store/Admin/TripCheckpointController.php:40-41`; `app/Http/Controllers/Store/Admin/TripMapController.php:51`; `app/Http/Controllers/Store/Admin/TruckController.php:36-39`; `app/Http/Controllers/Store/Admin/DriverController.php:27-30`; `routes/shop-admin.php:305-306` |
| E3 | Representative packages, payments, visits (17 routes): none → `view_/add_/edit_/delete_/assign_representative_package(s)`, `view_representative_payments`, `approve_representative_payment`, `export_representative_payment`, `view_representative_visits`, `delete_representative_visit` | R (`99a14828`) | NO CHANGE | – | **LOSES ACCESS** (brand-new) | `app/Http/Controllers/Store/Admin/RepresentativePackageController.php:30-34`; `app/Http/Controllers/Store/Admin/RepresentativePaymentController.php:28-30`; `app/Http/Controllers/Store/Admin/VisitController.php:30-31`; `routes/shop-admin.php:252` |
| E4 | Media library and uploads (`uploaded-files.*`, `all-file`, bulk delete): none → `view_uploads`, `upload_files`, `delete_uploads`, `delete_all_uploads` | R (`6c21e5a4`) | NO CHANGE | – | **LOSES ACCESS** (brand-new); also loses uploading from product/blog/website forms and referencing others' files | `app/Http/Controllers/Store/Admin/UploadController.php:30-33`; `app/Http/Requests/Concerns/ValidatesStoreUploadReference.php:93` |
| E5 | Order entry (`feat-order.*`, `new.feat-order.trip.*`, `orders.search-options`): none → `add_order` / `edit_order` | R (`3bece94c`) | NO CHANGE | – | **LOSES ACCESS** (brand-new) | `app/Http/Controllers/Store/Admin/OrderEntryController.php:32-34` |
| E6 | Offers catalogue (`offers.index`, `offers.store`, `offers.destroy`, download): none → `view_/add_/delete_offers_catalog` | R (`29ada8cd`) | NO CHANGE | – | **LOSES ACCESS** (brand-new) | `app/Http/Controllers/Store/Admin/OffersCatalogController.php:28-30` |
| E7 | Customer editing, 20 routes (`customers.create/store/edit/update/verify/important/not_important/change_activity_status/update_customer_notes/sendAgreement`, `change_password`, `update_password`, `representatives.create/store`, `admin.customers.addresses.*`, `admin.addresses.*`): legacy none (only `customers.index` gated) → H `view_all_customers` → R `edit_customer` | H then R | NO CHANGE | `view_all_customers` without `edit_customer`: **LOSES ACCESS** (customer list stays) | **LOSES ACCESS** vs legacy (could edit through ungated routes); NO CHANGE vs `8e3f82b6` | `app/Http/Controllers/Store/Admin/CustomerController.php:47-48`; `app/Http/Controllers/Store/Admin/AddressController.php:26` |
| E8 | Order details and writes (`orders.details`, `*.show`, `orders.update_delivery_status`, `update_payment_status`, `update_tracking_code`, `update_manager_notes`, `order.item.edit_price`): legacy none → H any of `view_all_orders\|view_inhouse_orders\|view_seller_orders\|view_pickup_point_orders` → R `view_order_details`, `update_order_delivery_status`, `update_order_payment_status`, `update_order_tracking`*, `update_order_notes`*, `edit_order_price`* (*brand-new) | H then R | NO CHANGE | list-permission holders without the new names: **LOSES ACCESS** to details and each write | **LOSES ACCESS** vs legacy | `app/Http/Controllers/Store/Admin/OrderController.php:36-43` |
| E9 | Support replies: `support_ticket.admin_store` `view_all_support_tickets` → `reply_to_support_tickets`; `product_query.reply` `view_all_product_queries` → `reply_to_product_queries`; `conversations.admin_reply` new, `reply_to_product_conversations` | R | NO CHANGE | view-only holders: **LOSES ACCESS** to replying | NO CHANGE (no access before or after) | `app/Http/Controllers/Store/Admin/SupportTicketController.php:27`; `app/Http/Controllers/Store/Admin/ProductQueryController.php:28`; `app/Http/Controllers/Store/Admin/ConversationController.php:27` |
| E10 | SMTP test `test.smtp`: legacy none → H `send_newsletter` → R `send_newsletter` AND `smtp_settings` | H then R | NO CHANGE | `send_newsletter` without `smtp_settings`: **LOSES ACCESS** | **LOSES ACCESS** vs legacy | `app/Http/Controllers/Store/Admin/SettingsController.php:108,118` |
| E11 | Settings writers: legacy per-page updates ungated (`facebook_chat.update`, `google_analytics.update`, `payment_method.update`, …) and the any-of `business_settings.update` → each page's own permission; the generic writer retired | H, R, M (§§17, 20) | NO CHANGE | holders of one settings permission: **LOSES ACCESS** to writing other pages' keys (the #391 fix) | **LOSES ACCESS** to the ungated writes | `app/Http/Controllers/Store/Admin/SettingsController.php:107-118`; `app/Http/Controllers/Store/Admin/GeneralSettingsController.php:25`; `app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29`; `app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29` |
| E12 | Routes that legacy left ungated now share their page's permission (about 120 routes, mostly store/update/status/bulk writes plus a few secondary reads: brands, categories, colors, attributes, blog, coupons, flash deals, popups, languages, currencies, taxes, zones, states, cities, carriers, pickup points, staff, roles, sellers, digital/physical products, …) | H | NO CHANGE | holders of the page permission: NO CHANGE | **LOSES ACCESS** to posting those writes (they never had the page) | e.g. `app/Http/Controllers/Store/Admin/BrandController.php:39-40`; `app/Http/Controllers/Store/Admin/RoleController.php:63-64`; `app/Http/Controllers/Store/Admin/StaffController.php:39-40`; `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| E13 | Offer deals and notifications: none → `view_all_offer_deals`, `add_/edit_/delete_offer_deal`, `add_notification`, `delete_notification`; campaign-product lookups any of the add/edit offer/flash names | H (`da2d49de`) | NO CHANGE | holders: NO CHANGE | **LOSES ACCESS** | `app/Http/Controllers/Store/Admin/OfferController.php:28`; `app/Http/Controllers/Store/Admin/CustomerNotificationController.php:27-28`; `app/Http/Controllers/Store/Admin/CampaignProductController.php:19` |
| E14 | Colors, attributes, attribute values "add": legacy Blade form under `add_color` / `add_product_attribute` / `add_product_attribute_values`, route ungated → `edit_color` / `edit_product_attribute` / `edit_product_attribute_value` | H | NO CHANGE | `add_*` without `edit_*`: **LOSES ACCESS** to adding | **LOSES ACCESS** to the ungated store route | `app/Http/Controllers/Store/Admin/ColorController.php:26`; `app/Http/Controllers/Store/Admin/AttributeController.php:29`; `app/Http/Controllers/Store/Admin/AttributeValueController.php:27` |
| E15 | Classified package create: Blade button `add_classified_package`, routes ungated → `edit_classified_package` | H, UI M (§12) | NO CHANGE | `add_classified_package` without `edit_classified_package`: **LOSES ACCESS** | **LOSES ACCESS** | `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36` |
| E16 | Seller approval: Blade `approve_seller`, routes ungated → `edit_seller` (`sellers.approve`, `sellers.reject`, `sellers.approved`, create/store/update) | H, UI M (§34) | NO CHANGE | `approve_seller` without `edit_seller`: **LOSES ACCESS** | **LOSES ACCESS** | `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| E17 | Brand discount: page none → `view_all_brands`; `set_product_discount` none → `product_edit`; sidebar `set_brand_wise_discount` → `view_all_brands` | H, UI §2.2 | NO CHANGE | `set_brand_wise_discount` without `view_all_brands`+`product_edit`: **LOSES ACCESS** | **LOSES ACCESS** | `app/Http/Controllers/Store/Admin/BrandDiscountController.php:28-29` |
| E18 | Seller money: `commissions.pay_to_seller` none → `pay_to_seller`; `sellers.payment_history` none → `seller_payment_history`; withdraw-request routes none → `view_seller_payout_requests` | H | NO CHANGE | holders: NO CHANGE | **LOSES ACCESS** | `app/Http/Controllers/Store/Admin/SellerPaymentController.php:30-31`; `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26` |
| E19 | Feature activation write `business_settings.update.activation`: `RaizerSuperAdmin` → `RaizerSuperAdmin` AND `features_activation` | R | NO CHANGE if also vendor admin; a Super Admin without the vendor flag had and has no access | vendor admin (not Super Admin) without `features_activation`: **LOSES ACCESS** | NO CHANGE | `app/Http/Controllers/Store/Admin/SettingsController.php:107,117` |
| E20 | Cache clear `cache.clear`: none → `RaizerSuperAdmin` | H | **LOSES ACCESS** unless also the vendor admin | – | **LOSES ACCESS** | `routes/shop-admin.php:685` |
| E21 | Product form helpers: `products.add-more-choice-option` none → `add_new_product\|product_edit`; `order_configuration.product-search` none → `order_configuration` | R | NO CHANGE | holders: NO CHANGE | **LOSES ACCESS** (only reachable from forms they cannot open anyway) | `app/Http/Controllers/Store/Admin/ProductSearchController.php:21-22` |
| E22 | Representative customer creation `store_customer` (customer area, `is_rep` check): legacy none → H `view_all_customers` → R none | H then R | – (customer route) | – | vs `8e3f82b6`: **GAINS ACCESS** for representatives; vs legacy: NO CHANGE | `routes/shop-web.php:328`; `app/Http/Controllers/CustomerController.php:28` |
| E23 | Sidebar-only changes (§2.2: archive, notifications, brand discount, Home Banners) and button-only changes (§§12, 26, 34, 35) | M | NO CHANGE | NO CHANGE (links and buttons now follow the route's permission; a link no longer leads to a 403) | NO CHANGE | `resources/js/lib/storeAdminMenu.ts:249,325,331,518` |
| E24 | Server status `system_server` deleted | M (§29) | (vendor admin) **LOSES ACCESS**: the page no longer exists | – | NO CHANGE | – |
| E25 | Everything else in §D.1 (unchanged gates; new routes carry their page's permission) | – | NO CHANGE | NO CHANGE | NO CHANGE | §D.1 |

## F. Proposed grant plan (for the owner to approve; NOT executed)

Role names below are placeholders; replace each with the production role(s) that
Q4/Q6 show. "Recommended default" is this audit's suggestion, not a decision.

| Role placeholder | Permission(s) | Reason | Risk | Recommended default |
| --- | --- | --- | --- | --- |
| every role that signs in to the back office | `admin_dashboard` | post-login landing page (E1) | low: read-only aggregates | **grant** (or change the login redirect; owner decision) |
| `<logistics roles>` | `view_all_trips`, `add_trip`, `edit_trip`, `view_all_trucks`, `add_truck`, `edit_truck`, `view_all_drivers`, `add_driver`, `edit_driver` | E2 | medium: edits trips and order delivery status (`trips.orders.*`) | **grant** to roles that run logistics today |
| `<logistics roles>` | `delete_trip`, `delete_driver` | E2 | delete | **owner decision** |
| – | `delete_truck` | no route uses it | none | **do not grant** (dead; §C.2) |
| `<representative managers>` | `view_representative_packages`, `view_representative_payments`, `view_representative_visits`, `export_representative_payment` | E3 | low (reads, export) | **grant** |
| `<representative managers>` | `add_representative_package`, `edit_representative_package`, `assign_representative_package` | E3 | medium: changes representatives' discount percentage | **owner decision** |
| `<representative managers>` | `approve_representative_payment` | E3 | **money** (accept/reject payments) | **owner decision** |
| `<representative managers>` | `delete_representative_package`, `delete_representative_visit` | E3 | delete | **owner decision** |
| every role that edits products, blog, website pages or trucks | `view_uploads`, `upload_files` | E4; forms upload and pick files through them | medium: `view_uploads` lets the holder see and reference every account's files | **grant** `upload_files`; **owner decision** on `view_uploads` |
| `<media managers>` | `delete_uploads` | E4 | delete | **owner decision** |
| – | `delete_all_uploads` | E4 | destructive bulk delete of the whole library | **owner decision** (default: do not grant) |
| `<order entry roles>` | `add_order`, `edit_order` | E5 | medium: creates/edits orders with prices | **grant** to roles that enter orders today |
| `<order handlers>` (holders of a `view_*_orders` permission, Q6) | `view_order_details`, `update_order_delivery_status`, `update_order_tracking`, `update_order_notes` | E8 | medium | **grant** where the role handled orders |
| `<order handlers>` | `update_order_payment_status`, `edit_order_price` | E8 | **money** (payment state, line prices) | **owner decision** |
| `<catalogue roles>` | `view_offers_catalog`, `add_offers_catalog` | E6 | low | **grant** |
| `<catalogue roles>` | `delete_offers_catalog` | E6 | delete | **owner decision** |
| holders of `view_all_customers` (Q6) | `edit_customer` | E7 | medium: edits customer accounts, passwords, verification, addresses | **owner decision** (they could edit before; confirm intent) |
| `<customer admins>` | `delete_customer`, `ban_customer` | archive/restore/delete, ban | **ban/delete** | **owner decision** |
| `<customer admins>` | `login_as_customer` | impersonation | **impersonation** | **owner decision** |
| `<support roles>` (holders of `view_all_support_tickets` / `view_all_product_queries` / `view_all_product_conversations`) | `reply_to_support_tickets`, `reply_to_product_queries`, `reply_to_product_conversations` | E9 | low–medium: writes to customers | **grant** to roles that answered before |
| holders of `send_newsletter` | `smtp_settings` | E10 (the SMTP test email) | low: the SMTP page here has no writer (its section lists no keys); the test sends one email | **owner decision** |
| `<seller managers>` | `edit_seller` (approve/reject, create/update) | E16 | medium: seller verification and accounts | **owner decision** |
| `<seller managers>` | `ban_seller`, `delete_seller` | | **ban/delete** | **owner decision** |
| `<seller managers>` | `login_as_seller` | | **impersonation** | **owner decision** |
| `<finance>` | `pay_to_seller`, `seller_payment_history`, `view_seller_payout_requests`, `seller_commission_configuration` | E18 | **money** | **owner decision** |
| `<settings roles>` | `general_settings`, `footer_setup`, `website_appearance`, `header_setup`, `edit_website_page`, `order_configuration`, `google_map_setting`, `currency_setup`, `features_activation`, `smtp_settings`, `payment_methods_configurations`, the other `*_configuration`/`*_setting` names | E11 | **settings writers** | **owner decision** |
| `<staff admins>` | `edit_staff_role`, `add_staff_role`, `edit_staff`, `add_staff`, `delete_staff`, `delete_staff_role` | role/staff management | **privilege escalation** (§G-3) | **owner decision**; default: Super Admin only |
| holders of `add_color` / `add_product_attribute` / `add_product_attribute_values` / `add_classified_package` / `approve_seller` / `set_brand_wise_discount` (Q6) | the live gate (`edit_color`, `edit_product_attribute`, `edit_product_attribute_value`, `edit_classified_package`, `edit_seller`, `view_all_brands`+`product_edit`) | E14–E17 | low for catalogue names; `edit_seller` and `product_edit` medium | **grant** catalogue names; **owner decision** for `edit_seller`, `product_edit` |
| – | removed-addon and retired names (§D.3) | cleanup | deleting rows is irreversible | **owner decision** (default: leave; they gate nothing) |

**Recommended way to apply it later (not written here):** an artisan command, for
example `store:grant-permissions --role=<name> --permission=<name> ... --dry-run`,
that takes role names as input (never ids, never "Super Admin"), refuses unknown
roles or permissions instead of creating them, works only on guard `store`,
prints the before/after per role, defaults to `--dry-run`, and calls
`forgetCachedPermissions()` once at the end. It suits a decision the owner makes
per role from production data. If the owner prefers code-reviewed data, use one
explicit, idempotent migration with a literal role→permission list
(`insertOrIgnore` into `wms_role_has_permissions` by looked-up ids, guard
`store` only, no-op `down()`, cache flush) reviewed in its own PR. Either way:
Super Admin needs no grant, and both should land before or with the deploy that
makes these gates live.

## G. Security observations (report only; nothing fixed)

| # | Severity | Observation | Proof |
| --- | --- | --- | --- |
| G-1 | **Medium** | `products.fetch` (POST) has no permission: any admin, staff or accountant account can queue a full `import:all-products-v2` catalogue import, which writes the WMS-owned `products` table (one per 30 minutes, by the controller's own lock). Only the Vue button is gated (`add_new_product`). `LegacyAdminPermissionsTest` lists it as a "read-only AJAX helper", which it is not. | `routes/shop-admin.php:142`; `app/Http/Controllers/ProductController.php:47,75-83`; `app/Console/Commands/CheckForCommands.php:72`; `resources/js/pages/Store/Admin/Product/Products/Index.vue:234`; `tests/Feature/LegacyAdminPermissionsTest.php:45` |
| G-2 | **Medium** (deploy) | 94 routes need permissions no role holds (E1–E6); the admin dashboard (post-login) needs `admin_dashboard`. Not a vulnerability, an availability risk on deploy. | §E |
| G-3 | **Medium** | Privilege escalation inside staff: `edit_staff_role` can grant any store permission to any non-Super Admin role, including the editor's own; `edit_staff` can change the editor's own role (only `destroy` refuses one's own account). | `app/Http/Controllers/Store/Admin/RoleController.php:135`; `app/Http/Requests/Concerns/ValidatesStoreRole.php:25`; `app/Http/Controllers/Store/Admin/StaffController.php:107,127,135` |
| G-4 | Low | Writes gated by a read permission: `admin.rep.visits.note` (PUT) by `view_representative_visits` (the write only touches the caller's own visits); the withdraw-request `create/edit/update/destroy` resource routes by `view_seller_payout_requests` (they redirect without writing); the `withdraw_request.payment_modal` read by the same. | `routes/shop-admin.php:252`; `app/Http/Controllers/Store/Admin/VisitController.php:107`; `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:26,64-87` |
| G-5 | Low | Creation gated by an edit permission (no add permission exists or the legacy add name is dead): `customers.create/store`, `representatives.create/store` (`edit_customer`), `colors.store` (`edit_color`), `attributes.store` (`edit_product_attribute`), `store-attribute-value` (`edit_product_attribute_value`), `customer_packages.create/store` (`edit_classified_package`), `sellers.create/store` (`edit_seller`). | `app/Http/Controllers/Store/Admin/CustomerController.php:48`; `app/Http/Controllers/Store/Admin/ColorController.php:26`; `app/Http/Controllers/Store/Admin/AttributeController.php:29`; `app/Http/Controllers/Store/Admin/AttributeValueController.php:27`; `app/Http/Controllers/Store/Admin/CustomerPackageController.php:36`; `app/Http/Controllers/Store/Admin/SellerController.php:46` |
| G-6 | Low | Approval gated by a broad edit permission: seller product approval `products.approved` needs only `product_edit`, the same permission as editing in-house products. | `app/Http/Controllers/Store/Admin/ProductController.php:76` |
| G-7 | Low | Store-permission routes outside the admin group, so without `IsAdmin` (no user-type or ban check): `subscribers.index`/`subscribers.destroy` (no `auth` either; spatie refuses a guest with 403), `conversations.destroy` and its unnamed DELETE twin, `product-queries.index/show`, `reviews.index` (redirect). A banned staff account holding the permission passes them. | `routes/shop-web.php:242,361,366`; `app/Http/Controllers/SubscriberController.php:24-25`; `app/Http/Controllers/ConversationController.php:30`; `app/Http/Controllers/ProductQueryController.php:22` |
| G-8 | Low | Ungated `/temp/admin` reads available to any admin/staff/accountant: `products.get-details/{id}` (unit price, material id of any product), `products.search`, `products.get_products_by_subcategory`, `products.sku_combination`; `admin.all-notification` (own notifications; a GET that marks them read); `profile.*` (own account). `env_key_update` refuses every request. | `routes/shop-admin.php:141-145,344-345,353,683`; `app/Http/Controllers/ProductController.php:121-127`; `app/Http/Controllers/Store/Admin/NotificationController.php:27` |
| G-9 | Low | UI/backend mismatch on the vendor flag: the shared `isRaizerAdmin` is `raizer_admin` alone, the `RaizerSuperAdmin` middleware also requires `user_type = 'admin'`. A non-admin account with the flag would see vendor-only menu entries that answer 403. | `app/Http/Middleware/HandleInertiaRequests.php:139`; `app/Http/Middleware/RaizerSuperAdmin.php:27` |
| G-10 | Low (ops) | `add_order_entry_permissions` does not flush spatie's permission cache, unlike the other definition migrations; until the 24 h cache expires or another write flushes it, its five rows can be invisible to permission checks. | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10-12`; `config/permission.php:206-227` |
| G-11 | Info | `customer_products.published` sits in the customer group (`IsCustomer`) but needs the staff permission `publish_classified_product`, so in practice nobody reaches it (a Super Admin is not a customer). | `routes/shop-web.php:318`; `app/Http/Controllers/CustomerProductController.php:30` |
| G-12 | Info | `HandleInertiaRequests` shares the signed-in user's full permission-name list with every store page. Not sensitive, but it is the full grant list in the page props. | `app/Http/Middleware/HandleInertiaRequests.php:137` |
| G-13 | Info | Pages check permissions their own imports do not need, because the gated action sits in a child dialog or the route itself (`admin_dashboard`, `view_order_details`, `pay_to_seller`, `seller_payment_history`, `view_seller_profile`, `reply_to_support_tickets`): verified to match the routes those children call. No Vue gate names a permission the backend does not enforce, and no menu link's gate disagrees with its route. | `resources/js/pages/Store/Admin/Sellers/Sellers/Index.vue`, `resources/js/pages/Store/Admin/Support/Tickets/Show.vue` |

## H. Verification and limits

This is a documentation-only change; application tests, `pre_push_check.py`
and CI were not run (the shipping PR carries `[skip ci]`, so PR CI, SAST and
deploy did not run either).

**Checks run (all passed):**

| Check | Result |
| --- | --- |
| Every `path:line` citation resolves (scripted: file exists, every cited line or range end within the file; `<sha>:path:line` read with `git show`) | report **866**, JSON **1,540**, SQL **54** resolved; **0** failed |
| Semantic check: every enforcement and definition site in the JSON shows its permission name on the cited line (or the next 10, for multi-line `@canany`/arrays); `RaizerSuperAdmin` sites show the middleware | **1,386** sites, **0** mismatches. This check found and fixed one real error during the audit: definition lines for sectioned migration arrays were offset |
| Manual spot-check of hand-written citations (§§0, 1, A, B, D.2, E, F, G): each read and compared with its claim | 127 narrative citations printed and read, including the 30 below; plus the short `(:NN)` references of the §B migration table and `tests/Pest.php:240-246,255-261,270-273` |
| `python3 -m json.tool .ai/role-grants-audit-20261020.json` | parses |
| `grep -nE "\b(INSERT\|UPDATE\|DELETE\|DROP\|ALTER\|TRUNCATE\|CREATE\|REPLACE\|GRANT)\b" .ai/role-grants-audit-20261020.sql` | empty (exit 1); a second pass with comments and string literals stripped, case-insensitive, is also empty |
| SQL statements run on a scratch SQLite copy of the migrated schema (MySQL-only functions translated for the check), first as migrated + seeded, then with synthetic rows (a role holding only `view_all_customers`, a role-less staff account, a foreign `model_type` pivot, a stray permission, a `web`-guard role) | all 14 statements execute; Q2 lists the 144 undefined names, Q6 finds the old-permission role, Q7 the role-less staff, Q9a the foreign pivot; `web` rows never appear |
| `git diff --cached --check` | clean |

**Spot-checked citations (30, across sections):**

| § | Citation | What the line shows |
| --- | --- | --- |
| 0/E1 | `app/Http/Controllers/Auth/LoginController.php:216-217` | admin/staff redirected to `shop.admin.dashboard` |
| 0/E1 | `app/Http/Controllers/Store/Admin/DashboardController.php:28` | `permission:admin_dashboard` on `index` |
| 0/G-1 | `routes/shop-admin.php:142` | `products.fetch` with no middleware |
| 0/G-1 | `app/Console/Commands/CheckForCommands.php:72` | calls `import:all-products-v2` |
| 0/G-1 | `resources/js/pages/Store/Admin/Product/Products/Index.vue:234` | fetch button under `access.can('add_new_product')` |
| 0/E8 | `app/Http/Controllers/Store/Admin/OrderController.php:36-43` | any-of list gate, then the six per-action gates |
| 0/G-3 | `app/Http/Controllers/Store/Admin/RoleController.php:135` | `syncPermissions` of the checked ids |
| 0/G-3 | `app/Http/Controllers/Store/Admin/StaffController.php:107` | update refuses only Super Admin rows |
| 1 | `app/Providers/AuthServiceProvider.php:26-27` | `Gate::before` → `hasRole(Role::SUPER_ADMIN, 'store')` |
| 1 | `app/Http/Middleware/RaizerSuperAdmin.php:27` | `user_type == 'admin'` and `raizer_admin` |
| 1 | `app/Models/StoreUser.php:70` | `$guard_name = 'store'` |
| A | `app/Http/Middleware/HandleInertiaRequests.php:138-139` | `isSuperAdmin`, `isRaizerAdmin` props |
| A | `resources/js/lib/storeAccess.ts:21-23` | `can()` true for Super Admin or a held name |
| A | `app/Http/Middleware/IsAdmin.php:22` | admits admin, staff, accountant |
| A | `app/Http/Controllers/Store/Admin/OrderController.php:50-54` | route-name → permission `match`, `abort_unless` |
| A | `app/Http/Controllers/Store/Admin/TripMapController.php:126` | `Gate::authorize('edit_trip')` |
| B | `database/migrations/2026_10_10_035933_add_order_entry_permissions.php:10-12` | `insertOrIgnore` loop, no cache flush |
| B | `database/migrations/2026_10_09_230302_add_store_logistics_permissions.php:16` | the `trucks` names incl. `delete_truck` |
| B | `database/seeders/StoreRoleSeeder.php:18-19` | `Super Admin`, `Clerk` |
| D.2 | `resources/js/lib/storeAdminMenu.ts:325` | archive link `delete_customer` |
| D.2 | `app/Http/Controllers/Store/Admin/BrandDiscountController.php:28-29` | `view_all_brands` index, `product_edit` update |
| D.2 | `app/Http/Controllers/Store/Admin/TripOrderController.php:49` | `edit_trip` on bulk status, invoice, delivered |
| D.3 | `app/Http/Controllers/Store/Admin/RoleController.php:41-53` | `REMOVED_ADDON_SECTIONS` |
| E7 | `app/Http/Controllers/Store/Admin/CustomerController.php:47-48` | `view_all_customers` reads, `edit_customer` writes |
| E4 | `app/Http/Controllers/Store/Admin/UploadController.php:30-33` | the four upload names |
| E22 | `app/Http/Controllers/CustomerController.php:28` | `abort_unless(auth()->user()->is_rep, 403)` |
| G-4 | `app/Http/Controllers/Store/Admin/VisitController.php:107` | writes only when `rep_id` is the caller |
| G-4 | `app/Http/Controllers/Store/Admin/SellerWithdrawRequestController.php:64-87` | resource actions redirect without writing |
| G-7 | `routes/shop-web.php:242` | `Route::resource('subscribers', …)` outside the admin group |
| G-10 | `config/permission.php:206-227` | 24 h permission cache |

**Not verified, and not claimed:**

- **Production data.** No production database was available. Which of the 181
  enforced names exist as `store`-guard rows, which roles exist and what they
  hold, member counts, whether legacy rows were moved to guard `store` with
  `model_type` `App\Models\StoreUser`, and so how many people each LOSES ACCESS
  verdict really affects: all unknown. The SQL file answers them.
- **"In the legacy vocabulary"** means "referenced by the legacy store's code
  at `c3f6422e`", which strongly suggests a legacy permission row but does not
  prove the row exists in production.
- **Runtime behaviour** was established by reading code and `route:list`, not by
  issuing requests as staff accounts. The baseline route lists were produced by
  booting old commits against today's `vendor/`; they booted cleanly, but a
  baseline whose middleware depended on a since-changed package could differ in
  a way this method would not show.
- **Grant decisions** in §F are proposals. Nothing was granted, revoked, created
  or deleted anywhere.

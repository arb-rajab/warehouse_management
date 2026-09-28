---
paths:
  - composer.json
  - composer.lock
  - package.json
  - bootstrap/app.php
  - bootstrap/providers.php
  - 'config/app.php,config/auth.php,config/cache.php,config/services.php,config/filesystems.php,config/permission.php,config/session.php,config/store.php,config/secure-headers.php'
  - 'routes/web.php,routes/api.php,routes/console.php,routes/store/**'
  - 'app/Models/User.php,app/Models/Product.php,app/Models/Upload.php,app/Models/PersonalAccessToken.php'
  - 'app/Models/Store/**,app/Http/Controllers/Store/**'
  - app/Http/Controllers/Controller.php
  - app/Http/Controllers/WelcomeController.php
  - app/Providers/AppServiceProvider.php
  - app/Console/Commands/SyncProductsCommand.php
  - 'database/migrations/**,database/schema/**'
  - 'public/**,resources/views/**,lang/**'
  - 'tests/Feature/RootRedirectTest.php,tests/Feature/SharedDatabaseTableNamesTest.php'
---

# Merging the legacy `albaraka-holland` store into this app

The goal is **one application**: one `composer.json`/`vendor`, one database,
one `.env`. No second vendor directory, no second database, no gateway or proxy
in front of two apps. This file is the audit that scopes that work. It was
built from static reads of `github.com/arb-rajab/albaraka-holland` and this
repo. The audit was first run on commit `a120282` and re-checked on `f31f264`
("Second Commit(after changes)", 2026-09-27); section 0.6 lists what that commit
changed. Nothing was installed and no SQL was run. Package versions come from
Packagist metadata (`repo.packagist.org/p2/*.json`) as of 2026-09-24.

Read the whole of section 0 before you start any phase. The other sections are
reference material for the phase you are working on.

## 0. Blocking findings. Handle these first.

### 0.1 The legacy repo still contains a live webshell

The legacy repo is a snapshot of a server document root. It contains files that
an attacker dropped there:

| File | What it is |
| --- | --- |
| `public/logo.php` | Encrypted-eval webshell. It AES-decrypts a payload keyed on the session id, then `@eval()`s it. |
| `public/demo.php` | Recon probe that deletes itself. It is `eval(base64_decode(...))` and prints the PHP version and file path before unlinking. |
| ~~`tn.php`, `public/tn.php`~~ | **Removed in `f31f264`.** Tiny File Manager 2.5.3, a full web file manager with one bcrypt admin user, whose plaintext password sat in a comment next to the hash. Deleting it from the repo does not remove it from the server, and the password must be treated as leaked (it is still in git history). |
| `public/addons/*.zip` (6 files) | The delivery mechanism. Each zip is a fake "addon" (`unique_identifier` `pm_…`, `sql/update.sql` = `SELECT 1;`) whose `config.json` copies `demo.php`/`logo.php` into `public/`. |

This is how they got there: `AddonController::store` (`routes/admin.php:606`,
`Route::resource('addons', …)`) accepts an uploaded zip, extracts it, copies any
file its `config.json` names to any path, and runs its SQL. That is remote code
execution by design for anyone holding an admin session. `Storage::disk('local')`
has `root => public_path()`, which is why the uploaded zips ended up web-served
under `public/addons/`.

`f31f264` removed only the file manager. The upgrade branch
(`claude/albaraka-laravel-13-upgrade-7b5954`, commit `52e0de5`) removes
`public/logo.php`, `public/demo.php`, the six `public/addons/*.zip` files,
`public/logs/` and `AddonController`. `main` still has all of them until
that branch merges.

Phase 1 found two more entry points of the same kind. Both are deleted on
that branch, and the production server still has them:

- **`UpdateController` (`POST /update`).** The route was registered outside
  the `auth`/`admin` group, so any visitor holding a CSRF token (every page
  hands one out) could name any row of the `uploads` table. The controller
  extracts that zip into the project root. `zip` is an allowed
  `AizUploadController` type, and a customer account is enough to get an
  upload row (anyone can register; a guest's upload fails before the row is
  saved). That is remote code execution for any self-registered customer.
- **`DemoController`.** Eight unauthenticated GET routes:
  - `cron_1` drops every table and re-imports the demo SQL; `cron_2`
    replaces the uploads. Both run only under `DEMO_MODE=On`.
  - Six vendor data migrations run for anyone, each iterating every product
    or order with a 2 GB memory limit: `convert_assets`, `convert_category`,
    `convert_tax`, `insert_product_variant_forcefully`,
    `update_seller_id_in_orders/{min}/{max}` and `migrate_attribute_values`.

Check the production access logs for `POST /update` and those GET paths.

The following is outside this repo's scope. Tell the owner, because it cannot
wait for the merge:
- Treat the production store server as compromised.
- Rotate the DB credentials, `APP_KEY`, `OTAJER_TOKEN`, the FCM service account
  and `config('app.wms_token')`.
- Audit the `addons` table and the admin users.

For the merge, **never copy these into this repo**: `public/demo.php`, `public/logo.php`, `public/addons/`, `public/logs/`,
`AddonController`, `UpdateController`, `InstallController` or `DemoController`
and their views and routes, `routes/install.php`, `routes/update.php`, or the
`update`/`installation` view directories.

### 0.2 The legacy document root is the project root

`index.php`, `server.php` and `.htaccess` sit at the repo root. The `.htaccess`
serves any existing file as-is. `static_asset()` and `my_asset()`
(`app/Http/Helpers.php:1003-1030`) prefix every URL with `public/`. Under that
layout, `/.env`, `/shop.sql`, `/composer.json` and `/storage/logs/*` can be
downloaded unless the vhost blocks them. This app serves from `public/`. The
merge must keep it that way. Rewrite those two helpers to drop the `public/`
prefix. That is the single point every storefront asset URL goes through.

### 0.3 Legacy endpoints and secrets that must not survive as-is

- `routes/api_warehouse.php` is mounted with **no middleware group**
  (`RouteServiceProvider::mapApiWareHouseRoutes`). These routes are
  **unauthenticated** and expose customer PII and orders:
  - `GET api/v2/warehouse/customers`
  - `/reps`
  - `/customer/{id}`
  - `/orders`
  - `/order/{id}`
  - `/order_items/{id}`
  - `POST /order/update-customer`

  The only guard on the rest of that file is `wms.auth` (`WmsAuthMiddleware`).
  That guard compares a `private-token` header with `!=` against a secret
  **hardcoded in `config/app.php:110`** (`wms_token`).
- `config/services.php` hardcodes the FCM `project_id`. Nineteen
  `stores.otajer.com/api/...` URLs are hardcoded across 14 files, with the
  token appended from `config('services.otajer.token')`.
- There are 450 `env()` calls in 170 files outside `config/`. Every one returns
  `null` once `config:cache` runs, and this app's deploy caches config.

### 0.4 `app/Exceptions/` is gitignored, so the legacy repo cannot boot as committed

`.gitignore` lists `/app/Exceptions`. `bootstrap/app.php` binds
`App\Exceptions\Handler`, which is not in the repo. `composer.lock` and
`vendor/` are also ignored, so **no version pins survive**. Every version below
is inferred from the `composer.json` ranges.

The upgrade branch (`804dca9`) un-ignores `app/Exceptions` and adds a
`Handler`. `main` still can't boot.

### 0.5 Corrections to the facts this audit was seeded with

- The legacy `app/` directory holds **527 files**, not about 1600. There are 526
  PHP classes plus `Helpers.php`, which is 1578 lines and 70 global functions.
  There are 420 Blade views on top of that.
- The schema is **not only `shop.sql`**. `shop.sql` is a phpMyAdmin dump of DB
  `raizercms`, generated **2023-05-03**. Its `migrations` table records just 2
  rows. On top of it sit:
  - **106 migrations** in `database/migrations`, dated 2023-08 to 2025-03. They
    create 20 tables and alter `users`, `products`, `orders` and others.
  - **68 `sqlupdates/v*.sql` files**, which are vendor upgrade scripts.

  Production has drifted from all three. Capture it before phase 5 (see phase 0b).
- There are **14 payment-gateway SDK packages**, not about 20. Another 8
  gateways (bKash, Nagad, SSLCommerz, Aamarpay, VoguePay, PayHere, N-Genius,
  ProxyPay) are hand-rolled with no SDK.
- `php: ^7.1.3` contradicts `laravel/framework 8.*`, which needs `^7.3|^8.0`.
- This app declares `php: ^8.3`, but its `composer.lock` actually needs
  **PHP ≥ 8.4.1**: symfony 8.1 components (`symfony/console` v8.1.4 and others)
  and `phpunit/phpunit` 13. Target PHP 8.4+ for the merged app.

### 0.6 What `f31f264` changed

The second legacy commit touches 9 files. None of it changes a package, a
table, a class name, a config key or a route, so sections 1–7 still hold. What
it does change:

| File | Change | Effect on this audit |
| --- | --- | --- |
| `tn.php`, `public/tn.php` | Deleted | Updates 0.1. The webshell, the probe and the zips remain |
| `SearchController` (2 queries), `BlogController` | `orderByRaw()` now binds the `LIKE` terms (`?` + bindings) instead of interpolating the search string into the SQL | Fixes SQL injection in the storefront search and blog search. A sweep of `app/` for other `*Raw()`/`DB::select`/`DB::statement` calls with an interpolated or concatenated variable finds none left |
| `ProductController` (admin fetch) | The `cron_jobs` lookups wrap `status = pending OR running` in a closure and only count rows updated in the last 30 minutes | Fixes an operator-precedence bug where any `running` row of any command blocked the fetch, and a stuck job no longer blocks it forever. Keep this logic when the Otajer import commands move into `routes/console.php` (§6.5) |
| `ProductController` (admin listing) | The default listing hides `deleted_from_api = 1` products | Store-admin UI only. It matches the idea behind WMS's `published` filter, but the two columns are different and must not be merged into one flag |
| `NewFetchProductsCommand` | The `shipping` product and its `PerBox` stock row use `firstOrCreate` instead of `create` | Each import no longer adds another `shipping` row. Note for §3: this row is **not** in the Otajer feed, so whichever command ends up as the single `products` writer must keep creating it, and WMS product search will see it once the tables are shared |
| `CategoryUtility::flat_children` | Guards against category parent cycles | Fixes unbounded recursion. No merge impact |
| `backend/representatives/index.blade.php` | The delete link no longer points `href` at the route, it uses `data-href` with the confirm modal | Fixes deletion by a plain link click. No merge impact |
| `public/download/product_bulk_demo.xlsx` | Added | A static asset to carry over with `public/` (§5) |

### 0.7 Owner decisions and upgrade status (2026-09-28)

Owner decisions. They override any recommendation in this file that conflicts:

- **Never turn off Composer's platform check.** No `--ignore-platform-reqs`,
  no `config.platform` override, no `platform-check: false`. The legacy
  production `vendor/` could only have been installed that way: its code uses
  PHP 8.1 enums while `laracon21/*` allowed only PHP 7. A package that doesn't
  allow the running PHP gets upgraded or replaced with one that does.
- **The legacy repo is temporary.** It will be deleted once its code is in
  this app, so don't spend effort on its own configuration, deploy layout,
  lock file or test harness. What matters is how the code lands here.
- **The legacy framework is upgraded one major at a time** (8 → 9 → 10 →
  11 → 12 → 13), not in one jump to the L13 skeleton. This replaces the
  "skip the intermediate hops" advice in the phase 2 row of §7.

Status: branch `claude/albaraka-laravel-13-upgrade-7b5954` of the legacy
repo is on `laravel/framework ^13.17` and `php ^8.3`. It continues
`claude/albaraka-laravel-upgrade-fxsyl1` (L8 → L10) with one commit per step:

| Commit | What it did |
| --- | --- |
| `804dca9`, `6165c95` | L9 and L10 (earlier session). Removed `fideloper/proxy`, `symfony/mailer`, `spatie/db-dumper`, `lcobucci/jwt` and the three `laracon21/*` packages, inlined `makeCombinations()` into `App\Utility\ProductUtility`, replaced `timezones()` with `DateTimeZone::listIdentifiers()`, and added the `personal_access_tokens.expires_at` migration for Sanctum 3 |
| `62dc3f9` | Removed the dead packages and the SDK-backed gateways (§1.1), with every controller, route, view, provider, alias and config file that imported them |
| `d4cdf20` | PHP 8.2–8.5 deprecations (§0.9) |
| `0b13c5e` | L11, PHP 8.2, Sanctum 4, spatie v6, Carbon 3 fixes |
| `c64715c` | L12, the duplicate-route-name fix (§6.4) |
| `ba58d80` | L13, PHP 8.3, spatie v8, maatwebsite/excel 4 (merged to `main` as PR #1) |
| `52e0de5` | Phase 1: webshell, addon installer, updater and installer (§0.1) |
| `3fb59dc` | Phase 1: the 12 addon route files and what only they reached |
| `2a2aea1` | Phase 1: the ActiveITzone licence-check package |
| `62ec165` | Phase 1: the dead Mix asset pipeline |
| `1d01ab7` | Phase 1: checkout options and admin toggles of the removed gateways |
| `5506c3b` | Phase 1: `DemoController` and the unreachable table-less models |

Decisions taken on that branch:

- **The L11 slim skeleton is deferred to the file-merge phase (§7 phase
  6).** The legacy app keeps `App\Http\Kernel`, `App\Console\Kernel`, the
  five service providers and the `providers`/`aliases` arrays in
  `config/app.php`. The L11 upgrade guide recommends against migrating an
  upgraded app, L13 still supports that layout (`$routeMiddleware` is still
  read), and all of it is folded into this app's `bootstrap/app.php` in
  phase 6 anyway.
- **`composer.json` is now merge-ready.** `php`, `laravel/framework`,
  `laravel/sanctum`, `spatie/laravel-permission` and `laravel/tinker` carry
  this app's exact constraints. The only overlapping `require` entry that
  differs is `simplesoftwareio/simple-qrcode` (`^4.2` there, `*` here), and
  both resolve to 4.2.0. Resolution was checked with `composer update
  --dry-run` against Packagist metadata only, with the platform PHP set in a
  scratch copy to 8.3.0, 8.4.1 and 8.5.0:
  - the legacy set alone, with `roave/security-advisories` added;
  - this app's `composer.json` plus every legacy `require`;
  - `composer require --dry-run` of the legacy additions on top of this
    app's `composer.lock`. That adds 39 packages and patch-bumps
    `guzzlehttp/guzzle` 7.15.3 → 7.15.4. Nothing already locked is
    downgraded or removed.

### 0.8 Open decisions for the owner

These came out of the upgrade and need a business answer, not a code one:

- **The gateways removed in `62dc3f9` were removed on the 2023 dump's
  toggles, before the phase 0b production check.** Stripe, PayPal, Razorpay,
  Paystack, Instamojo, Payku, Authorize.Net and Mercado Pago lost their web
  controllers. The Flutter app's `api/v2` Stripe, PayPal, Razorpay, Paystack,
  Flutterwave and Paytm endpoints are gone too. If 0b shows any of them in
  use, restoring one means a current SDK, not a revert:
  - `unicodeveloper/laravel-paystack`: last release 1.2.0 (2024-04), max L11,
    no maintained fork of it. `binkode/laravel-paystack` 1.6.0 installs on L13,
    but it is a separate, lightly used package whose API was not compared.
    Either drop Paystack or budget a rewrite of its two controllers.
  - `paypal/paypal-checkout-sdk`: abandoned by PayPal in favour of
    `paypal/paypal-server-sdk`, which has a different API. PayPal would be a
    rewrite.
  - `kingflamez/laravelrave` (Flutterwave): max L10, and none of the forks
    checked on Packagist supports L13. Flutterwave would be a rewrite against
    its HTTP API.
  - `instamojo/instamojo-php`: last release 1.0 in 2020, unmaintained.
  - Stripe (`stripe/stripe-php` 21.x), Razorpay 2.9, Authorize.Net 2.0.4 and
    Payku 1.0.5 still install. Stripe jumps from API-pinned 7.x, so its
    checkout calls would need re-testing.
- **Checkout options for the removed gateways are gone** on the upgrade
  branch (`1d01ab7`): the wallet and package modals,
  `Api\V2\PaymentTypesController`, and the admin toggles and credential
  forms. `main` still offers them whenever their toggle is on, and checkout's
  `payment_option` dispatch then falls through to the manual-payment branch.
  Keep those toggles off until the branch merges.
- **`iyzico/iyzipay-php` stays** (`^2.0.61`, the current release) because
  `iyzico=1` in the dump. Drop it if 0b shows that was a demo default.

### 0.9 What was verified, and what was not

Checked statically, with nothing installed:

- **Compile-time deprecations.** `php -l` on PHP 8.4 over the legacy `app/`,
  `config/`, `routes/`, `database/` and `tests/`, and over all 409 Blade views
  after compiling them with this app's L13 Blade compiler. No implicitly
  nullable parameters. No `${var}` interpolation either: the 48 "`${var}`
  sites" in §7 phase 2 are JavaScript template literals inside `<script>`
  blocks, not PHP. The only failure is a pre-existing parse error in
  `frontend/voguepay/customer_package_payment_vogue.blade.php` (an unmatched
  `)` in the currency lookup). That view 500s whenever it renders; phase 1
  deletes VoguePay or fixes it.
- **Dynamic properties.** A nikic/php-parser scan of legacy `app/` for
  `$this->x =` on undeclared properties (resolving parents through this app's
  L13 vendor) and for `$request->x =`. The only hits were 7 order-status paths
  stashing FCM fields on the `Request`; they now call
  `NotificationUtility::sendOrderFirebaseNotification()`.
- **Framework class references.** Every `Illuminate\…`, `Laravel\Sanctum\…`
  and `Spatie\Permission\…` class the legacy code names exists in this app's
  installed L13 / Sanctum 4.3 / spatie 8.3 source. The five `Auth\*`
  controller traits come from laravel/ui, which therefore stays.
- **maatwebsite/excel 4.** `CustomersImport`, `ProductsImport` and
  `ProductsExport` load against the 4.0.3 concern interfaces. Before the
  signature changes they fatal.
- **Routes.** All 1379 legacy routes register on an L13 router. After
  `keepLastRegisteredRouteNames()`, no name is shared and each duplicated
  name resolves to its pre-L12 target.

Not verified, because the legacy `vendor/` can't be installed here and CI
doesn't exist for that repo:

- The app has never booted on L11–L13, so there is no `artisan`, no
  `route:list` and no request through the kernel. The legacy PHPUnit suite
  (`ExampleTest` ×2, `UpdateProductFromApiTest`, and the new
  `RouteNameResolutionTest`) has not run.
- Runtime behaviour of the packages whose code changed underneath: Carbon 3
  beyond the `diffIn*()` sites, PhpSpreadsheet 1.30 → 5 inside Excel imports
  and exports, spatie v6's `RoleOrPermissionMiddleware` (it now checks
  `canAny()`, so Gate abilities count too), Sanctum 4 and laravel/ui 4.6.
- Runtime-only PHP deprecations that static reads can't see, such as `null`
  passed to internal string functions.
- Legacy migrations that `->change()` columns (13 sites) or pass
  precision to `double()`. L11 changed both. They run only on a fresh
  database, and phase 5 replaces them with a baseline schema.

## 1. Package compatibility (legacy `composer.json`)

Here is what the verdicts mean:

- **keep→X**: bump to X, which is compatible with Laravel 13 on PHP 8.4.
- **remove**: dead code, so drop the package and its call sites.
- **replace**: abandoned or not installable on PHP 8, so swap it for the named
  alternative.
- **in-WMS**: this app's lock already carries the package, so the merge adopts
  the WMS version.

"Refs" is the number of legacy files that reference the package's namespace or
facade under `app/`, `routes/`, `config/` and `resources/views/`. 

**Gateway toggles** come from `business_settings` in the 2023 dump. Only
`cash_payment=1` is on among the checkout-visible gateways, except `iyzico=1`.
The `addons` table is **empty**, so every `addon_is_activated()` branch is dead.
Before deleting any gateway, confirm it against production:

```sql
SELECT type, value FROM business_settings WHERE type REGEXP 'payment|razorpay|paystack|iyzico|proxypay';
SELECT unique_identifier, activated FROM addons;
SELECT payment_type, COUNT(*) FROM orders GROUP BY payment_type;
```

### require

| Package | Legacy | Refs | Verdict | Notes |
| --- | --- | --- | --- | --- |
| php | ^7.1.3 | – | → ^8.3 (declared, = WMS) | the runtime needs ≥ 8.4.1, see 0.5 |
| laravel/framework | 8.* | – | keep→^13.17 (in-WMS) | |
| laravel/sanctum | ^2.12 | 11 | keep→^4.0 (in-WMS 4.3.3) | only one token model is possible, see §3 |
| spatie/laravel-permission | ^5.5 | 6 (+388 `can()`/`permission:` sites) | keep→^8.3 (in-WMS) | `Spatie\Permission\Middlewares\*` becomes `Middleware\*`. Only one `table_names` is possible, see §3 |
| simplesoftwareio/simple-qrcode | ^4.2 | 8 | in-WMS 4.2.0 | rep-payment QR codes |
| laravel/tinker | ^2.0 | 0 | keep→^3.0 (in-WMS) | |
| guzzlehttp/guzzle | ^7.3 | 15 | keep ^7.8 (in-WMS 7.15) | don't take 8.x, the framework pins 7 |
| symfony/mailer | ^5.4 | 0 | remove | framework brings v8 |
| spatie/db-dumper | 2.21.1 (exact pin) | 0 | remove | the pin conflicts with WMS's 4.1.1 (via laravel-backup) |
| laravel/ui | ^3.0 | 11 (5 auth traits) | keep→^4.6.3 | not removable as-is: the 5 `Auth\*Controller`s use its `Illuminate\Foundation\Auth\*` traits and `web.php` calls its `Auth::routes(['verify'=>true])` macro. Re-authoring those controllers is the only way to drop it |
| laravel/socialite | ^5.0 | 3 | keep→^5.31 | `google_login`/`facebook_login` = 0 in dump, verify |
| genealabs/laravel-socialiter | * | 2 | keep→^13.0 | Apple sign-in in `Api\V2\AuthController:316` |
| genealabs/laravel-sign-in-with-apple | * | 0 direct | keep→^13.0 | driver `sign-in-with-apple`. Remove both genealabs packages if Apple login is unused |
| lcobucci/jwt | ^3.4.5 | 0 | remove | a transitive pin for Apple sign-in, 3.x needs PHP 7 |
| giggsey/libphonenumber-for-php | * | 3 | keep→^9.0 | rep payments, customers |
| google/apiclient | ^2.18 | 2 | keep ^2.20 | FCM HTTP v1 auth in `NotificationUtility`, `AdminNotificationController` |
| intervention/image | ^2.5 | 6 | keep→^3.11 + intervention/image-laravel ^1.5 | v3 rewrites the API (`Image::make` becomes `ImageManager::read`). Uses: `TripService`, `AizUploadController` ×2, `SellerFileUploadController`. The `Image` alias goes away |
| maatwebsite/excel | ^3.1 | 7 | keep→^4.0 | 4.x is **required**, not optional: every 3.1.x needs phpspreadsheet 1.30, which caps PHP below 8.5 (this app's CI runs 8.5), and 3.1 < 3.1.70 has an advisory. v4 adds native return types to the concerns, see §0.9 |
| laracasts/flash | ^3.0 | 100 | keep→^3.2.6 | 3.2.6 allows `^13.0` |
| enshrined/svg-sanitize | ^0.15.4 | 1 | keep→^0.22 | `AizUploadController`. Every release below 0.22 has an advisory (roave refuses it). `sanitize()` is unchanged in 0.22 and 1.0.0 |
| setasign/fpdi | ^2.6 | 1 | keep ^2.6.8 | `TripService` (`TcpdfFpdi`) |
| setasign/fpdf | ^1.8 | 0 direct | keep ^1.9 | the FPDI backend |
| tecnickcom/tcpdf | ^6.8 | 1 (+`TcpdfFpdi`) | keep ^6.11 | needed by `TripService`, not just `config/excel.php` |
| league/flysystem-aws-s3-v3 | ^1.0 | 13 files gate on `env('FILESYSTEM_DRIVER')=='s3'` | keep→^3.0 **or remove** | 1.x is Flysystem 1, while L9+ needs 3. Remove it if prod `FILESYSTEM_DRIVER` isn't `s3` |
| predis/predis | ^1.1 | 0 (only the `Redis` alias) | remove | `.env.example` uses file cache and sync queue |
| niklasravnsborg/laravel-pdf | ^4.0 | 7 | replace | **abandoned**. Swap to `barryvdh/laravel-dompdf` + `khaled.alshamaa/ar-php` (both in-WMS, and WMS's Arabic PDF stack already). Uses: invoices, bulk upload, rep payments |
| mehedi-iitdu/core-component-repository | 2.0 | 15 files, 47 calls | replace (delete) | ActiveITzone licence check. Delete every `CoreComponentRepository::instantiateShopRepository()`/`initializeCache()` call |
| laracon21/combinations | 1.2 | 7 calls | replace | `php: ^7.1`, **not installable on PHP 8**. Inline a cartesian-product helper into the existing `App\Utility\ProductUtility` |
| laracon21/timezones | 1.2 | 3 | replace | `php: ^7.1`. `timezones()` helper becomes `DateTimeZone::listIdentifiers()` |
| laracon21/colorcodeconverter | 1.2 | 0 | remove | provider line only |
| barryvdh/laravel-ide-helper | ^2.10 | 1 | remove (or require-dev ^3.7) | it wrongly sits in `require` |
| fideloper/proxy | ^4.0 | 1 | remove | max L9. Use WMS's `$middleware->trustProxies()` in `bootstrap/app.php` |
| rmccue/requests | ^1.8 | 0 | remove | |
| milon/barcode | ^10.0 | 0 | remove | |
| twilio/sdk | ^6.1 | 2 | remove | `SendSMSUtility`, which only the OTP addon uses (inactive, and its tables don't exist) |
| stripe/stripe-php | ^7.95 | 4 | remove | `stripe_payment=0`. If it's kept, use a current major |
| paypal/paypal-checkout-sdk | dev-master | 2 | remove | **abandoned** in favour of paypal/paypal-server-sdk. `paypal_payment=0` |
| razorpay/razorpay | ^2.0 | 7 | remove | `razorpay=0` |
| unicodeveloper/laravel-paystack | ^1.0 | 9 | remove | max `^11.0`, **cannot install on L13**. `paystack=0` |
| iyzico/iyzipay-php | ^2.0 | 2 | remove after prod check | `iyzico=1` in the dump, the one gateway toggle that is on. Probably a demo default, but verify first |
| instamojo/instamojo-php | ^0.4.0 | 8 | remove | `instamojo_payment=0` |
| sebacarrasco93/laravel-payku | ^1.0 | 6 | remove | no toggle row |
| authorizenet/authorizenet | ^2.0 | 1 | remove | sandbox row only |
| mercadopago/dx-php | ^2.4 | 1 (view) | remove | no toggle row |
| myfatoorah/laravel-package | ^2.0 | 3 (views) | remove | `MyfatoorahController` isn't in the repo |
| anandsiddharth/laravel-paytm-wallet | ^2.0.0 | 2 | remove | paytm addon inactive, web `Payment\PaytmController` missing |
| kingflamez/laravelrave | ^4.2 | 1 (provider) | remove | max `^10.0`. `FlutterwaveController` missing |
| osenco/mpesa | ^1.20 | 3 | remove | african_pg addon, `MpesaController` missing |
| cinetpay/cinetpay-php | ^1.9 | 0 | remove | |

### require-dev

| Package | Legacy | Verdict |
| --- | --- | --- |
| phpunit/phpunit | ^9.0 | replace with Pest ^5 (in-WMS). The legacy tests are `ExampleTest` ×2 and `UpdateProductFromApiTest` |
| fzaninotto/faker | ^1.4 | replace with fakerphp/faker (in-WMS). It is **abandoned**. `database/factories/UserFactory.php` uses the old `$factory->define` style |
| facade/ignition | ^2.3.6 | remove, it is abandoned and max L8. L13's own error page covers it |
| beyondcode/laravel-dump-server | ^1.0 | remove, max `^12.0` |
| barryvdh/laravel-debugbar | ^3.6 | remove (or ^4.4) |
| filp/whoops | ^2.0 | remove as a direct dependency (in-WMS transitively) |
| mockery/mockery, nunomaduro/collision | ^1.0, ^5.0 | in-WMS (1.6, 8.9) |

**Net result**: 49 legacy packages become about 14 additions to this app's
`composer.json`, if the removals hold:
- laravel/ui
- laravel/socialite
- the two genealabs packages
- libphonenumber
- google/apiclient
- intervention/image + image-laravel
- maatwebsite/excel
- laracasts/flash
- svg-sanitize
- fpdf
- fpdi
- tcpdf

That list is before any gateway the production check keeps. Also: drop the
`database/seeds` classmap, keep the `files: [app/Http/Helpers.php]` autoload,
and drop `minimum-stability: dev` (it existed only for the PayPal `dev-master`).

### 1.1 Final legacy package list (branch `claude/albaraka-laravel-13-upgrade-7b5954`)

The tables above are the original audit. This is what the legacy
`composer.json` requires now.

`require`:

| Package | Constraint | Note |
| --- | --- | --- |
| php | ^8.3 | = WMS |
| laravel/framework | ^13.17 | = WMS |
| laravel/sanctum | ^4.0 | = WMS |
| spatie/laravel-permission | ^8.3 | = WMS |
| laravel/tinker | ^3.0 | = WMS |
| simplesoftwareio/simple-qrcode | ^4.2 | WMS has `*`; both lock 4.2.0 |
| laravel/ui | ^4.6.3 | |
| laravel/socialite | ^5.24.3 | |
| genealabs/laravel-socialiter, genealabs/laravel-sign-in-with-apple | ^13.0 | drop both if Apple login is unused |
| giggsey/libphonenumber-for-php | ^9.0 | |
| google/apiclient | ^2.18 | |
| guzzlehttp/guzzle | ^7.8 | |
| intervention/image | ^2.5 | v2 is end-of-life; the v3 rewrite (5 call sites) is still open |
| maatwebsite/excel | ^4.0 | |
| laracasts/flash | ^3.2.5 | |
| enshrined/svg-sanitize | ^0.22 | |
| setasign/fpdf, setasign/fpdi, tecnickcom/tcpdf | ^1.8, ^2.6, ^6.8 | |
| league/flysystem-aws-s3-v3 | ^3.0 | remove if production `FILESYSTEM_DRIVER` isn't `s3` |
| niklasravnsborg/laravel-pdf | ^4.0 | abandoned; still to replace with dompdf |
| mehedi-iitdu/core-component-repository | 2.2 | still to delete with its 47 calls |
| iyzico/iyzipay-php | ^2.0.61 | the only payment SDK left, see §0.8 |

`require-dev`: `fakerphp/faker ^1.24`, `mockery/mockery ^1.6` and
`nunomaduro/collision ^8.9.3` (all = WMS), and `phpunit/phpunit ^12.0`,
which the merge drops in favour of Pest.

Removed from `require`:

- `62dc3f9`:
  - `anandsiddharth/laravel-paytm-wallet`
  - `authorizenet/authorizenet`
  - `barryvdh/laravel-ide-helper`
  - `cinetpay/cinetpay-php`
  - `instamojo/instamojo-php`
  - `kingflamez/laravelrave`
  - `mercadopago/dx-php`
  - `milon/barcode`
  - `myfatoorah/laravel-package`
  - `osenco/mpesa`
  - `paypal/paypal-checkout-sdk`
  - `predis/predis`
  - `razorpay/razorpay`
  - `rmccue/requests`
  - `sebacarrasco93/laravel-payku`
  - `stripe/stripe-php`
  - `twilio/sdk`
  - `unicodeveloper/laravel-paystack`
- The L9/L10 hops:
  - `fideloper/proxy`
  - `symfony/mailer`
  - `spatie/db-dumper`
  - `lcobucci/jwt`
  - `laracon21/*` ×3

Removed from `require-dev`:

- `barryvdh/laravel-debugbar`, `beyondcode/laravel-dump-server`,
  `filp/whoops`, `spatie/laravel-ignition`
- `facade/ignition` and `fzaninotto/faker` (earlier hops)

`config/database.php`'s redis client is now
`env('REDIS_CLIENT', 'phpredis')`, since predis is gone.

## 2. Namespace collisions (`App\` in both)

Both apps map `App\` to `app/`. Only one class per FQCN can load.

### Exact FQCN collisions: 5

| FQCN | Resolution |
| --- | --- |
| `App\Models\User` | **Done** (2026-09-28, legacy repo commit `a99b02c`, branch `claude/albaraka-holland-namespace-migration-u5nkzv`). **Different tables** (`users` vs `wms_users`) and populations, so both must exist. Renamed to `App\Models\StoreUser` in the legacy repo, 139 files (104 mechanical FQCN/`use` sites plus 35 files with bare same-namespace or post-`use` references a plain FQCN grep can't see — see §2.1). The legacy rows move tables anyway (§3), so rewriting their morph type costs nothing extra. Morph columns storing `App\Models\User` for the store side still need an `UPDATE` at the phase 5 DB merge, now confirmed as exactly these four (no `morphMap()` exists anywhere in the legacy app, so every one resolves the FQCN dynamically via `getMorphClass()`, not a static alias): `model_has_roles.model_type`, `personal_access_tokens.tokenable_type`, `notifications.notifiable_type`, `checkpoints.relationable_type` (`Checkpoint::isCustomer()`/`getCustomerForCheckpoint()`, `Trip.php:70` `whereHasMorph(... [StoreUser::class, Order::class])` — both already use the renamed class, so newly written rows store `App\Models\StoreUser`; only pre-existing rows need the `UPDATE`) |
| `App\Models\Product` | **Same table** (`products`). Merge into one model: legacy is the full 72-column writer, WMS adds `searchByName`/`applyNameSearch`, `pallets`, `setting`, `WITH_DERIVED_ATTRIBUTES`, `boxes_count`/`image_url`, `optionLabels()`. See the conflicts in §6.8 |
| `App\Models\Upload` | **Same table** (`uploads`). Merge: legacy is the writer (`AizUploadController`, import commands), WMS only has `url()`. Merging also answers shared-database.md's open question about how `uploads` stays current |
| `App\Http\Controllers\Controller` | Keep WMS's abstract controller (`paginated`, `redirectPreservingQuery`, `resolvePerPage`). Legacy extends L8's base with `AuthorizesRequests`/`ValidatesRequests`. `$this->validate/authorize/dispatch` is used in 3 legacy controllers, so give those the traits directly |
| `App\Providers\AppServiceProvider` | Merge the bodies. WMS's global settings break legacy code, see §6.1 |

### 2.1 `User` → `StoreUser` rename: what was checked and fixed

A 2026-09-28 session was asked to rename the legacy tree's entire `App\`
root namespace (e.g. to `App\Shop\`). It stopped short of that: this
section's own recommendation (below) and phase 7's phase-3 scope both
already rejected a full-tree rename as unnecessary churn with no autoload
benefit, so the session did only what phase 3 actually calls for — the one
collision that's a same-repo mechanical rename. It is not a general
precedent against renaming namespaces here; it's a record that the
full-tree option was considered and declined for the reason already on
record. `composer.json`'s `psr-4` mapping did not change: `App\` still maps
to `app/`, only the `User` short name moved to `StoreUser`.

**Scope of the mechanical work.** A plain `grep` for the `App\Models\User`
FQCN found 104 files, but the true footprint was 139: 35 more files use the
bare class name after a `use App\Models\User;` import (now
`use App\Models\StoreUser;`) or, for 34 of those, from inside `app/Models/`
itself, where same-namespace code never imports its sibling classes at all.
A rename that stops at the FQCN grep leaves these bare references pointing
at a class that no longer exists — this is the trap to check for in any
later phase-3-style rename here. Every changed file was confirmed with
`php -l` on PHP 8.4 (139/139 clean, matching this audit's own §0.9
verification method) before pushing.

**String-based references checked, per this file's own "easiest thing to
miss" warning:**
- `$this->app->bind()`/`singleton()` calls: none reference `User` by string
  anywhere in `app/Providers/`.
- `config/queue.php`'s `failed` block only names a table, not a class; no
  job payload class names are hardcoded there.
- Blade `@php use` statements: none exist importing `App\Models\User` (or
  anything else) anywhere in `resources/views/`. Every Blade reference to
  the model uses the fully-qualified `\App\Models\User` form directly, so
  the FQCN sweep already covered all 420 views correctly — 10 of them
  needed the leading-backslash form specifically (`\App\Models\User::…`),
  which a naive "no leading backslash" regex would miss.
- Route model binding type-hints: none bind `User` in `routes/`.
- `Spatie\Permission` (`config/permission.php`) and Sanctum
  (`config/sanctum.php`) config: neither hardcodes the user model by name
  or string; both resolve it dynamically (`auth.providers.users.model`,
  which does need — and got — the `App\Models\User::class` →
  `App\Models\StoreUser::class` update in `config/auth.php`). Sanctum has
  no model override to update at all.
- `config/services.php`'s dead `stripe.model` key (the Stripe gateway
  itself was removed in phase 1, §1) still referenced
  `App\Models\User::class` and was updated too, since leaving a dangling
  FQCN in a live config file — even an unused key — would break
  `config:cache`.
- `Illuminate\Foundation\Auth\User as Authenticatable` in the model file
  itself is the **framework's** base class, imported under an alias; it is
  correctly untouched, it is not the legacy `App\Models\User`.
- **`morphMap()`: none exists anywhere in the legacy app.** Every
  polymorphic relation resolves its stored type column dynamically via
  `getMorphClass()` (`get_class($model)`), not a short static alias. That
  is *worse* for a rename than a `morphMap()` would have been: a
  `morphMap()` alias is one line to update and every stored row keeps
  resolving correctly; without one, this rename immediately changed what
  new rows write (`App\Models\StoreUser` from now on) while every
  **existing** row across the four morph columns listed in the table above
  still holds the literal string `App\Models\User`, and nothing in this
  session's scope (a standalone code-only repo, no DB access) could touch
  that production data. Whoever runs the phase 5 DB merge must run the
  `UPDATE` against all four columns *before* any code that reads them
  through `getMorphClass()`-based comparisons goes live on the merged
  database, or `Checkpoint::isCustomer()`/`getCustomerForCheckpoint()` and
  the `Trip::scopeSearch()` `whereHasMorph()` call will silently stop
  matching every pre-rename row.
- `routes/channels.php` has `Broadcast::channel('App.User.{id}', …)`. This
  looked at first like a stale namespace-derived channel name, but nothing
  in the app calls `new PrivateChannel('App.User.'.$id)` or broadcasts
  through it — the only real `PrivateChannel` usage in the app is
  `'trip.'.$id` (`SetTruckAvailability`). It is an arbitrary, seemingly
  unused string identifier, not a derived class reference, so it was left
  as-is rather than guessed at.

**Deferred, not done here:** `Product`, `Upload`, `Controller` and
`AppServiceProvider` are body *merges* with WMS's own versions of those
classes (§2's table), not renames — writing the merged class correctly
needs both codebases in the same tree to see both sides, which this
still-standalone session didn't have. They stay under `App\` in the legacy
repo until the phase 6 file move, at which point phase 3's plan already
covers them.

### Legacy infrastructure classes with no L13 counterpart

Fold these into `bootstrap/app.php`/`bootstrap/providers.php`, don't port them:

- `App\Http\Kernel`. Its 24 middleware include aliases `admin`, `seller`,
  `user`, `unbanned`, `wms.auth`, `role`, `permission`, and a CSRF `$except`
  list of 8 entries.
- `App\Console\Kernel`. Its schedule is empty. Imports run from system cron via
  `check:commands` and the `cron_jobs` table.
- `RouteServiceProvider` (plus a stray `RouteServiceProvider.txt`),
  `EventServiceProvider`, `AuthServiceProvider`, `BroadcastServiceProvider`.
  `RouteServiceProvider::keepLastRegisteredRouteNames()` must survive the
  fold, see §6.4.
- The missing `App\Exceptions\Handler`.
- The framework-default middleware: `Authenticate`, `RedirectIfAuthenticated`,
  `EncryptCookies`, `TrimStrings`, `TrustProxies`, `VerifyCsrfToken`,
  `CheckForMaintenanceMode`.

### Short-name collisions under different namespaces

These don't clash in the autoloader. They matter only for `use` aliasing and
grep-ability:

| Short name | Legacy | WMS |
| --- | --- | --- |
| `ProductController` | `Http\Controllers\ProductController`, `Seller\`, `Api\V2\`, `Api\V2\Seller\`, `Api\V2\Warehouse\` | `Admin\`, `Api\V1\` |
| `DashboardController` | `Seller\` | `Admin\`, `Api\V1\` |
| `AuthController` | `Api\V2\` | `Api\V1\` |
| `UserController` | `Api\V2\` | `Admin\` |
| `LoginController` | `Auth\` | root |
| `Controller` | `Api\V2\`, `Api\V2\Seller\`, `Seller\` | root |
| `ProductResource` | `Resources\V2\Seller\` | `Resources\` |

Everything else in legacy `app/` (521 of 526 classes) has a unique FQCN. It
does not collide and can move as-is. Recommendation: don't move the whole
legacy tree under a `Store\` sub-namespace. That touches every file and the 420
views that name classes, for no autoload benefit. Place only *new* merge glue
under `app/Http/Controllers/Store/` and `app/Models/Store/` if needed.

### Global functions

The 70 functions in `app/Http/Helpers.php` were checked against every function
defined by this app's `vendor/` autoload. **None collide.** 66 of them are
wrapped in `function_exists`, so a future vendor helper with the same name
would win silently.

## 3. Database table collisions

The legacy schema has 111 table names. That is the union of:
- `shop.sql` (83)
- `Schema::create` in legacy migrations (19 more)
- `sqlupdates` creates not in the dump (9 more: `app_settings`,
  `follow_sellers`, `oauth_*` ×5, `sub_category_translations`,
  `sub_sub_category_translations`). Whether these still exist in production is
  unknown until the 0b dump. `App\Models\AppSettings` reads `app_settings`.

WMS has 25 `Schema::create` names (3 of them `pulse_*`) plus the 5 `wms_*`
permission tables and `wms_migrations`. Telescope, Pulse and Health run on
their own sqlite connections and are out of scope.

### Direct name collisions: 2

Both are the store's own tables, which WMS originally shared (see shared-database.md):

| Table | Situation |
| --- | --- |
| `products` | In production the store's table has 72 columns and `id int(11)` signed. WMS's copy lives in its own DB, fed only by `products:sync` (name/ar_name/published from the Otajer feed). **Two writers after the merge:** legacy `import:all-products`, `import:all-products-v2`, `import:offer-new-products` and `fetch:otajer-photos` also write `products`/`uploads` from `stores.otajer.com`. Pick one writer before the DB merge. Also confirm that WMS's `products.id` set is a subset of the store's ids, since `pallets`, `cell_status_logs`, `cell_verification_reports` ×2 and `wms_product_settings` all FK to it. WMS's `add_fulltext_index_to_products_table` and `add_ar_name_to_products_stand_in` must then apply cleanly to the store's table. |
| `uploads` | The store's table. WMS reads it only. After the merge it becomes live data in the same DB. `config('store.asset_base_url')` becomes this app's own URL. |

### One-config-wins collisions

The tables are named differently, but a single app can point only one config at
them:

| Legacy table | WMS table | Why it collides after the merge | Resolution |
| --- | --- | --- | --- |
| `migrations` | `wms_migrations` | one `database.migrations.table` | Keep `wms_migrations`. Replace the 106 legacy migrations + `shop.sql` with a baseline schema (phase 5) and don't carry legacy migration rows |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | `wms_*` of each | one `permission.table_names` | Move the legacy rows into `wms_*`: offset ids, rewrite `model_type` to the store user class, and add the legacy-only **`permissions.section`** column to `wms_permissions` |
| `personal_access_tokens` | `wms_personal_access_tokens` | one `Sanctum::usePersonalAccessTokenModel()` | Move the legacy rows into `wms_personal_access_tokens` with `tokenable_type` rewritten. Sanctum 2 and 4 hash the same way, so Flutter-app tokens keep working. |
| `password_resets` | `wms_password_reset_tokens` | `config/auth.php` brokers | Add a second broker for the store provider (table `password_resets`, or migrate it) |
| `users` | `wms_users` | none, both stay | Two providers/guards (§6.7) |

Sessions, cache and queue don't collide:
- Legacy uses the `file` driver for all three (`.env.example`: `SESSION_DRIVER=file`,
  `CACHE_DRIVER=file`, `QUEUE_CONNECTION=sync`).
- WMS uses database drivers on `wms_sessions`/`wms_cache`/`wms_jobs`.
- The merged app uses WMS's. Store sessions are invalidated at cutover.

### spatie role and permission data in each app

- **Legacy:**
  - One seeded role, `Super Admin` (id 1, guard `web`), assigned to user id 9.
  - Staff roles are created at runtime via `RoleController` (`Role::create`).
    Query production for the real list.
  - 213 permissions (`add_new_product`, `show_all_products`, …) across 28
    `section`s, all on guard `web`.
  - **Role id 1 is hardcoded**: `Role::findOrFail(1)`, `Role::where('id','!=',1)`
    in `RoleController`, `CustomerController` and `StaffController`. Once the
    roles move into `wms_roles`, id 1 is WMS's `admin`. Replace these with
    `findByName('Super Admin')` before the data moves.
- **WMS:** one role, `admin` (guard `web`), created by `Role::findOrCreate('admin', 'web')`,
  and checked via `role:admin` and `User::isAdminGate()`. No permissions.
- `(name, guard_name)` is unique. `Super Admin` and `admin` don't clash, but both
  sit on guard `web` over different user models. Giving the store its own guard
  (§6.7) is what keeps `hasRole()` unambiguous.

### Legacy-only tables (no WMS counterpart)

These carry over unchanged:

`addons addresses app_translations attribute_category attribute_translations
attribute_values attributes blog_categories blogs brand_translations brands
business_settings carrier_range_prices carrier_ranges carriers carts
carts_offered categories category_translations checkpoints cities
city_translations colors combined_orders commission_histories conversations
countries coupon_usages coupons cron_jobs currencies customer_package_payments
customer_package_translations customer_packages customer_product_translations
customer_products customers_notifications dynamic_popup_user dynamic_popups
firebase_notifications flash_deal_products flash_deal_translations flash_deals
home_categories languages messages notifications offer_products offer_user
offers offers_catalogs order_details orders page_translations pages
payku_payments payku_transactions payments pickup_point_translations
pickup_points product_barcodes product_collections product_queries
product_stocks product_taxes product_translations product_variations
proxypay_payments representative_packages representative_payments reviews
role_translations searches seller_withdraw_requests sellers shops staff states
subscribers taxes ticket_replies tickets transactions translations trips
trucks user_page_views visits wallets wishlists zones`

WMS-only tables, which the store has nothing like:

`rows cells pallets cell_status_logs cell_status_log_flags
cell_verification_rounds cell_verification_round_row cell_verification_reports
mobile_app_version_requirements wms_product_settings wms_settings`

### Semantic overlaps, not name clashes

Decide these deliberately in phase 5 or 6:
- `product_stocks` holds per-variant sellable quantity. `pallets`/`cells` hold
  physical stock.
- `trucks.max_pallets_number`, `trips` and `checkpoints` (the legacy delivery
  module) vs WMS pallets.
- `orders.wms_status` ("Sent to WMS"/"Sent to Otajer") and the whole
  `api/v2/warehouse/*` API. It was built for an external WMS, and after the
  merge it becomes an in-process call. WMS itself never calls it.

### Legacy models whose tables exist nowhere

These are uninstalled-addon dead code, so delete them with their controllers.

**Status on the upgrade branch (`5506c3b`):**

- **Deleted:** the models whose only users were dead code or uncalled
  relations: `Banner`, `Slider`, `Policy`, `SubCategory`, `SubSubCategory`,
  `AffiliateLog`, `AffiliateWithdrawRequest`, `AffiliateStats`,
  `AffiliateEarningDetail`, `CartProduct`, `DeliveryBoyCollection`,
  `DeliveryBoyPayment` and `OtpConfiguration`.
- **Kept, because live code paths still reach them:**
  - `ProductStock::wholesalePrices` from the cart
  - `club_point` from checkout
  - `OrderDetail::refund_request` from the `api/v2` purchase-history
    resources
  - `SellerPackage*` from `Helpers.php` and six payment controllers
  - `SmsTemplate`, `ManualPaymentMethod`, `DeliveryBoy` and
    `DeliveryHistory` from order and `api/v2` controllers
  - `Customer` from `CustomerController` and `CustomersImport`
  - `AffiliateUser`, `AffiliateOption`, `AffiliateConfig`,
    `AffiliatePayment`, `AuctionProductBid`, `ClubPoint`, `ClubPointDetail`
    and `RefundRequest`

  Each site needs its guard checked, and the phase 0b schema dump must
  confirm the table is really absent in production, before these go.

Original list:

- `Affiliate*` ×7
- `AuctionProductBid`
- `ClubPoint`, `ClubPointDetail`
- `DeliveryBoy`, `DeliveryBoyCollection`, `DeliveryBoyPayment`, `DeliveryHistory`
- `ManualPaymentMethod`, `OtpConfiguration`, `RefundRequest`
- `SellerPackage`, `SellerPackagePayment`, `SellerPackageTranslation`
- `SmsTemplate`, `WholesalePrice`
- `Banner`, `Slider`, `Policy`
- `SubCategory`, `SubSubCategory`
- `Customer`, `CartProduct`

`ProductsImport`, `ProductsExport` and `CustomersImport` sit in `app/Models`
but are Excel import/export classes, not models.

## 4. Config key collisions

Only one file per config key survives the merge. Start from WMS's files, then
port legacy keys in.

- **`config/app.php`:**
  - Legacy-only keys:
    - `debug_blacklist` (Ignition-era, drop it).
    - `wms_token` (hardcoded secret, see 0.3). Move it to
      `config/services.php` + env, or delete it with the warehouse API.
    - `providers` and `aliases` arrays. Under L13 they move to
      `bootstrap/providers.php`. Aliases for `PDF`, `Paystack`, `Excel`,
      `PaytmWallet`, `Rave`, `Image`, `QrCode`, `Socialite` and `Redis` are
      used as bare `\PDF::`-style facades in Blade/PHP. Keep the ones for
      packages that survive §1, via `AliasLoader` or by importing the FQCN.
  - Env names differ:
    - Legacy `locale` reads `DEFAULT_LANGUAGE`. WMS reads `APP_LOCALE`, and its
      shared Inertia `locale` prop plus `SetLocale` drive `ar`/`en`.
    - Legacy `timezone` reads `APP_TIMEZONE`. WMS hardcodes `'UTC'`.
  - WMS-only keys: `faker_locale`, `previous_keys`, `maintenance`. Use
    `APP_PREVIOUS_KEYS` to keep decrypting the legacy `APP_KEY`'s outstanding
    `encrypt()`ed links (email verification, conversations: 10 files).
- **`config/services.php`:**
  - Legacy keys: `mailgun`, `ses`, `sparkpost`, `stripe`, `google`,
    `facebook`, `twitter`, `paytm-wallet`, `fcm`, `otajer`.
  - WMS keys: `postmark`, `resend`, `ses`, `slack`.
  - Only `ses` overlaps. Legacy reads `SES_KEY`/`SES_SECRET`/`SES_REGION`,
    WMS reads `AWS_*`. Keep WMS's.
  - Add `google`/`facebook`/`twitter` if socialite stays, and `fcm`/`otajer`
    via env.
  - `otajer.token` and WMS's `config/store.php` `products_sync_url` embed the
    same Otajer API key. Unify them.
- **`config/filesystems.php`:** this is **the dangerous one**.
  - The legacy `local` disk has `root => public_path()`. WMS's has
    `storage_path('app/private')`.
  - Legacy code writes uploads through `Storage::disk('local')` and
    `public_path()` in 18 files / 56 sites, and depends on the files landing
    under the web root (`public/uploads/all/...`, which is what
    `uploads.file_name` stores).
  - Naively keeping WMS's `local` disk silently puts new store uploads in a
    private dir. Keeping legacy's exposes WMS's private disk.
  - Add a dedicated `uploads` disk (`root => public_path()`, the equivalent of
    legacy `local`) and repoint the legacy call sites to it.
  - Legacy's `cloud` key is gone in L13.
  - The env name changed: `FILESYSTEM_DRIVER` became `FILESYSTEM_DISK`. The 13
    `env('FILESYSTEM_DRIVER') == 's3'` checks must read config instead.
- **`config/permission.php`:**
  - Same `models` (the stock spatie classes), `model_morph_key`, and cache
    `key` `spatie.permission.cache`.
  - `table_names` differ: bare vs `wms_`. Keep WMS's (see
    shared-database.md) and move the legacy data.
  - WMS-only v8 keys: `register_octane_reset_listener`, `events_enabled`,
    `team_resolver`, `use_passport_client_credentials`.
  - `teams` is `false` in both.
- **`config/auth.php`:** both use a single `web` session guard over provider
  `users` with model `App\Models\User`. The merged app needs two providers and
  guards (§6.7).
- **Legacy-only config files** that move over if their package survives:
  - `excel.php`, `image.php`, `pdf.php` (replace it with the dompdf config).
  - The gateway configs (`flutterwave`, `laravel-payku`, `mercadopago`,
    `nagad`, `nexmo`, `paystack`, `rave`, `toyyibpay`) are dropped with their
    gateways.

## 5. Asset pipeline

The legacy `webpack.mix.js`/`package.json` (Mix 2, Vue 2, Bootstrap 4) are
**dead**:
- No view calls `mix()` or loads `js/app.js`/`css/app.css`.
- `resources/js` holds only the stock `ExampleComponent.vue`.

Drop them outright. There is nothing to port to Vite.

The storefront, admin and seller UIs are 420 server-rendered Blade views
(frontend 132, backend 214, seller 35, auth 5, emails 8). They load prebuilt
ActiveITzone theme bundles through `static_asset()`:

- `public/assets/js/vendors.js`, `aiz-core.js` and `intlTelutils.js`
- `public/assets/css/vendors.css`, `aiz-core.css`, `aiz-seller.css`,
  `custom-style.css` and `bootstrap-rtl.min.css`

Together with fonts and images these come to 11 MB. The views also pull from
CDNs:
- unpkg
- jsdelivr
- code.jquery.com
- Razorpay/bKash checkout scripts
- YouTube/Vimeo/Dailymotion embeds

Here is what that means for each piece:

- **Portable as-is:**
  - Copy `public/assets/**` verbatim as static files. Vite never touches them.
  - Views move under a namespaced directory, e.g. `resources/views/store/`, so
    they don't mix with WMS's `app.blade.php`/`pdf/`, or keep the paths and
    accept the mix.
  - Fix `static_asset()`/`my_asset()` per 0.2.
- **Needs re-authoring only if the owner wants the storefront on Inertia/Vue 3.**
  That is a full rewrite of 420 views, and out of scope for the merge.
- **Will break under WMS's global middleware:**
  - `SecureHeadersMiddleware` is appended globally with CSP on and
    `script-src 'unsafe-inline' => false`. Every legacy view uses inline
    `<script>` and third-party CDNs.
  - Scope CSP per route group, or give store routes their own policy.
- **Translations:**
  - Legacy UI text comes mostly from the DB (`translations` table via the
    `translate()` helper), plus `resources/lang/en/{auth,pagination,passwords,validation}.php`.
  - L13 reads `lang/` when it exists, so the legacy `resources/lang` would be
    ignored. The four files collide by name with WMS's `lang/en/*`. Keep WMS's.
    The legacy copies are framework stubs.

## 6. Cross-cutting behaviour the merge changes

### 6.1 WMS's `AppServiceProvider` globals

These silently alter legacy code:

- `JsonResource::withoutWrapping()`. Legacy has 25 `JsonResource` and 62
  `ResourceCollection` classes serving the Flutter store app on `api/v2`.
  Unwrapping changes their JSON shape. Scope it, or add `$wrap` back on the
  legacy resources.
- `Date::use(CarbonImmutable::class)`. There are 165 Carbon/`now()` uses in 62
  legacy files. Code that mutates in place (`$d->addDays(3)` without
  reassigning) becomes a silent no-op, and `Carbon\Carbon` type-hints throw.
  Audit every site before enabling it for store code.
- `Model::shouldBeStrict()` in local/testing. Legacy lazy-loads everywhere, so
  every legacy test would throw until you eager-load or opt out.
- `Password::defaults()` (min 12 in production) applies to legacy registration
  only where it calls `Password::defaults()`.

WMS's `config/` also has two L13 hardening settings that break legacy code.
The legacy config lacks both keys, so it runs on the L13 fallbacks today:

- **`cache.serializable_classes`.** WMS allow-lists only `Collection`,
  `stdClass` and `CarbonImmutable`. Legacy caches Eloquent models and
  collections: 31 `Cache::remember`/`rememberForever` sites, e.g. the
  `app.todays_deal`, `app.flash_deals` and `app.best_selling_products` API
  caches. With that allow-list, those come back as `__PHP_Incomplete_Class`.
  Either cache arrays (`->toArray()`, then rehydrate), or allow-list the
  legacy model classes. The first is safer.
- **`session.serialization` = `json`.** Legacy stores objects in the session,
  e.g. `compare` and `cart` as Collections (`CompareController`,
  `PayhereController`). Under `json` they come back as arrays, and code calling
  collection methods on them breaks. Store arrays, or scope `php`
  serialization to the store's session if the store gets its own.

### 6.2 `api` middleware group

WMS appends `SetLocaleFromHeader` and `EnsureMinimumAppVersion` to the whole
`api` group. When a minimum version is set, the second one rejects any request
without `X-App-Version`, and the Flutter store app doesn't send it. Scope both
to `api/v1`.

### 6.3 Global `BlockMaliciousRequests` (WAF)

Test it against the storefront. Legacy search, slug and checkout params were
never exercised through it.

### 6.4 Routes

The legacy route table was registered statically against this app's router: 1411
routes. The WMS route table from `route:list` has 129.

- **URI collisions:**
  - `GET /`: WMS `welcome` redirects away vs the legacy storefront home.
  - `GET /admin`: WMS `admin.dashboard` vs the legacy admin dashboard.
  - `POST /logout`.
- **Name collisions:** `login`, `logout`, `admin.dashboard`, `password.update`.
- **Duplicate names inside the legacy routes.** 58 legacy names (61
  before phase 1 deleted the addon route files) are
  registered twice:
  - mostly `Route::resource()` followed by an explicit override like
    `Route::get('/brands/destroy/{id}')->name('brands.destroy')`
  - plus `api/v2` resources that share a name with an admin page, e.g.
    `brands.index`, `categories.index`, `customers.show`

  Up to L11 the last registration won. From L12 the first one does, and
  `route:cache` still refuses duplicates. The legacy
  `RouteServiceProvider::keepLastRegisteredRouteNames()` strips each name
  from all but its last route, and `RouteNameResolutionTest` pins the targets.
  When the store routes move into `routes/store/*.php`, either:
  - run the same step over the store route group, or
  - rename the losing routes at the source, e.g.
    `Route::resource(...)->except(['edit', 'destroy'])` and `api.`-prefixed
    names for the `api/v2` resources.

  Without one of those, admin links would point at the JSON API and every
  GET delete link at a DELETE route.
- **Catch-alls:** legacy `/{slug}` (web) and `api/{fallbackPlaceholder}`
  (`Route::fallback`) would shadow WMS routes registered after them.
- **Dead routes:** 228 legacy routes point at **controllers that are not in the
  repo**, i.e. uninstalled addons:
  - `DeliveryBoyController` 27, `AffiliateController` 26,
    `RefundRequestController` 20, `AuctionProductController` 19
  - `SellerPackageController` 14, `WholesaleProductController` 14,
    `PosController` 14, `ManualPaymentMethodController` 13,
    `Payment\PayfastController` 12, `ClubPointController` 11,
    `AuctionProductBidController` 11
  - `Payment\MpesaController` 8, `SmsTemplateController` 7
  - `OTP*` ×2, `PersonalInfoController`, `ConnectionController`, and others

  Delete these route files: `affiliate.php`, `refund_request.php`,
  `club_points.php`, `otp.php`, `offline_payment.php`, `african_pg.php`,
  `paytm.php`, `pos.php`, `seller_package.php`, `delivery_boy.php`,
  `auction.php`, `wholesale.php`. Also delete `install.php`/`update.php`, which
  are already unmapped.
- **The `/` redirect must never change.** It is pinned by `RootRedirectTest`,
  which hits the default test host. Bind **every store route** with
  `Route::domain(config('store.domain'))`, and register them **before** WMS's
  host-agnostic routes. Then the store host gets the storefront, and every
  other host, including the test host and the WMS host, still gets WMS and the
  redirect. Serving both from `albaraka-holland.nl` without host binding would
  turn `/` into a redirect loop.

### 6.5 Console

Legacy `Console\Kernel::schedule()` is empty. The 19 legacy commands run from
system cron, and `check:commands` polls `cron_jobs` every minute. Register the
survivors in `routes/console.php` next to `products:sync`, and settle the
products double-writer (§3) first. `test`, `test:mat` and `fix:members` are
one-off scripts and candidates for deletion.

### 6.6 Hardcoded upstream

`stores.otajer.com` is hardcoded in 14 legacy files. `SyncProductsCommand` reads
it from config. Converge on config.

### 6.7 Auth

- Guards and providers:
  - A `web` guard over `wms_users`, which is WMS as today.
  - A new `store` session guard over `users` (`StoreUser`), used by store
    route groups via `auth:store` / `Auth::shouldUse('store')`.
  - Sanctum's `guard` config lists both.
- Legacy `user_type` values: `customer`, `seller`, `admin`, `staff`,
  `delivery_boy`, `accountant`, `driver` (the `IsAdmin`/`IsSeller`/`IsDriver`/…
  middleware).
- If both hosts share a parent domain, give the session cookies different names.

### 6.8 Merging the `Product` models

- WMS casts `published` to bool. Legacy compares `== 1`, which still works.
- WMS relies on narrow `select()`s plus strict mode. Legacy reads arbitrary
  columns, so strict mode (`preventAccessingMissingAttributes`) would throw on
  any WMS query result that reaches legacy code.
- WMS's `ar_name` search FULLTEXT index is added by migration and must exist
  on the store table.

## 7. Phase plan

Effort estimates are from the counts above.

**The phases originally sketched were:** standalone upgrade → namespace →
composer → DB → files/routes/config → assets → auth/session → tests. **Proposed
changes:**

1. **Add phases 0a and 0b** before anything else.
2. **Prune before upgrading.** About 40% of the legacy code is dead, and
   upgrading it first is wasted work.
3. **Fold the "asset merge" into the file move.** There is no build step to
   merge (§5).
4. **Resolve the model/table decisions** (§2, §3) inside namespace remediation.
   The DB merge depends on them.

| # | Phase | Scope | Estimate |
| --- | --- | --- | --- |
| 0a | Incident response (owner, outside the repo) | §0.1–0.3: rotate secrets, clean the server, disable the addon installer, lock down `api/v2/warehouse`, move the docroot to `public/` | ops, not code |
| 0b | Capture production truth | `mysqldump --no-data` of prod, `business_settings` toggles, `addons` rows, `orders.payment_type` histogram, role and user_type lists, `FILESYSTEM_DRIVER`, and whether WMS product ids ⊆ store product ids | 1 short session |
| 1 | Prune legacy (in the legacy repo) | Delete: the 12 addon route files (228 routes), about 30 table-less models, the gateway controllers/views/utilities for each gateway §1 removes (about 40 files across `Payment/`, `Api/V2/`, `Utility/`, views), `AddonController`, the install/update flows, the malicious files, `webpack.mix.js`/`package.json`. Strip the 47 licence-check calls. Replace the laracon21 helpers. **Done on the upgrade branch:** the laracon21 helpers; every SDK-backed gateway's controllers, routes, views, checkout options and admin toggles; the malicious files and the addon installer, updater and installer (§0.1); the 12 addon route files (1379 → 1130 routes); the licence-check package (its 52 calls were already commented out); the Mix pipeline; `DemoController`; and the unreachable table-less models (§3). **Left:** the table-less models still reached from live code (§3), the ~150 `addon_is_activated()` branches (inert while `addons` is empty), the hand-rolled gateways (bKash, Nagad, SSLCommerz, Aamarpay, VoguePay, PayHere, N-Genius, ProxyPay, ToyyibPay) pending phase 0b, and 19 references to classes that were never in the repo (`OTPVerificationController`, `SellerPackageController`, `AffiliateController`, `ClubPointController`, `SmsUtility`, `PayfastUtility`, `Recaptcha` and others) | ~150 files deleted, ~40 edited. Medium |
| 2 | Upgrade legacy L8 → L13 **in the L13 skeleton shape** | Build `bootstrap/app.php`/`providers.php` from the Kernels and providers. Recreate the exception handling. Apply the surviving package bumps (§1). Rewrite intervention/image (6 files), laravel-pdf→dompdf (7), `Spatie\Permission\Middlewares`. Fix `env()` outside config (450 sites / 170 files). Fix PHP 8.x deprecations: `DB::raw` string casts (12). The `${var}` count was JS template literals, see §0.9. Needs PHP ≥ 8.4.1 to match WMS's lock. **Done:** the L8 → L13 version chain one major at a time, the spatie `Middleware` rename, PHP 8.2–8.5 deprecations, and Carbon 3 `diffIn*()` fixes (§0.7). **Left:** the skeleton shape (deferred to phase 6), intervention/image v3, laravel-pdf → dompdf, and `env()` outside config. Deploy it standalone against the prod DB only as a safety gate if the owner wants one | Large: the bulk of the effort |
| 3 | Namespace and model remediation | **`User` → `StoreUser` rename done** (§2.1: 139 files, not 151 — the estimate undercounted the bare-reference files a plain FQCN grep misses; the morph-type data plan is written but not run, no DB in scope). **Still open:** merge `Product` and `Upload` into single models, merge `Controller` and `AppServiceProvider`, replace the hardcoded role-id-1 lookups | ~180 files touched, mostly mechanical. Medium |
| 4 | Composer merge | Add the ~14 surviving packages to this repo. `composer.lock` regenerates in CI or a networked env (the sandbox can't install dev deps) | Small |
| 5 | DB merge | Target the **store's MySQL DB as the physical host**: it holds the live, larger data set. Import WMS's `wms_*` and domain tables into it. Add a guarded baseline migration for the store schema, following the `create_uploads_table` `hasTable()` pattern, generated from the 0b dump. It must also build on sqlite for CI. Move the legacy spatie and Sanctum rows into `wms_*` with id offsets and morph rewrites. Add `wms_permissions.section`. Pick the single products writer. Extend `SharedDatabaseTableNamesTest`'s inventory with every store table | Large and risky: needs a rehearsal on a prod copy |
| 6 | Files, routes, config and assets | Move `app/` (non-colliding), `Helpers.php` (autoload `files`), views, `public/assets`, and the surviving commands. Add store routes under `Route::domain()` in `routes/store/*.php`. Port config per §4. Add the `uploads` disk. Scope CSP, `api`-group middleware, `withoutWrapping` and CarbonImmutable per §6.1 | Large (moves are mechanical, the scoping is not) |
| 7 | Auth and session | Add the `store` guard and provider, the second password broker, Sanctum guard list, cookie names, and `APP_PREVIOUS_KEYS`. Session cutover plan | Medium |
| 8 | Tests | Pest feature tests per store route group. That covers auth, the role-gated admin/seller/staff areas, and the unauthorized-rejection cases CLAUDE.md requires for every gated action. It also covers the Flutter `api/v2` contract, especially the JSON wrapping shape. The legacy suite gives nothing to port (3 trivial tests). `RootRedirectTest` must pass untouched at every phase | Large: 1411 → roughly 1100 live routes to cover |

Estimate for phase 2 in more detail: ~210 controllers, 86 resources and 24
middleware exist today. After phase 1 roughly 400 PHP files remain to touch.

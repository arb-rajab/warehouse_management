# Localization migration: storefront, seller and admin stages

The shared branch is `codex/localization-migration`; keep its PR into `develop`
open for subsequent stages. This stage starts from `bf85892f`, the merge of
PR #511. Its APP_LOCALE fixes are inherited, not cherry-picked or duplicated.
The seller/admin stage continues from the actual storefront head `ff546a93`.
Verification is explicitly deferred by the user until the migration prompts
are complete; see the verification contract below.

## Completed boundary

Customer authentication/recovery, storefront pages, account pages, checkout,
orders, wallet, notifications, conversations and support use Laravel keys in
`lang/{en,ar}/storefront.php`. Shared required-field components, address fields,
uploader labels, image-preparation messages, error layouts and breadcrumbs use
files too. Static controller/service refusals and flashes in these flows use
the same catalog. Auth verification email actions and messages composed during
customer requests also use files. Laravel's existing auth, password, pagination
and validation catalogs remain authoritative for their messages.

`Language` resolves a valid explicit session choice before `config('app.locale')`
(APP_LOCALE). It does not persist an automatic default. The application locale
keeps the selected content code; the Laravel translator uses
`StoreLocale::uiLocale()`. This maps the legacy Arabic store code `sa` to `ar`
without changing its language row, product/category/CMS lookups, or the
DEFAULT_LANGUAGE content default. HTML language, direction, Vue store props,
display dates and numbers use that UI locale. APP_FALLBACK_LOCALE is preserved
and its legacy Arabic alias is normalized for file/Vue fallback.

Static file lookups never call `translate()`. That helper remains unchanged for
unmigrated consumers, including its existing database behavior. Existing
translation records are preserved. No schema, dependency, environment-file,
currency, rounding, timezone, guard or route changes are part of this stage.

Business-owned content deliberately retains database translation: product and
category names/descriptions, CMS content, configured navigation labels, coupon
headings and external-product button text. A populated custom menu can therefore
still query the translation table for its content. This is outside static UI
resolution and must not be removed by a blanket helper rewrite.

## Seller/admin stage completed

- Seller dashboard, layout/AIZ labels, navigation, profile and addresses, shop
  settings and verification, physical/digital product forms and variants, bulk
  upload instructions, uploaded-file screens, orders, payment history,
  withdrawals, coupons, notifications, conversations, reviews, product queries
  and support use file keys. New seller copy is organized in
  `lang/{en,ar}/seller.php`; existing shared storefront keys are reused.
- Seller controllers, registration/onboarding, seller/profile/coupon requests,
  byte-limit validation and import flashes use files. Shared reCAPTCHA messages
  now use `validation.recaptcha.*`; known validation attributes have matching
  English/Arabic labels. Shared upload/date validation preserves its rules and
  uses translated attribute names. Dynamic verification-form labels/options
  remain business-owned content.
- Order notifications interpolate code and translated status in one message;
  variant labels, move-attribute accessibility labels, map coordinates and
  transit time use placeholders. Upload-limit counts use plural forms. The
  seller shell reuses the existing uploader plural forms. Translated JavaScript,
  chart text and flash values use `Js::from`; digital form data attributes remain
  HTML escaped. Currency symbols, calculations, dates and timezone are unchanged.
- SMTP/configuration refusals, compatibility-controller flashes and attribute
  validation use `lang/{en,ar}/store_admin.php`. The two retained backend SKU
  fragments use shared file labels while preserving Blade. The existing Vue
  admin catalogs and components are unchanged.
- `StorePermissions::all()` remains the canonical identifier list. Display
  methods map its 182 application permission identifiers and 18 sections to
  `lang/{en,ar}/store_permissions.php`. They do not rename database records,
  change assignments or authorization, sync permissions, or seed translations.
  Unknown custom permission/section records keep content translation; custom
  role names still use `role_translations` and their selected content locale.
- Known payment methods in seller orders and converted admin visit details
  use files; custom methods retain content translation. Seller payment history
  preserves its raw custom-method display. Four product-query content calls
  (`user name`, `product name`, truncated question/reply) deliberately retain
  `translate()`; they are content, not static UI. Product, category, brand, shop,
  CMS, configured menus and business-setting content are unchanged.

Shared-service audit: `ProductVariantCombinations` is used by the admin and
seller product writers; only its stock-reconciliation message changed.
`SellerWithdrawService` is also used by API/V2: exception types, validation keys,
locking, balance arithmetic and response envelopes are preserved; its two shared
messages now use files. `ProductsImport` keeps its `notify` switch, spreadsheet
binding, data/persistence behavior and validation. Seller-created support email
subject/body reuse the already migrated storefront composition keys; the retained
email template remains for the document stage. No routes, guards, ownership
checks, uploads, dependencies, schemas, `.env`, seeders, DEFAULT_LANGUAGE or
translation records were changed by this stage.

## Remaining static consumers

| Group | Remaining consumers |
| --- | --- |
| Seller/admin UI | No remaining static `translate()` calls found in seller templates/controllers/requests or converted admin controllers/resources/services. Remaining calls on these surfaces are the explicitly retained dynamic product-query content and custom payment/permission fallbacks. Retained backend invoice/download templates are documented below. This is a source-inspection result, not runtime verification. |
| Emails/documents | `resources/views/shop/emails/{support,conversation,invoice,newsletter,auction_bid,app_verification}.blade.php`; backend invoice/download templates; `AppEmailVerificationNotification`, `PasswordResetRequest`, driver/order notifications and `NotificationUtility`. Customer-request message composition and auth verification/terms email actions migrated in this stage; other email/document UI remains for its stage. |
| API/mobile | `app/Http/Controllers/Api/V2/**`, seller/driver/trip API paths, `IsAppUserUnbanned`, and mobile notification/services. Existing API locale/content lookup and response contracts remain unchanged. Shared payment/cart/document services now resolve their static messages from files wherever reused. |

Additional deferred boundaries: `DriverService`, seller `OrderController` and
shared `OrderService` still compose Firebase/mobile notification title/body;
`NotificationUtility` composes order email text. `Store/Admin/SettingsController`
has a hardcoded SMTP-test email subject/body; treat it with emails. Retained
`backend/downloads/{brand,category,user}.blade.php` and
`backend/invoices/invoice.blade.php` keep their document text. API/V2 seller
responses (product, upload, coupon, withdrawal, shop, order and conversation),
customer/auth/catalog/payment, delivery and driver/trip messages still use the
legacy helper. Keep these for the email/document and API/mobile prompts.

Do not mistake `getTranslation()` model methods, configured menu/coupon
content, SVG transforms, or vendor JavaScript `translate` functions for static
database-backed UI labels.

## Verification contract

`StorefrontLocalizationTest` checks actual rendered Arabic on both login routes
and representative public/account pages with an empty translation table. Query
logging asserts no translation-table reads or writes during static rendering.
It also checks English, explicit/invalid sessions, `sa` content isolation,
fallback/direction, catalog keys/parameters, status vocabulary, interpolation,
shared error/auth email rendering and safe JavaScript serialization. A source
boundary assertion permits only retained dynamic helper calls in scoped
templates. The browser regression exercises Arabic login/flash JavaScript
through both login routes using the legacy Arabic language record.

`SellerLocalizationTest` adds rendered Arabic on representative seller screens,
empty-table/query assertions, static override isolation, English/session/sa and
fallback cases, content-language isolation, JavaScript serialization, whole
notification messages, validation/ownership/withdrawal/stock refusals, retained
variant rendering, plurals, reCAPTCHA and catalog/source boundaries.
`StorePermissionLocalizationTest` adds actual role-form label coverage for the
canonical permissions, custom records/role content, payment labels and the SMTP
refusal. Query helpers now live in `tests/Pest.php` for both migration suites.

**Deferred by explicit user instruction:** no tests, browser suites, pre-push
scripts, formatter/type-check workflows or attestation commands were run for
this stage. Review used source inspection, searches and `git diff --check`.
The earlier storefront development results are historical and do not verify
the seller/admin commit. The stopped storefront attestation was not restarted;
no CI-Verified trailer was created or fabricated. Intermediate commit/push is
explicitly authorized without that attestation. Keep PR #512 draft and open.

Reserve these checks until the user finishes all migration prompts:

```sh
php artisan test --compact tests/Feature/StorefrontLocalizationTest.php tests/Feature/SellerLocalizationTest.php tests/Feature/Store/StorePermissionLocalizationTest.php
php artisan test --compact --filter='Seller|StorePermissions|RoleController|NewsletterController|Recaptcha|ProductVariant'
php artisan test --compact --filter='DefaultLanguageConfig|StoreLocale|Language|RequiredFieldsRatchet|StoreAuthGuard|AuthGuardIsolation'
vendor/bin/pint --dirty --format agent
composer run types:check
npm run types:check
npm run test:unit
npm run build
npm run test:e2e
python3 .claude/scripts/pre_push_check.py --attest
```

The final attested run needs a clean tree and an unpushed final commit; follow
its actual prerequisites then. Record only observed results, and do not count
source inspection or earlier commits' checks as verification of the final tree.

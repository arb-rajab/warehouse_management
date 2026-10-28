# Localization migration: storefront, seller, admin and delivery/document stages

The shared branch is `codex/localization-migration`; keep its PR into `develop`
open for subsequent stages. This stage starts from `bf85892f`, the merge of
PR #511. Its APP_LOCALE fixes are inherited, not cherry-picked or duplicated.
The seller/admin stage continues from the actual storefront head `ff546a93`.
The delivery/document stage continues from the actual seller/admin head `b12995a5`.
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
email template is migrated by the delivery/document stage below. No routes, guards, ownership
checks, uploads, dependencies, schemas, `.env`, seeders, DEFAULT_LANGUAGE or
translation records were changed by this stage.

## Delivery/document stage completed

- All eight `resources/views/shop/emails` paths (support, conversation, auction
  bid, newsletter, invoice, app verification, verification and terms) now use
  file-based static labels and explicit language/direction metadata. Reuse the
  existing storefront auth/support keys; new copy is in matching
  `lang/{en,ar}/delivery.php`. Add password-reset/order-notification views and
  plain-text alternatives. Business-owned newsletter HTML, message/ticket
  details, terms/settings, product names and customer/shop information keep
  their existing print/sanitization/content rules.
- The six existing mail managers share `LocalizedMail`. Static subject/body
  keys and interpolation parameters survive queue serialization; translation
  happens when the message builds. Recipient `HasLocalePreference` is honored
  when available, with `sa` normalized to `ar`; otherwise use the configured
  UI default. Store users currently have no saved locale preference. An
  explicit locale can be pinned (SMTP tests use the requesting UI choice).
  Each mailable carries its locale before queueing and restores both the
  application locale and the separately selected translator locale afterward.
  Existing serialized payloads with custom/precomposed content are still accepted.
- Laravel mutates `app.locale` when switching a request/job's language.
  `app.ui_default_locale` captures the same APP_LOCALE configuration without
  that mutation, so delivery fallback cannot inherit a sender's browser or a
  preceding worker job. No new environment variable or `.env` edit is needed.
  DEFAULT_LANGUAGE and APP_FALLBACK_LOCALE retain their existing purposes.
- `AppEmailVerificationNotification`, `PasswordResetRequest`, existing
  verification/terms notifications and the dormant `OrderNotification::toMail`
  resolve static copy explicitly for the notifiable/explicit notification
  locale. They do not change the worker's global locale to compose text.
  New driver-trip notifications and order Firebase updates resolve the
  recipient locale; unordered-cart notifications pluralize for each admin.
  Existing type names, channels, routing, payload keys, raw status identifiers,
  recipient lists and historical records are preserved. User-authored pushes
  and historical/precomposed notification messages are not translated again.
- `backend/invoices/invoice`, `backend/downloads/{brand,category,user}` and
  `frontend/user/export-rep-payments` use an explicit document UI locale,
  language/direction metadata and readable Latin identifier spans. Both invoice
  controllers, both bulk-upload reference generators and both receipt export
  controllers pass that locale. A validated `en`, `ar` or legacy `sa` request
  session choice wins; otherwise use the immutable configured UI default.
  Dynamic category/brand translation still uses the selected content code.
- Store documents keep mPDF and its bundled DejaVu Sans Arabic/OpenType support.
  TripService keeps TCPDF/FPDI and the existing fixed CMR template, coordinates,
  barcode/signature content and filenames. Its generated headings, instructions
  and interpolated labels now use file keys and language metadata. The CMR
  coordinate system remains LTR because mirroring it would misalign the fixed
  form; TCPDF's existing DejaVu shaping handles Arabic overlay text. Printed
  labels baked into the externally supplied CMR PDF remain part of that form,
  rather than application translation-table consumers.
- The human-facing reference PDF headings are localized. `ProductsExport`
  spreadsheet column names are import identifiers and remain byte-for-byte
  unchanged. QR/barcode labels contain identifiers, not translatable headings.
  Amount calculations, tax/total visibility, currency/precision, timezone,
  identifiers, download routes, receipt/attachment filenames and authorization
  are preserved. Invoice-email sending remains commented out; this stage does
  not enable it or add an attachment/delivery channel.

## Remaining consumers and boundaries

| Group | Remaining consumers |
| --- | --- |
| Seller/admin/customer UI | No remaining active static database-helper calls found in the migrated surfaces. Dynamic product-query content, configured navigation/CMS/coupon content and custom payment/permission fallbacks intentionally remain. Commented legacy markup and SVG transforms are not active static consumers. This is source inspection, not runtime verification. |
| API/mobile | `app/Http/Controllers/Api/V2/**` customer/auth/catalogue/cart/checkout/payment/address/wallet/chat/auction/upload messages; seller product/shop/order/coupon/conversation/upload/package/refund/withdrawal messages; delivery and driver/trip responses; `IsAppUserUnbanned`; static labels in `app/Http/Resources/V2/**`. Only their shared email/notification composition was migrated here. Existing response envelopes/statuses and content locale behavior remain intact. `NotificationUtility::sendPushNotificationToUser` also retains its API response message `Notification status` for that stage. |
| Translation management | `Store/Admin/LanguageController` still reads/writes/imports/exports `translations` and `app_translations`; its requests, models, cache invalidation and mobile language endpoints remain dependent on them. These tables and the legacy `translate()` helper must remain until their consumers are migrated. |
| Intentional database content | `getTranslation()` model methods, custom role/permission/payment names, products/categories/brands/shops, CMS, configured menus/coupon headings, business settings and user-authored content retain existing behavior. These are separate from static application vocabulary. |
| Documents | Runtime/visual review of Arabic emails and both PDF engines is deferred. The external fixed CMR form's preprinted labels and business/company literals are preserved; only generated static overlay vocabulary is localized. |

Do not mistake `getTranslation()` model methods, configured menu/coupon content,
SVG transforms, Laravel validation's `->translate()` or vendor JavaScript
`translate` functions for static database-backed UI labels. Legacy helper calls
remaining outside the intentional content list are predominantly API/V2 and its
resources/middleware; do not remove the tables/helper during this stage.

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

`DeliveryLocalizationTest` adds queued mailable serialization and consecutive
recipient locale isolation, HTML/text subjects and bodies, empty-table and
stored-override isolation, safe names/URLs/content interpolation, recipient
preferences/default/sa handling, serialized auth notifications, unchanged
order/cart/trip payload contracts, plural cart copy, document metadata and
content-language separation, real mPDF generation, invoice calculations,
receipt values and unchanged spreadsheet headers. Existing storefront auth,
password-reset, newsletter/SMTP and bulk-reference tests are updated for deferred
message composition and explicit document locale. These tests are unexecuted.

**Deferred by explicit user instruction:** no tests, browser suites, builds, pre-push
scripts, formatter/type-check workflows or attestation commands were run for
the seller/admin or delivery/document stages. Review used source inspection, searches and `git diff --check`.
The earlier storefront development results are historical and do not verify
the seller/admin or delivery/document commits. The stopped storefront attestation was not restarted;
no CI-Verified trailer was created or fabricated. Intermediate commit/push is
explicitly authorized without that attestation. Keep PR #512 draft and open.

Reserve these checks until the user finishes all migration prompts:

```sh
php artisan test --compact tests/Feature/StorefrontLocalizationTest.php tests/Feature/SellerLocalizationTest.php tests/Feature/DeliveryLocalizationTest.php tests/Feature/Store/StorePermissionLocalizationTest.php
php artisan test --compact tests/Feature/LegacyStoreInvoiceTest.php tests/Feature/LegacyStorePasswordResetTest.php tests/Feature/Console/CheckUnorderedCartsCommandTest.php tests/Feature/Store/Admin/ProductBulkUploadControllerTest.php tests/Feature/Store/Admin/RepresentativePaymentControllerTest.php
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

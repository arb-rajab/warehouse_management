# Localization migration: storefront, seller, admin, delivery/documents and API/mobile

The shared branch is `codex/localization-migration`; keep its PR into `develop`
open for subsequent stages. This stage starts from `bf85892f`, the merge of
PR #511. Its APP_LOCALE fixes are inherited, not cherry-picked or duplicated.
The seller/admin stage continues from the actual storefront head `ff546a93`.
The delivery/document stage continues from the actual seller/admin head `b12995a5`.
The API/mobile stage continues from the fetched delivery/document head `236a502b`;
the reported head was confirmed against the existing draft PR #512 before editing.
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

## API/mobile stage completed

- Repository-owned API/V2 static responses now use matching English/Arabic
  `lang/{en,ar}/api.php`, reusing storefront, seller and Laravel validation keys.
  Covered paths include authentication/recovery, accounts/representatives/visits,
  addresses, cart/checkout/coupons/wishlist, payments/wallet/packages/refunds,
  orders/reviews, seller products/shop/orders/coupons/conversations/uploads and
  withdrawals, customer support/chat, delivery-boy and driver/trip responses,
  product/offer lookups and warehouse customer/order responses. Shared services
  retain their earlier file messages. `NotificationUtility`'s API response
  `Notification status` is also file-backed; push payload contracts are unchanged.
- Cart minimum/maximum/available-quantity messages use complete messages and
  plural forms, preserving stock counts, product names and variant text. Delivery
  status response text interpolates a display label; the underlying status stays
  unchanged. Validation fields, rules, authorization, ownership, provider-supplied
  errors, calculations, response nesting/types/statuses and deliberately empty
  response messages are preserved.
- V2 resources use file labels for Free, Verified/Non-Verified seller, In House
  Product, payment/delivery/shipping display statuses, refund/approval labels,
  missing-user/order placeholders, transaction captions and price ranges. New
  resource-specific labels live in `api_resources.php`; known status vocabulary
  reuses storefront keys. `ApiResourceLabel` preserves the prior humanized value
  for unknown/custom statuses. Raw enum/status/shipping values, identifiers,
  product/package/shop names and descriptions, custom method names, pagination,
  currency/precision, timestamps and authorization are unchanged. Existing
  humanized `payment_type`/`payment_method` fields without separate machine codes
  remain stable; their external client usage is unknown. The auction resource's
  mixed timestamp/`Ended` sentinel is preserved for the same reason. Arabic Free
  is corrected to the natural price label `مجاني` in the shared catalog.
- `IsAppUserUnbanned`, driver/delivery role refusals and the warehouse token
  refusal use files. Ban token revocation, `result_key: banned`, the 403 response,
  role checks and the warehouse refusal's legacy HTTP 200/body `status: 401`
  contract remain intact. Existing minimum-app-version messages already use files.

### API locale-header behavior

`SetLocaleFromHeader` still reads `Accept-Language` for the API group. It accepts
supported English/Arabic regional choices in quality order, maps legacy `sa` to
Arabic UI, rejects malformed/unsupported tokens and malformed quality weights,
and skips zero-quality choices.
Missing, wildcard-only and unsupported values use the immutable
`app.ui_default_locale`, rather than an earlier request's mutable `app.locale`.
The translator's configured fallback retains `APP_FALLBACK_LOCALE` with `sa`
normalized for file lookup.

Store API routes still apply `AppLanguage` afterward: **App-Language retains
precedence over Accept-Language**, including when absent or invalid. Valid exact
codes are `en`, `ar` and legacy `sa`; lists, paths, unsupported codes and uppercase
App-Language values do not select a locale. Valid explicit codes keep their
content-language value (including `sa`) and use `ar` for Arabic static UI. With
no valid App-Language, content lookups keep `app.default_language`
(`DEFAULT_LANGUAGE`), while static messages use the immutable UI default. No
stored language rows or content-language keys are renamed. Global/API middleware
ordering and authentication behavior are unchanged.

`StoreLocale::withLocale` follows the delivery stage's scoped restoration
mechanism: snapshot application locale, translator locale and translator fallback,
then restore each in `finally`, including exceptions and nested middleware.
The separately configured UI default is never mutated. Existing queued delivery
locale capture/restoration remains intact.

### Mobile language/catalog and ARB boundary

The only repository-owned public language endpoint is
`GET /temp/api/v2/languages` (`Api/V2/LanguageController::getList`). It returns
language records, **not a translation catalog**. Its `data` fields remain
`id`, `name`, `code`, `mobile_app_code`, `rtl`, `is_default`, `image`, plus
`success`/`status`. Configured language names are content; stored codes, app codes,
default flags and booleans are preserved.

`Store/Admin/LanguageController` owns the existing mobile catalog screens and
ARB import/export. Export is flat JSON with unescaped Unicode and exact stored
mobile keys, downloaded as `app_<sanitized app_lang_code>.arb` with JSON content
type. Exact regional codes such as `ar_SA` stay distinct from `ar` and `sa`;
empty catalogs still serialize as `[]`. Import accepts arbitrary string keys and
string values within the existing limits, into the English app catalog; it does
not accept nested ARB metadata objects. Catalog screens retain English-key
pagination/search, destination app code, IDs and `id/key/source/value` fields.

No production ARB files, mobile key declarations, external client source or
runtime mobile catalog reader exist in this checkout. Synthetic test keys do not
establish installed clients' vocabulary. Consequently **no file-backed mobile
catalog adapter is claimed in this stage**: creating one without that inventory
would invent keys or silently drop supported clients' keys. Existing DB-backed
management/export remains compatible and has new unexecuted contract tests.
External follow-up must collect versioned customer/seller/delivery client ARB
catalogs and usages, including exact keys, interpolation syntax, locale codes and
fallback expectations. Then commit complete matching English/Arabic mobile
catalogs and an explicit legacy-key-to-file-key reader/export adapter. Files
must be authoritative for mapped static keys; unknown catalogs must remain
available until their supported consumers are accounted for.

## Remaining consumers and boundaries

| Group | Remaining consumers |
| --- | --- |
| Seller/admin/customer UI | No remaining active static database-helper calls found in the migrated surfaces. Dynamic product-query content, configured navigation/CMS/coupon content and custom payment/permission fallbacks intentionally remain. Commented legacy markup and SVG transforms are not active static consumers. This is source inspection, not runtime verification. |
| API/mobile | No remaining active static database-helper calls in API/V2 controllers, scoped requests/services, API middleware or V2 resources. `V2/LanguageCollection` deliberately retains content translation of configured language names. External client catalogs remain unestablished; DB-backed ARB management/export is retained as described above. |
| Translation management: active DB catalog consumers | `Store/Admin/LanguageController::{show,storeTranslations}` uses `translationPage(false)`/`saveTranslations(false)` for general translations. `::{showAppTranslations,storeAppTranslations,importAppTranslations,exportAppTranslations}` reads/writes mobile catalogs through `translationPage`, `saveTranslations`, `writeTranslation`. `UpdateTranslationsRequest::rules` validates keys against English DB rows; `ImportAppTranslationsRequest::{after,translations}` validates/returns arbitrary imported JSON keys. Retain management routes in `routes/shop-admin.php`, permission gates, Vue language screens/forms, pagination, `translations`/`app_translations` tables and `translations-<locale>` cache invalidation until final cleanup decisions and external-client mapping. |
| Intentional database content | `getTranslation()` model methods, custom role/permission/payment names, products/categories/brands/shops, CMS, configured menus/coupon headings, business settings and user-authored content retain existing behavior. These are separate from static application vocabulary. |
| Documents | Runtime/visual review of Arabic emails and both PDF engines is deferred. The external fixed CMR form's preprinted labels and business/company literals are preserved; only generated static overlay vocabulary is localized. |
| Retained static legacy templates without repository callers | `resources/views/shop/partials/coupons/{cart_base_coupon,cart_base_coupon_edit,product_base_coupon,product_base_coupon_edit}.blade.php` contain 44 static helper calls. No include/view/controller/test reference to these partial names was found. They are potential externally invoked legacy consumers, not established active runtime paths; confirm external integrations before retiring them. |
| Unused cleanup candidates | `TranslationUtility::{getInstance,reInstantiate,cached_translation_row,getAllTranslations}` and the `AppTranslation` model have no repository caller. Review/retire in cleanup; do not count them as current runtime consumers. |

Do not mistake `getTranslation()` model methods, configured menu/coupon content,
SVG transforms, Laravel validation's `->translate()` or vendor JavaScript
`translate` functions for static database-backed UI labels. Legacy helper calls
remaining outside the intentional content list are the retained coupon partials
and DB catalog management; do not remove the tables/helper during this stage.

The exact remaining active global `translate()` content call sites are:
`StorePermissions::{displayName,displaySection}` for custom permission/section
records; `StoreLocale::paymentMethod` for custom payment methods (invoice,
seller order detail and VisitDetailResource consumers); `V2/LanguageCollection`
for configured names; seller product-query index for user/product names and
truncated question/reply (four calls); frontend index for configured coupon
title/subtitle (two); frontend navigation for configured desktop/mobile menu
labels (two); product-details/add-to-cart external button text (two). Dedicated
model `getTranslation()` and content-translation tables remain intentional.
The helper itself (`app/Http/Helpers.php`) still allows DB overrides and inserts
missing English content keys; no migrated static path calls it. Final cleanup
must retain or deliberately replace those content behaviors before removing it.

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

`ApiMessageLocalizationTest`, `ApiResourceLocalizationTest` and
`ApiLocaleLocalizationTest` add English/Arabic and legacy `sa` API/refusal/resource
coverage with an empty translations table and stored-override isolation; safe
quantity/product/variant/transaction interpolation; unchanged envelopes, raw
statuses, ownership, token revocation and empty messages; malformed/supported
headers, precedence, default/content separation and nested/consecutive/exception
locale restoration. Existing `SetLocaleTest` assertions now inspect localized
responses and restored surrounding state. `Store/Admin/LanguageControllerTest`
adds arbitrary mobile key/interpolation, ARB format/filename, regional isolation
and empty-export compatibility. These additions remain unexecuted.

**Deferred by explicit user instruction:** no tests, browser suites, builds, pre-push
scripts, formatter/type-check workflows or attestation commands were run for
the seller/admin, delivery/document or API/mobile stages. Review used source inspection, searches and `git diff --check`.
The earlier storefront development results are historical and do not verify
the seller/admin, delivery/document or API/mobile commits. The stopped storefront attestation was not restarted;
no CI-Verified trailer was created or fabricated. Intermediate commit/push is
explicitly authorized without that attestation. Keep PR #512 draft and open.

Reserve these checks until the user finishes all migration prompts:

```sh
php artisan test --compact tests/Feature/StorefrontLocalizationTest.php tests/Feature/SellerLocalizationTest.php tests/Feature/DeliveryLocalizationTest.php tests/Feature/Store/StorePermissionLocalizationTest.php
php artisan test --compact tests/Feature/ApiMessageLocalizationTest.php tests/Feature/ApiResourceLocalizationTest.php tests/Feature/ApiLocaleLocalizationTest.php tests/Feature/SetLocaleTest.php tests/Feature/SetLocaleFromHeaderTest.php tests/Feature/Store/Admin/LanguageControllerTest.php
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

# Localization migration: storefront, seller, admin, delivery/documents and API/mobile

The shared branch is `codex/localization-migration`; keep its PR into `develop`
open for subsequent stages. This stage starts from `bf85892f`, the merge of
PR #511. Its APP_LOCALE fixes are inherited, not cherry-picked or duplicated.
The seller/admin stage continues from the actual storefront head `ff546a93`.
The delivery/document stage continues from the actual seller/admin head `b12995a5`.
The API/mobile stage continues from the fetched delivery/document head `236a502b`;
the reported head was confirmed against the existing draft PR #512 before editing.
The final repository cleanup continues from the fetched API/mobile head
`285406aa9b9e2fbd4d3ed67021bcbaea21da5524`, also confirmed against PR #512.
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

Static file lookups never call `translate()`. That helper retains its existing
database behavior for business-owned content; its docblock records that boundary. Existing
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

## Final repository cleanup completed

- Preserve all four `resources/views/shop/partials/coupons/` templates and migrate
  their 44 static calls (13/11/10/10 respectively) to storefront/seller file keys.
  Four new matching English/Arabic coupon keys cover the legacy headings/date
  placeholder. No variable, field name, option value, identifier, selected-value,
  date/currency behavior or JavaScript initialization changes. Active seller AJAX
  endpoints return `shop.seller.coupons.fields`, and admin coupon actions render
  Inertia; neither establishes retirement of possible external use of the four
  partials. Unknown external use is handled by preserving their contracts.
- Remove `app/Utility/TranslationUtility.php` and `app/Models/AppTranslation.php`,
  with both entries in `pint.json` and `phpstan.neon`. The utility only instantiated
  itself; the active helper uses `Translation` directly. The catalog controller
  uses `DB::table`, with no model relationship, provider/binding, configuration,
  route or autoload consumer of either deleted class. Tables/records remain.
- Language Index/Translations titles and help now distinguish content/custom
  labels, file-managed static UI and compatibility mobile catalogs in English and
  Arabic. Mobile pages display the exact `app_lang_code`, including regional codes.
  General catalog rows can include historical UI entries; help explicitly states
  they no longer control migrated UI. Import help describes the English mobile
  catalog. `LanguageController`, both translation requests, routes, permission
  `language_setup`, input rules, payloads, search, pagination, saves, cache
  invalidation and downloads remain unchanged. No replacement static DB editor.
- Add the missing English `seller.common.order` counterpart to an existing Arabic
  entry, keeping seller catalog keys aligned. No locale resolver, header/session,
  queue, fallback, content-language or locale-restoration behavior changes here.

## Exact final runtime database consumer inventory

These are retained intentional dependencies, based on source inspection of the
actual branch, not executed verification. There are **14** global-helper content
calls; no repository-owned static literal helper calls remain in owned runtime
PHP/Blade sources. Mobile catalogs are still DB-backed and are not counted as a
completed file migration.

| Path / consumer | Calls | Classification and justification |
| --- | --- | --- |
| `app/Support/StorePermissions.php::{displayName,displaySection}` | 2 | Custom permission/section content only; canonical identifiers use files. `Store/Admin/RoleController` consumes the display methods. |
| `app/Support/StoreLocale.php::paymentMethod` | 1 | Custom payment-method content; invoice, seller order detail and `Store/VisitDetailResource` consume it. Known methods use files. |
| `app/Http/Resources/V2/LanguageCollection.php` | 1 | Configured language-name content, returned by `Api/V2/LanguageController::getList` at `/temp/api/v2/languages`; metadata, not a mobile translation catalog. |
| `resources/views/shop/seller/product_query/index.blade.php` | 4 | Customer name, product name, truncated question and reply, rendered by seller product-query index; preserve user/product content. |
| `resources/views/shop/frontend/index.blade.php` | 2 | Configured `cupon_title` / `cupon_subtitle`. |
| `resources/views/shop/frontend/inc/nav.blade.php` | 2 | Configured desktop/mobile `header_menu_labels` values. |
| `resources/views/shop/frontend/product_details/details.blade.php` | 1 | Configured external-product button text. |
| `resources/views/shop/frontend/partials/addToCart.blade.php` | 1 | Configured external-product button text in the AJAX fragment. |

The underlying runtime catalog consumers are:

| Path / consumer | Remaining database behavior and reason |
| --- | --- |
| `app/Http/Helpers.php::translate`, `app/Models/Translation.php` | Read `translations`, insert missing English content keys, retain `translations-en`, `translations-<requested content locale>` and `translations-<DEFAULT_LANGUAGE>` caches and fallback. Necessary for all 14 calls above. |
| `Store/Admin/LanguageController::{show,storeTranslations}` | `translationPage(false)` / `saveTranslations(false)` / `writeTranslation` read/write general content/custom-label catalog. English-key pagination/search, target-language values and `translations-<code>` invalidation remain. |
| `Store/Admin/LanguageController::{showAppTranslations,storeAppTranslations,importAppTranslations,exportAppTranslations}` | `translationPage(true)` / `saveTranslations(true)` / `writeTranslation` and direct export read/write `app_translations` for mobile compatibility. Exact external keys/locales and existing JSON/download contracts remain. |
| `UpdateTranslationsRequest::rules` | Validates submitted keys against English rows in the selected general/mobile table. No file-key adapter. |
| `ImportAppTranslationsRequest::{after,translations}` | Validates and returns flat uploaded mobile JSON keys/string values before controller writes; does not itself query a translation table. |
| `resources/js/pages/Store/Admin/Languages/{Index,Translations}.vue` and `routes/shop-admin.php` language/catalog actions | Existing authorized management UI/route consumers of those controller/request paths. Keep exact route names and the `language_setup` permission gate. |

Dedicated model/content paths are also deliberately retained; they do not use
the general static UI catalog:

- `getTranslation()` on `Attribute`, `Brand`, `Category`, `City`,
  `CustomerPackage`, `CustomerProduct`, `FlashDeal`, `Page`, `PickupPoint`,
  `Product`, `Role`, `SellerPackage`; preserve their dedicated translation tables,
  relations and writers/importers. Direct content readers include
  `Store/{WebsitePageResource,PickupPointResource,CityResource}` and admin address
  lookup. Product/category/brand/package/CMS/custom-role content remains dynamic.
- `app/Http/Helpers.php::get_setting` retains language-sensitive `BusinessSetting`
  content and `business_settings` caching. Configured shop/settings/newsletter,
  verification forms and user-authored content retain their existing content
  selection and sanitization rules; they are not static UI translation consumers.
- `DEFAULT_LANGUAGE` remains `app.default_language`, the content-language default.
  No stored `sa`/`ar` rows, model content locales or exact mobile codes are renamed.

Do not classify SVG/CSS/canvas transforms, Laravel validation's `->translate()`,
comments or vendor JavaScript translation functions as global helper consumers.
The external CMR form's preprinted labels are document assets, not DB consumers.

## Unresolved external mobile inputs

The fetched branch and tracked repository still contain no authoritative `.arb`,
`.dart`, mobile l10n inventory or runtime client catalog reader. Synthetic test
keys are not production inventories. Before a further implementation stage, obtain:

1. Versioned complete **customer, seller and delivery** client catalogs and actual
   key usages for each supported/released client version, with obsolete and still
   supported keys identified. Include complete English and Arabic catalogs, all
   regional variants, and the full supported-locale manifest; exact production
   codes cannot be inferred from this repository (`ar`, `ar_SA`, `sa` are distinct).
2. Exact case/punctuation-sensitive keys, values and interpolation/plural syntax,
   placeholder types and escaping, ARB metadata requirements, and generation/lookup
   behavior. Existing import accepts flat strings only (5 MiB, 5,000 entries,
   nonempty keys up to 255 characters and string values up to 255 characters,
   including empty values), not nested ARB metadata; a future adapter must not
   silently discard unsupported metadata.
3. Client locale negotiation, normalization, fallback/missing/empty-key behavior,
   catalog load/update paths and backward-compatibility requirements. Confirm
   whether download names and the existing empty `[]` shape are consumed directly.

Then a follow-up implementation must commit complete file catalogs and explicit
lossless legacy-key mappings, making files authoritative for mapped static keys
while retaining unmapped supported client vocabulary. Current exports preserve
flat Unicode JSON, exact arbitrary keys/regional codes, sanitized
`app_<app_lang_code>.arb` names, JSON content type and `[]` empty exports. No adapter
or invented mobile key inventory was added. Repository static cleanup is complete;
**another mobile implementation prompt is required after these external inputs**
before declaring the entire localization migration complete and merging this PR.

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

Final cleanup extends `SellerLocalizationTest` with all four retained coupon
fragments in English, Arabic and legacy `sa`: every label, form/selection/date
contract, escaped product/code content, model content-locale selection, empty
general catalog, database override isolation and no general translation queries.
The existing catalog/source test now includes the coupon partials. Language Vue
tests cover distinct content/mobile help and titles in both locales, exact mobile
regional code display, and unchanged action/form/permission contracts.
`LanguageControllerTest` additionally pins custom-label cache refresh while
historical DB UI overrides cannot change file text or other locale caches.

`LocalizationBoundaryTest` scans owned PHP/Blade source under app/resources/routes/
config/bootstrap, with exact file/expression/count exceptions for the 14 content
calls and retained catalog helper/model/controller/request behavior. No directory
is exempted for dynamic or mobile consumers; new calls in those same files fail.
PHP tokenization and Blade compilation distinguish executable PHP from comments,
SVG transforms, JavaScript methods and Laravel validation methods. Scanner tests
pin added calls, case variants, helper aliases, model access and raw SQL. This is
a source regression boundary, not arbitrary dynamic-call/data-flow analysis or
proof about unavailable external client code. Coverage was added during the staged migration;
final verification is now authorized as described below.

**Historical stage verification:** no tests, browser suites, builds, pre-push
scripts, formatter/type-check workflows or attestation commands were run for
the seller/admin, delivery/document, API/mobile or final repository cleanup stages.
No manually triggered CI was requested. Review used source inspection, searches
and `git diff --check`.
The earlier storefront development results are historical and do not verify
the seller/admin, delivery/document or API/mobile commits. The stopped storefront attestation was not restarted;
no CI-Verified trailer was created or fabricated. Intermediate commit/push is
explicitly authorized without that attestation. The user has now authorized final
verification of the combined migration. PR #512 is open for review; merge remains
conditional on observed final verification and repository merge requirements.

Run the consolidated checks as part of final verification:

```sh
php artisan test --compact tests/Feature/LocalizationBoundaryTest.php
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

Consolidated final-verification checklist:

- Run the targeted suites above and the complete required backend/frontend suites,
  including language screen component tests, permission/ownership refusals, content
  and mobile catalog CRUD/import/export, schema-sensitive checks and the
  required-fields ratchet. Use prepared isolated test databases, never seed
  production or change its catalogs as verification setup.
- Confirm English/Arabic catalog keys and interpolation parity; empty translation
  tables/stored legacy overrides cannot affect static UI, while custom labels and
  model content remain editable and cached. Check exact ARB keys, regional codes,
  Unicode/flat JSON, download filenames/content type, empty `[]`, input limits,
  invalid-file atomicity and denied callers leaving data unchanged.
- Confirm locale defaults, session override, App-Language precedence over
  Accept-Language, malformed/weighted headers, `sa` UI mapping/content isolation,
  translator fallback, nested/error restoration and consecutive queue recipients.
- Run builds/types/formatters/browser coverage and review Arabic/RTL storefront,
  seller and language-management text with safe JavaScript interpolation.
- Complete the **earlier deferred Arabic email/PDF/CMR visual review**: HTML and
  plain-text mail, subjects and queued deliveries; mPDF invoices/reference PDFs/
  receipts; TCPDF/FPDI CMR overlays. Inspect shaping, bidi/Latin identifiers,
  wrapping, page breaks, totals/currency, fixed-form overlay positions, barcode/
  signatures and unchanged downloads. The external CMR's preprinted labels remain
  supplied-document content, not application labels to translate.
- External mobile application catalogs are outside this PR by explicit user
  instruction because their source is not in this repository. Keep existing
  server-side mobile catalog management/import/export compatible and verify
  those contracts. Migrating external client catalogs is a separate future scope,
  not an outstanding implementation dependency for this PR.

The final attested run needs a clean tree and an unpushed final commit; follow
its actual prerequisites then. Record only observed results, and do not count
source inspection or earlier commits' checks as verification of the final tree.

Final verification review found and corrected mixed-direction CMR identifiers:
Arabic labels now preserve the email and leading phone `+` in the generated PDF,
with English/Arabic PDF extraction regression coverage. Arabic invoice and CMR
overlay samples were visually inspected, and Arabic support email HTML/plain text
were reviewed. Full fixed-form CMR alignment remains unverified because the supplied
`storage/app/PrintCMR-PDF-EN_2.pdf` is absent from this workspace; the overlay review
does not establish alignment against that external form.

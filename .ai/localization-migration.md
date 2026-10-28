# Localization migration: storefront stage

The shared branch is `codex/localization-migration`; keep its PR into `develop`
open for subsequent stages. This stage starts from `bf85892f`, the merge of
PR #511. Its APP_LOCALE fixes are inherited, not cherry-picked or duplicated.

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

## Remaining static consumers

| Group | Remaining consumers |
| --- | --- |
| Seller panel | `resources/views/shop/seller/**`; `app/Http/Controllers/Seller/**`; seller request refusals and product/profile/shop forms. Shared form/upload components already use files. Seller layout's remaining AIZ labels and sidebar/account text still use the legacy helper. |
| Admin | Retained backend product variant partials and documents; `Store/Admin/NewsletterController.php` SMTP refusal; `HomeController.php` top-category update flash; `SubscriberController.php` deletion flash; legacy `AttributeValueController.php`, `BusinessSettingsController.php`, `CouponRequest.php`, `AttributeValueRequest.php`. Converted Vue admin labels remain in `resources/js/locales/{en,ar}.json`. Role/permission labels translated as database-owned data need a separate classification before migration. |
| Emails/documents | `resources/views/shop/emails/{support,conversation,invoice,newsletter,auction_bid,app_verification}.blade.php`; backend invoice/download templates; `AppEmailVerificationNotification`, `PasswordResetRequest`, driver/order notifications and `NotificationUtility`. Customer-request message composition and auth verification/terms email actions migrated in this stage; other email/document UI remains for its stage. |
| API/mobile | `app/Http/Controllers/Api/V2/**`, seller/driver/trip API paths, `IsAppUserUnbanned`, and mobile notification/services. Existing API locale/content lookup and response contracts remain unchanged. Shared payment/cart/document services now resolve their static messages from files wherever reused. |

The customer-facing shop-registration path still has legacy seller-onboarding
responses in `ShopController` / `StoreShopRequest`; include those with the seller
stage. Do not mistake `getTranslation()` model methods, configured menu/coupon
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

Run the affected tests and the repository's genuine attested pre-push run before
pushing. Record actual results in the PR; do not handwrite CI-Verified trailers.

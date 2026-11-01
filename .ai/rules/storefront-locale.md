---
paths:
  - 'app/Http/Middleware/Language.php'
  - 'app/Http/Controllers/Store/LanguageController.php'
  - 'app/Support/StoreLocale.php'
  - 'resources/views/shop/frontend/inc/nav.blade.php'
  - 'resources/views/shop/frontend/partials/language_toggle.blade.php'
  - 'tests/Feature/StorefrontLanguageToggleTest.php'
---

# Storefront locale: Arabic/English toggle, session, then cookie

## How the storefront's language is chosen
The Blade storefront under `/temp` resolves its locale in `Language` (the
store's web middleware): a supported session `locale` first, then the
`store_locale` cookie (`StoreLocale::COOKIE`), then `config('app.locale')`.
Neither fallback is written back to the session. `AppLanguage` (API,
`App-Language` header) and the WMS `SetLocale` never read the cookie.

## The header toggle
`shop/frontend/partials/language_toggle.blade.php` is the only storefront
language control, included twice in `inc/nav.blade.php` (desktop top bar,
mobile sidebar) behind the existing `show_language_switcher` setting. It is a
plain CSRF form POST to `language.toggle`, no theme JavaScript, naming the
other language natively ("العربية" / "English").

`LanguageController::toggleLanguage` accepts only `StoreLocale::TOGGLE_LOCALES`
(`ar`, `en`; not the content code `sa`), stores
`StoreLocale::contentCodeFor()` (an active `languages` row whose code is the
locale, else one whose `app_lang_code` maps to it, such as the legacy `sa`
Arabic row, else the bare locale) in the session and a one-year cookie, and
redirects only to a local absolute path from the `redirect` field, else to
`home`. Keep the stored value a content code: product, category and CMS
translations are keyed on it. `language.change` (AJAX, any language row) stays
for the seller panel and its consumers. Tests:
`tests/Feature/StorefrontLanguageToggleTest.php`.

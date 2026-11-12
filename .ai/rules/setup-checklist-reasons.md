---
paths:
  - 'app/Services/Store/SetupChecklist.php'
  - 'resources/js/components/StoreSetupChecklist.vue'
  - 'resources/js/components/StoreHighlight.vue'
  - 'resources/js/components/StoreServerSettingsStatus.vue'
  - 'app/Http/Controllers/Store/Admin/{SettingsController,ShippingConfigurationController,GeneralSettingsController,WebsiteHeaderController,WebsiteFooterController,GoogleMapController,CurrencyController,LanguageController}.php'
  - 'resources/js/pages/Store/Admin/Dashboard/**'
  - 'resources/js/pages/Store/Admin/Settings/**'
  - 'resources/js/pages/Store/Admin/Setup/**'
  - 'resources/js/pages/Store/Admin/Website/{Header,Footer}.vue'
  - 'resources/js/pages/Store/Admin/Languages/Index.vue'
---

# A setup or readiness check names the missing setting and links to it

A setup checklist item, readiness check or similar "is this configured?" signal must never show a bare "not done". An admin once saw "Shipping" not done while every shipping field looked set: the page showed `0` for a cost never saved and the first shipping type for a type never chosen, and nothing said which setting the check wanted.

## How
- **Every failing check returns reasons.** `SetupChecklist::items()` gives each item `reasons: [{key, route, highlight}]`, empty exactly when the check is done. `key` is `<check>.<exact setting>` (`shipping.flat_rate_cost`, `siteBasics.header_logo`); `route` is the Laravel route of the page that sets it; `highlight` is the key that page marks, or null on a list page with no single option. Register each reason in `SetupChecklist::TARGETS`. Several missing parts give several reasons.
- **A reason may carry `params`.** Only when its text names something that varies:
  the two language reasons carry `params: {code}` (the language in use; "-" when none
  is configured), set by `SetupChecklist::language()`, and `StoreSetupChecklist.vue`
  passes them to the text (`{code}`). Every other reason keeps the plain
  `{key, route, highlight}` shape.
- **Words in both languages.** Each reason has text under `store.dashboard.checklist.reasons.<key>` in `resources/js/locales/en.json` and `ar.json`, naming the setting and, when it differs, the saved state it depends on (the shipping type, the server variable).
- **Link and highlight with the existing mechanism.** The dashboard links each reason with `?highlight=<key>` (.ai/rules/deep-link-highlight.md). The target controller validates the key against its own list (`ReadsHighlight`) and the page wraps the field or section in `StoreHighlight`. Never add a second highlight mechanism.
- **The page must show what the check reads.** A check may only fail on data the admin can see on the linked page:
  - Never default an unsaved value to something that looks set, such as `0` or the first option. Send null and show it empty or "Choose ...".
  - Show a server value (env, a credentials file) as read-only set/not set status (`StoreServerSettingsStatus`, never the value).
  - A setting the admin can save over `.env` ([admin-env-overrides.md](admin-env-overrides.md)) is checked on its **effective** value (saved, else `.env`), and the page shows that value with its source. The SMTP check reads `StoreMailSettings::effective()`: `smtp.mailer_log` (the mailer is `log`, emails are only logged) highlights `mail_mailer`, `smtp.host` highlights `mail_host`, and `smtp.username_and_password` (both empty; either one set counts) highlights the `mail_credentials` pair on the SMTP form.
  - Read the same row the page writes (the `lang IS NULL` row first).
  - Keep a record the check needs editable. The inactive default language can be switched back on.
  - The payment checks read the **effective** enabled list (`StorePaymentGateways::enabled()`: chosen on the Payment methods page, else `STORE_PAYMENT_GATEWAYS`). `paymentMethod` counts a gateway's Features activation switch only while the gateway is enabled, as `GET payment-types` lists it only then (`none_enabled` highlights `cash_payment` on Features activation). `paymentGateways` (permission `payment_methods_configurations`) gives one reason per enabled gateway and missing required field, `paymentGateways.<gateway>_<field>` (`StorePaymentGateways::missing()`, the effective value: saved group, else `.env`), each highlighting `<gateway>.<field>` on the Payment methods page, which opens that gateway's tab and marks the field. A field added to a gateway's spec needs its TARGETS entry and texts (`SetupChecklistTest` checks the TARGETS against the spec).
  - The language check reads the **effective** default (`StoreDefaultLanguage::get()`: chosen on the Languages page, else `DEFAULT_LANGUAGE`). `language.default_missing` and `language.default_inactive` name that exact code and highlight `default_language`, the default-language control (select and save form) on the Languages page; the control keeps a saved language that was switched off or deleted in its list, marked, so the page shows what the check reads.
- **Authorization.** A check is listed only for staff holding every permission its pages need (`SetupChecklist::PERMISSIONS`). Pick a page they may open: a missing category falls back to the product list without `view_product_categories`.
- **No duplicate banners.** The checklist is the one place a setup state is shown on the dashboard. Do not add a banner that reads the same check.

## Tests
- `tests/Feature/Store/SetupChecklistTest.php`: the reason keys per state, that every target route exists and accepts its highlight, and that unknown or array highlights become null.
- `StoreSetupChecklist.test.ts`: every reason's text in en and ar and its href with the highlight query.
- Each target page test: exactly one marked field for its highlight, and none without one.

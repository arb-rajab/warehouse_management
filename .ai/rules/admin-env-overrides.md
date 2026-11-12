---
paths:
  - 'app/Support/StoreMailSettings.php'
  - 'app/Support/PublicSettingsGuard.php'
  - 'app/Http/Controllers/Api/V1/BusinessSettingController.php'
  - 'tests/Feature/PublicSettingsGuardTest.php'
  - 'app/Support/GoogleMapsKey.php'
  - 'app/Support/StoreIntegrationIds.php'
  - 'app/Support/StoreRecaptchaSettings.php'
  - 'app/Rules/Recaptcha.php'
  - 'app/Http/Requests/Store/Admin/UpdateIntegrationIdsRequest.php'
  - 'app/Http/Requests/Store/Admin/UpdateRecaptchaSettingsRequest.php'
  - 'resources/views/shop/frontend/layouts/app.blade.php'
  - 'resources/js/pages/Store/Admin/Settings/{Analytics,FacebookChat,FacebookComments,Recaptcha}.vue'
  - 'resources/js/components/StoreIntegrationIdsForm.vue'
  - 'resources/js/lib/{integrationIds,recaptchaSettings}*'
  - 'app/Providers/AppServiceProvider.php'
  - 'app/Http/Controllers/Store/Admin/SettingsController.php'
  - 'app/Http/Requests/Store/Admin/UpdateSmtpSettingsRequest.php'
  - 'app/Http/Controllers/Api/V1/ConfigController.php'
  - 'resources/js/pages/Store/Admin/Settings/Smtp.vue'
  - 'resources/js/components/StoreSmtpTestForm.vue'
  - 'resources/js/lib/smtpSettings*'
  - 'config/mail.php'
  - 'app/Support/StoreDeliverySettings.php'
  - 'app/Support/StoreDefaultLanguage.php'
  - 'app/Http/Requests/Store/Admin/{UpdateDeliverySettingsRequest,UpdateDefaultLanguageRequest}.php'
  - 'app/Http/Controllers/Store/Admin/{OrderConfigurationController,LanguageController}.php'
  - 'app/Http/Controllers/Api/V1/OrderController.php'
  - 'resources/js/components/{StoreDeliverySettingsForm,StoreDefaultLanguageForm}.vue'
  - 'resources/js/lib/{deliverySettings,defaultLanguage}*'
  - 'resources/js/pages/Store/Admin/Setup/OrderConfiguration/**'
  - 'resources/js/pages/Store/Admin/Languages/Index.vue'
  - 'app/Services/Store/SmtpSettingsChangeNotifier.php'
  - 'app/Mail/SmtpSettingsChangedMail.php'
  - 'resources/views/shop/emails/smtp_settings_changed.blade.php'
  - 'resources/views/shop/emails/plain/smtp_settings_changed.blade.php'
  - 'app/Models/AdminAuditLog.php'
  - 'database/migrations/*admin_audit_logs*'
  - 'app/Support/StorePermissions.php'
  - 'app/Support/StaffPrivileges.php'
  - 'resources/js/components/{StoreRoleFormFields,StoreStaffFormFields}.vue'
  - 'resources/js/pages/Store/Admin/Staff/Roles/Index.vue'
  - 'database/seeders/StoreAccountFilterSeeder.php'
  - 'app/Support/StorePaymentGateways.php'
  - 'app/Models/PaymentGatewaySetting.php'
  - 'database/migrations/*payment_gateway_settings*'
  - 'app/Http/Requests/Store/Admin/UpdatePaymentGatewaySettingsRequest.php'
  - 'app/Http/Requests/Store/Admin/Concerns/ConfirmsCurrentPassword.php'
  - 'app/Services/Store/{PaymentGatewaySettingsChangeNotifier,SuperAdminRecipients}.php'
  - 'app/Mail/PaymentGatewaySettingsChangedMail.php'
  - 'resources/views/shop/emails/payment_gateway_settings_changed.blade.php'
  - 'resources/views/shop/emails/plain/payment_gateway_settings_changed.blade.php'
  - 'resources/js/pages/Store/Admin/Settings/Payments.vue'
  - 'resources/js/lib/paymentGatewaySettings*'
  - 'app/Http/Middleware/EnsurePaymentGatewayEnabled.php'
  - 'app/Services/PaymentOptions.php'
  - 'app/Services/GatewayCurrency.php'
  - 'app/Http/Controllers/Api/V1/PaymentTypesController.php'
  - 'config/{services,nagad,toyyibpay,store}.php'
---

# A setting that only `.env` held may be saved in the admin: saved value first, `.env` as the fallback

Added 2026-11-11. The owner wants settings that could only be set in the server's
`.env`, but that an admin can reasonably manage, to be editable in the store back
office. SMTP was the first; the Google Maps key (`GoogleMapsKey`) already worked
this way; the analytics, Facebook and reCAPTCHA IDs and keys followed the same
day. The delivery lead days and timezone and the default content language followed
(2026-11-11). A settings form still **never writes `.env`** (store-backoffice-migration.md §5):
the value is stored in `business_settings` and laid over the config at runtime.

## How

- **Precedence.** A saved value wins; with nothing saved the `.env` value applies,
  so behaviour is unchanged until someone saves. An empty field deletes its row
  (the `.env` value applies again), never stores an empty string.
- **Values that only make sense together are one group.** The SMTP host, port,
  encryption, username and password are saved and read together: with a host
  saved all five come from the admin, otherwise all five from `.env`. Never mix a
  `.env` secret with an admin-chosen host (it would be sent there), and never
  keep a saved secret for a changed host or username: the request asks for it
  again (`UpdateSmtpSettingsRequest::keepsPasswordForAnotherServer()`).
- **Secrets are encrypted at rest** with `Crypt::encryptString()` (the application
  key; `APP_PREVIOUS_KEYS` covers rotation) and decrypted only where they are used.
  They never reach Inertia props, flash, validation messages, logs or the cache in
  plain text. The page gets `*_set` / `*_unreadable` flags; an empty secret field
  keeps the saved one and a separate "remove" control clears it. A value that can
  no longer be decrypted reads as not set and the page asks for it again.
- **A secret can be read through its class instead of the config.** When one
  consumer reads the value (the `Recaptcha` rule), the support class returns it
  (`StoreRecaptchaSettings::secret()`) and nothing is laid over `config()`: there
  is nothing to apply, so no console boot, `config:cache` or `optimize` can hold
  it. Public IDs and keys printed into pages are read the same way
  (`StoreIntegrationIds::get()`, `StoreRecaptchaSettings::siteKey()`, as
  `GoogleMapsKey::get()` does). Use the config overlay below only when many
  consumers read config (mail).
- **A public, non-secret value many places read goes through the config overlay
  too, with a snapshot.** The default content language
  (`StoreDefaultLanguage`) is read through `config('app.default_language')` at
  about 80 sites, so `apply()` lays the effective code over it, from the same
  `AppServiceProvider` hook (web requests and `JobProcessing`, never at console
  boot). The singleton remembers the `.env` code before the first overlay, so
  "from .env" stays right. Code that must be right outside a web request (the
  Languages page and its guards, the setup checklist, the `default_language()`
  helper, `LanguageResource`) reads `StoreDefaultLanguage::get()` instead; other
  artisan commands and the scheduler see the `.env` value. The saved code is
  cached under `business_settings:default_language` (10 minutes, forgotten by the
  writer). Whether the language exists and is active is checked when it is chosen
  (`UpdateDefaultLanguageRequest`) and by the setup checklist, not on every read.
  Changing the default re-labels existing text (the default language's text lives
  in the main columns), so the page warns and asks to confirm a change.
- **A value only one consumer reads is a static support class.** The delivery
  settings (`StoreDeliverySettings`, read by `Api\V1\OrderController` only) lay
  nothing over config; a saved value out of range or not a PHP timezone identifier
  reads as not saved. An invalid `STORE_DELIVERY_TIMEZONE` in `.env` is not guarded
  (it fails the order API as before): fix the `.env`.
- **Apply at runtime, never in the console.** `AppServiceProvider::applyStoreMailSettings()`
  applies `StoreMailSettings` on web requests and on `JobProcessing` (so a running
  queue worker picks up a save), never at console boot: `config:cache` and
  `optimize` boot the providers and would write the decrypted secret into
  `bootstrap/cache/config.php`. Built mailers are dropped (`forgetMailers()`) when
  the applied values change. Code that needs the effective value without relying
  on that (the setup checklist, the SMTP page, the newsletter guard) reads
  `StoreMailSettings::effective()`.
- **Guard the read.** A missing `business_settings` or cache table (migrations, a
  fresh install, a test before `RefreshDatabase`) reads as nothing saved and is
  not cached.
- **Cache.** The saved rows are cached under `business_settings:mail` (secrets still
  encrypted, 10 minutes). The writer forgets that entry and `business_settings`.
- **Keep the rows private.** Public endpoints that list `business_settings` by
  name must go through `App\Support\PublicSettingsGuard` (`ConfigController::business_settings`,
  `activated_social_login` and `BusinessSettingController::index` do). The SMTP rows
  (`StoreMailSettings::KEYS`), the encrypted reCAPTCHA secret and the other
  credentials are blocked there, not in each controller: see "Every
  `business_settings` key is classified" below.
- **A value printed into a script or URL is validated and escaped.** The public
  IDs (`StoreIntegrationIds`) must match a pattern of the characters real IDs
  use (no quote, angle bracket, space or line break; `D` flag so a trailing
  newline fails), in the request, the browser copy and again when read (a stored
  value that does not match reads as not saved and `.env` applies). The layout
  prints them with `Js::from()` inside `<script>` and `urlencode()` inside a
  URL, and a block whose switch is on but whose ID is empty prints nothing.
- **Show the source.** The page shows each effective value and whether it is
  "saved in admin" or "from .env"; secrets only as set / not set.
- **Document the superseded keys** in the README's Deployment section.

## Security: the SMTP settings are a way to read all mail

Added 2026-11-11 (owner-approved). Whoever chooses the mail server can redirect,
and so read, every email the shop sends, password-reset emails included. Three
mitigations hold together; keep all three when touching the SMTP page.

- **A sensitive permission.** `StorePermissions::SENSITIVE` (`smtp_settings`, and
  since 2026-11-12 `payment_methods_configurations`, see "Payment gateways")
  only a Super Admin grants. `StaffPrivileges::grantablePermissionIds()` leaves
  it out for everyone else, so they cannot tick it onto a new or edited role
  (`messages.role_permission_super_admin_only`), cannot take it off a role (the
  form does not offer it and the edit keeps it), and cannot give a role that
  carries it (`canAssign()`, the staff form's role list). A staff member keeps
  their own existing role when someone else edits their other fields. The role
  form shows it to a Super Admin with a "Sensitive" warning; the Roles list badges
  such roles (`Role::withSensitiveFlag()`, `RoleResource` `sensitive`), and the
  staff form labels them. No seeder gives it to a role other than Super Admin
  (`StoreAccountFilterSeeder`'s demo role excludes it); **existing grants are
  never revoked by a migration**: the owner decides. To list the holders:

  ```sql
  SELECT r.name AS holder, 'role' AS via FROM wms_permissions p
    JOIN wms_role_has_permissions rp ON rp.permission_id = p.id JOIN wms_roles r ON r.id = rp.role_id
    WHERE p.name = 'smtp_settings' AND p.guard_name = 'store'
  UNION ALL
  SELECT CONCAT('store user #', mp.model_id), 'direct' FROM wms_permissions p
    JOIN wms_model_has_permissions mp ON mp.permission_id = p.id
    WHERE p.name = 'smtp_settings' AND p.guard_name = 'store';
  ```

  The Super Admin holds it through `Gate::before()`, not through a row.
- **The staff member's own password.** `UpdateSmtpSettingsRequest` requires
  `current_password` (`current_password:store`), with a field error. Wrong ones
  are counted per account (`passwordLimiterKey()`, 5 per 15 minutes): a
  locked-out account is refused in `prepareForValidation()`, before the password
  is checked (`store_admin.smtp_current_password_throttled`). The counter lives in
  `Concerns\ConfirmsCurrentPassword` and is shared with the Payment methods page. A counted failure is
  logged (user id and IP) and written to the audit log as
  `smtp_settings.password_failed`, only while the count is within the limit, so a
  flood adds at most 5 rows per account per window. The password is never saved,
  logged or flashed (`dontFlash`, Telescope's hidden parameters); the page empties
  it and the SMTP password after every submit (`ResourceFormPage`'s
  `resetOnSuccess`/`resetOnError`). The browser copy requires it; the check itself
  is `serverOnly`. The test email needs no password.
- **An audit row and a mail on every change.** `StoreMailSettings::save()` returns
  field => `set`/`changed`/`removed` (the password compared decrypted) and writes
  the `smtp_settings.updated` row of `admin_audit_logs` (`AdminAuditLog::record()`)
  in the save's own transaction. The row holds the user id (no foreign key, so it
  outlives the account), a name and email snapshot, the field names and kinds, IP
  and user agent; never a value. A save that changes nothing writes and mails
  nothing (the success flash still shows). Removing the password and clearing the
  server group are changes like any other. The page lists the last 10 saves and
  refused passwords (`auditEntries`, a `DataTable` without the user agent).
  `SmtpSettingsChangeNotifier` then mails every active Super Admin (role, back-office
  `user_type`, not banned, with an email) and, only when the from address changed,
  the previous from-address unless it is one of theirs; one mail each, in the
  recipient's language (`SmtpSettingsChangedMail`, `delivery.mail.smtp_changed.*`):
  who, when (UTC), IP and the field names, never a value.
- **Which mailer sends that mail, and why.** At once (not queued), through the
  settings **as they were before** the save: someone who points the mail at their
  own server must not be able to swallow the alert, so it goes over the server the
  shop used until then. A queued mail would leave with the new settings (a worker
  applies the saved ones), and queueing the old ones would put the decrypted
  password into the job payload. For a previous `smtp` mailer a runtime-only mailer
  `smtp_before_change` is built from the snapshot (`effective()` read before the
  save, 10-second timeout) and dropped afterwards; any other previous mailer (`log`,
  a `.env` `ses`) is untouched by this page and used as it is (a `.env`
  `failover`/`roundrobin`'s `smtp` leg already has the new values). The `.env`
  mailer gets no extra copy. A mail that fails is logged by exception class only
  and never undoes or fails the save.

## Payment gateways: one group per gateway, in their own table

Added 2026-11-12 (owner-approved). All 11 gateways (bKash, Nagad, SSLCommerz,
iyzico, PayHere, AamarPay, N-Genius, VoguePay, ToyyibPay, Stripe, Paytm) are edited
on the Payment methods page (`Settings/Payments.vue`, permission
`payment_methods_configurations`): credentials, currency, mode and an enable switch.
`App\Support\StorePaymentGateways::GATEWAYS` is the one spec (fields, `.env` name,
secret or public, required, format, mode source).

- **Storage.** `payment_gateway_settings` (`PaymentGatewaySetting`), one row per
  gateway and field, not `business_settings`: its `type` is a `string(30)` and
  its rows can be read by the public endpoint. Secrets are `Crypt::encryptString()`
  ciphertext (`is_secret`); the model hides `value`. Nothing about it is in
  `PublicSettingsGuard` because no public endpoint reads the table. Cached under
  `payment_gateway_settings` (secrets still encrypted); `StorePaymentGateways::forget()`
  also forgets `business_settings`.
- **Group precedence.** Once a gateway's identifying field (bKash app key, Nagad
  merchant id, SSLCommerz/AamarPay store id, iyzico API key, PayHere/VoguePay/Paytm
  merchant id, N-Genius outlet id, ToyyibPay category code, Stripe publishable key)
  is saved, every field of that gateway comes from the admin, an empty one too,
  and none from `.env`; otherwise all come from config. Emptying the identifying
  field deletes the whole group, secrets included. A changed identifying field (or
  a `tied` one: bKash username, Paytm website) asks for the saved secrets again.
- **Read through the class, never laid over config.** Every consumer reads
  `StorePaymentGateways::get($gateway, $field)` (the controllers, `NagadUtility`,
  `NgeniusUtility`, `PayhereUtility`, `IyzicoPayments`, `SSLCommerz`, the PayHere
  forms) and `GatewayCurrency::forGateway()` reads `currency()`. With nothing saved
  it returns `config()`, so tests that set config and `.env` keep working.
- **Mode.** The six `<gateway>_sandbox` switches (bKash, SSLCommerz, iyzico, PayHere,
  AamarPay, VoguePay) were never `.env`: they stay `business_settings` rows, edited
  in the gateway's tab and audited as `<gateway>.mode`. Nagad (`NAGAD_MODE`),
  N-Genius (`NGENIUS_MODE`, `uat`/`live`), Paytm (`PAYTM_ENVIRONMENT`) and ToyyibPay
  (`TOYYIBPAY_MODE`) have a `mode` field in the group, stored as `sandbox`/`live`
  and mapped to the gateway's own value. Stripe's mode is read from its key prefix
  and both keys must agree.
- **Enabled list.** `enabled_source` chooses: follow `STORE_PAYMENT_GATEWAYS`
  (no `enabled` rows) or the switches (every gateway gets an `enabled` row, so
  "chosen here, all off" means none). `EnsurePaymentGatewayEnabled`,
  `PaymentOptions` (checkout, wallet, packages), `GET payment-types` and the setup
  checklist read `enabled()`. Stripe, Paytm and ToyyibPay have no payment flow:
  their keys can be stored, their switch is locked off (`payment_gateway_has_no_flow`).
  The Features activation switches (`bkash`, `sslcommerz_payment`, ...) stay the
  "offered at checkout" switch; the page shows their state and links there.
- **Still `.env` only:** `IYZICO_IDENTITY_NUMBER`, `NGENIUS_*_URL`,
  `NAGAD_CALLBACK_URL` (a redirect target), `NAGAD_CURRENCY` (BDT only).
- **Hardening (as SMTP).** `payment_methods_configurations` is in
  `StorePermissions::SENSITIVE`. `UpdatePaymentGatewaySettingsRequest` requires
  `current_password` through `ConfirmsCurrentPassword`, whose limiter key
  (`sensitive-settings-password|<id>`, 5 per 15 minutes) the SMTP request shares,
  so the two pages do not double the guesses; a counted failure writes
  `payment_gateway_settings.password_failed`. A save writes
  `payment_gateway_settings.updated` with `<gateway>.<field>` => `set`/`changed`/
  `removed`, `<gateway>.enabled` => `enabled`/`disabled`, `enabled_source` =>
  `set`/`removed` (never a value) in its transaction, then
  `PaymentGatewaySettingsChangeNotifier` mails every active Super Admin
  (`SuperAdminRecipients`, shared with SMTP) at once through the current mailer
  (this page never changes mail settings). A no-op save writes and mails nothing.
  `gateways` is in `dontFlash` and Telescope's hidden parameters. The page's
  required marks follow `required_with` (a secret: once the identifying field is
  typed and no readable saved one is kept); the browser copy is
  `lib/paymentGatewaySettingsRules.json`. To list the holders, run the SMTP query
  above with `p.name = 'payment_methods_configurations'`.

## Every `business_settings` key is classified (`PublicSettingsGuard`)

Added 2026-11-12 (owner-approved). `POST /api/v1/store/business-settings` is
public and unauthenticated and used to return any row whose `type` the caller
named. It now returns every requested key **except the blocked ones**: a
block-list, not an allow-list and not a sign-in. Old mobile app versions are
forced to update through the minimum app version, so a blocked key needs no
compatibility. `App\Support\PublicSettingsGuard` is the one class that decides;
`ConfigController::business_settings()` uses `requested()` (the names to query)
and `filter()` (the rows read, judged by their own `type`, since a database
collation can match a type the request spelled differently), and the GET list and
the social-login list filter through it as well.

- **What is blocked.** `isBlocked()` compares trimmed and lower-cased (also
  non-breaking and zero-width spaces), so case and padding cannot get around it,
  and blocks an empty name. A key is blocked when it is in `BLOCKED` or owned by a
  support class (`named()`: `StoreMailSettings::KEYS`, the encrypted reCAPTCHA
  secret, `GoogleMapsKey::SETTING`, so a field added there is blocked without an
  edit), when its name starts with a `BLOCKED_PREFIXES` entry (`payment_gateway_`,
  `mail_`, `smtp_`, `firebase_`, `fcm_`, `sms_`, the usual payment and SMS
  providers, and the internal `product_source_` and `store_filter_seed_`), when it
  ends in `secret`, `key`, `password`, `token`, `credentials`, `encrypted` (and a
  few more words, see the class) or carries `secret`, `password`, `token`,
  `credentials` or `encrypted` as a word. `requested()` reads `keys` as a
  comma-separated string or an array of such strings, ignores anything else
  (a number, a nested array) and caps the names at `MAX_KEYS`.
- **Exceptions.** `PUBLIC_EXCEPTIONS` are names a rule would block that stay
  public on purpose. Today only `recaptcha_site_key` (the reCAPTCHA SITE key is
  printed into every storefront form; only the secret is a credential). An exception
  never overrides a named key. Add one only with the reason beside it.
- **The Google Maps key is blocked**, although it is a browser-side key: only a
  signed-in customer's or seller's page prints it (the address map), the admin page
  only shows a mask of it, and no app reads it from this endpoint.
- **Every new `business_settings` key must be classified in the guard.** Either a
  rule blocks it (a credential: name it so a rule matches, or add it to `BLOCKED`),
  or it is added to `PUBLIC` (not a credential: printed into public pages, needed
  by the apps, or a plain switch or number). `PublicSettingsGuardTest` enforces it:
  it reads the admin pages' keys (`SettingsController::PAGES`), the `Update*Request::KEYS`
  lists, the support classes' constants and every `get_setting('...')` and
  `BusinessSetting::where('type', '...')` literal in `app/` and `resources/views/`,
  and fails for a key that is neither blocked nor in `PUBLIC`. A key built at runtime
  is not seen by that scan: add it to the test's source list or give it a name a rule
  blocks.
- **A secret has a name a rule blocks.** Name credentials `*_secret`, `*_key`,
  `*_password`, `*_token`, `*_credentials` or `*_encrypted` (as `mail_password_encrypted`
  and `recaptcha_secret_key_encrypted` are), or put them under `mail_` or
  `payment_gateway_`; never store a secret under a plain name and rely on the
  list. The payment gateway settings use `payment_gateway_`, so the whole family is
  blocked with nothing to edit; a gateway flag the apps must read goes to
  `PUBLIC_EXCEPTIONS` and `PUBLIC` with the reason. `business_settings.type` is a
  `string(30)`: a long key must fit.

## Superseded `.env` keys today

| Admin setting | `.env` fallback | Stored as |
| --- | --- | --- |
| SMTP page: mailer (`smtp`, `log`, or the server setting) | `MAIL_MAILER` | `mail_mailer` |
| SMTP page: host, port, encryption, username, password (one group) | `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`; `MAIL_URL` is ignored once a host is saved | `mail_host`, `mail_port`, `mail_scheme`, `mail_username`, `mail_password_encrypted` (encrypted) |
| SMTP page: from address, from name | `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `mail_from_address`, `mail_from_name` |
| Google map page: API key | `MAP_API_KEY` | `google_map_api_key` |
| Analytics page: Google Analytics tracking ID | `TRACKING_ID` | `google_analytics_tracking_id` |
| Analytics page: Facebook pixel ID | `FACEBOOK_PIXEL_ID` | `facebook_pixel_id` |
| Facebook chat page: Facebook page ID | `FACEBOOK_PAGE_ID` | `facebook_page_id` |
| Facebook comments page: Facebook app ID | `FACEBOOK_APP_ID` | `facebook_app_id` |
| Order configuration page: delivery lead days (whole days, 0 to 30) | `STORE_DELIVERY_LEAD_DAYS` | `delivery_lead_days` |
| Order configuration page: delivery timezone (a PHP identifier, `DateTimeZone::listIdentifiers()`) | `STORE_DELIVERY_TIMEZONE` | `delivery_timezone` |
| Languages page: default content language (an existing, active language) | `DEFAULT_LANGUAGE` | `default_language` |
| Payment methods page: which gateways answer (`enabled_source` + a switch per gateway) | `STORE_PAYMENT_GATEWAYS` | `payment_gateway_settings` rows `<gateway>.enabled` |
| Payment methods page, bKash (one group) | `BKASH_CHECKOUT_APP_KEY`, `BKASH_CHECKOUT_APP_SECRET`, `BKASH_CHECKOUT_USER_NAME`, `BKASH_CHECKOUT_PASSWORD`, `BKASH_CURRENCY` | `payment_gateway_settings` (secrets encrypted) |
| Payment methods page, Nagad (one group) | `NAGAD_MERCHANT_ID`, `NAGAD_MERCHANT_NUMBER`, `NAGAD_PG_PUBLIC_KEY`, `NAGAD_MERCHANT_PRIVATE_KEY`, `NAGAD_MODE` | same |
| Payment methods page, SSLCommerz (one group) | `SSLCZ_STORE_ID`, `SSLCZ_STORE_PASSWD`, `SSLCZ_CURRENCY` | same |
| Payment methods page, iyzico (one group) | `IYZICO_API_KEY`, `IYZICO_SECRET_KEY`, `IYZICO_CURRENCY_CODE` | same |
| Payment methods page, PayHere (one group) | `PAYHERE_MERCHANT_ID`, `PAYHERE_SECRET`, `PAYHERE_CURRENCY` | same |
| Payment methods page, AamarPay (one group) | `AAMARPAY_STORE_ID`, `AAMARPAY_SIGNATURE_KEY`, `AAMARPAY_CURRENCY` | same |
| Payment methods page, N-Genius (one group) | `NGENIUS_OUTLET_ID`, `NGENIUS_API_KEY`, `NGENIUS_CURRENCY`, `NGENIUS_MODE` | same |
| Payment methods page, VoguePay (one group) | `VOGUE_MERCHANT_ID`, `VOGUE_CURRENCY` | same |
| Payment methods page, ToyyibPay (one group, no payment flow) | `TOYYIBPAY_CATEGORY`, `TOYYIBPAY_KEY`, `TOYYIBPAY_MODE` | same |
| Payment methods page, Stripe (one group, no payment flow) | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_CURRENCY` | same |
| Payment methods page, Paytm (one group, no payment flow) | `PAYTM_MERCHANT_ID`, `PAYTM_MERCHANT_KEY`, `PAYTM_MERCHANT_WEBSITE`, `PAYTM_CHANNEL`, `PAYTM_INDUSTRY_TYPE`, `PAYTM_ENVIRONMENT` | same |
| reCAPTCHA page: site key and secret key (one pair) | `CAPTCHA_KEY`, `CAPTCHA_SECRET`: with a site key saved, both come from the admin and `CAPTCHA_SECRET` is not used | `recaptcha_site_key`, `recaptcha_secret_key_encrypted` (encrypted) |

The IDs and the reCAPTCHA key are optional in the form (`.env` may hold them), so
they carry no required mark; the switches stay `settings[...]` and the pages warn
inline, naming the field, when a switch is on and no value is in effect. The
reCAPTCHA secret is needed for a new or changed site key (the request asks again,
as for the SMTP password). `StoreIntegrationIds`, `StoreRecaptchaSettings`
and `UpdateIntegrationIdsRequest`/`UpdateRecaptchaSettingsRequest` are the code;
the public business-settings endpoint hides the encrypted secret row through
`PublicSettingsGuard` (the IDs and the site key are public by nature: the site key
is the guard's one name-rule exception). The Facebook login keys
(`FACEBOOK_CLIENT_ID`/`SECRET`) and the other social logins stay `.env` only.

The delivery fields and the default language are optional too (an empty field uses
`.env`), so no required mark or legend. The delivery weekdays (`store.delivery.weekdays`)
stay in `config/store.php`. `StoreDeliverySettings`, `StoreDefaultLanguage`,
`UpdateDeliverySettingsRequest`/`UpdateDefaultLanguageRequest` and the
`StoreDeliverySettingsForm`/`StoreDefaultLanguageForm` components are the code. No
setup checklist item reads the delivery settings; the language check reads the
effective default.

Still `.env` only: `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL`, `MAIL_SENDMAIL_PATH`, the
other mailers' keys, and the from address of the health and backup notifications
(`config/health.php`, `config/backup.php` read `MAIL_FROM_*` directly).

## Tests

- `tests/Feature/Store/Admin/SmtpSettingsTest.php`: save, encryption at rest, the
  password never in a response, precedence and the group, `.env` fallback, keep /
  change / clear, cache forgetting, the queue-worker and console paths, an
  unreadable table or password, authorization, validation (header injection), the
  immediate test email and the public API filter.
- `tests/Feature/PublicSettingsGuardTest.php`: the enforcement test (every key the
  code names is blocked or public), the guard's lists, the name rules, case and
  whitespace, an array or junk `keys`, the full row shape, the keys the apps rely
  on, and the GET and social-login lists.
- `SmtpSettingsClientValidationParityTest`: the browser copy of the rules.
- `tests/Feature/Store/Admin/SmtpSettingsSecurityTest.php`: the current password
  (missing, wrong, another account's, never flashed), the throttle and its capped
  audit rows, the audit row (kinds, no values, same transaction), no-op saves,
  password removal and group clearing, the notification (recipients, the previous
  mailer, the previous from-address, no secrets, failure does not block, language)
  and the page's recent entries. `SensitivePermissionTest.php`: seeding, the role
  and staff forms, the Roles list flag. `Models/AdminAuditLogTest.php`,
  `Models/RoleTest.php`, `Database/CreateAdminAuditLogsTableTest.php`.
- `tests/Feature/Store/Admin/IntegrationIdsTest.php` and
  `RecaptchaSettingsTest.php`: save, precedence and the pair, `.env` fallback,
  keep / change / clear, encryption at rest, the secret never in a response,
  script-injection characters, authorization, the layout printing the effective
  escaped IDs, the public API filter. Their parity tests
  (`IntegrationIdsClientValidationParityTest`,
  `RecaptchaSettingsClientValidationParityTest`) check the browser rules.
- `tests/Feature/Store/Admin/DeliverySettingsTest.php` and
  `DefaultLanguageSettingsTest.php`: save, precedence and `.env` fallback, clearing,
  validation (an unknown timezone, lead days out of range, a nonexistent or inactive
  language), authorization, the success flash, what the page sends, and that the
  consumers read the effective value (the order API's delivery date; the helper, the
  config overlay on a job and not on a console boot, the guards on the default).
  Their parity tests (`DeliverySettingsClientValidationParityTest`,
  `DefaultLanguageClientValidationParityTest`) check the browser rules.
- `tests/Feature/Store/SetupChecklistTest.php`: the checklist reads the effective
  settings, the language reasons name the code, the payment checks follow the
  enabled list and name each missing gateway field.
- `tests/Feature/Store/Admin/PaymentGatewaySettingsTest.php` (save, group
  precedence, fallback, encryption, keep/replace/remove, re-entry for another
  account, formats, the enabled list and its consumers, the gateway consumers
  reading the admin values, the page, highlights, authorization),
  `PaymentGatewaySettingsSecurityTest.php` (the sensitive permission, the shared
  password limiter, audit kinds, no-op saves, the Super Admin mail),
  `PaymentGatewaySettingsClientValidationParityTest.php`,
  `Database/CreatePaymentGatewaySettingsTableTest.php`, and
  `resources/js/pages/Store/Admin/Settings/Payments.test.ts`.
- `resources/js/pages/Store/Admin/Settings/Smtp.test.ts`, `Analytics.test.ts` (also
  the chat and comments pages) and `Recaptcha.test.ts`: the pages.
  `OrderConfiguration/Index.test.ts` (the delivery form) and
  `components/StoreDefaultLanguageForm.test.ts` (with `Languages/Index.test.ts`).

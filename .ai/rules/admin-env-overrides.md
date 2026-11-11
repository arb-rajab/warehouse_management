---
paths:
  - 'app/Support/StoreMailSettings.php'
  - 'app/Support/GoogleMapsKey.php'
  - 'app/Providers/AppServiceProvider.php'
  - 'app/Http/Controllers/Store/Admin/SettingsController.php'
  - 'app/Http/Requests/Store/Admin/UpdateSmtpSettingsRequest.php'
  - 'app/Http/Controllers/Api/V1/ConfigController.php'
  - 'resources/js/pages/Store/Admin/Settings/Smtp.vue'
  - 'resources/js/components/StoreSmtpTestForm.vue'
  - 'resources/js/lib/smtpSettings*'
  - 'config/mail.php'
---

# A setting that only `.env` held may be saved in the admin: saved value first, `.env` as the fallback

Added 2026-11-11. The owner wants settings that could only be set in the server's
`.env`, but that an admin can reasonably manage, to be editable in the store back
office. SMTP was the first; the Google Maps key (`GoogleMapsKey`) already worked
this way. A settings form still **never writes `.env`** (store-backoffice-migration.md §5):
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
  name must leave these rows out (`ConfigController::business_settings` excludes
  `StoreMailSettings::KEYS`).
- **Show the source.** The page shows each effective value and whether it is
  "saved in admin" or "from .env"; secrets only as set / not set.
- **Document the superseded keys** in the README's Deployment section.

## Superseded `.env` keys today

| Admin setting | `.env` fallback | Stored as |
| --- | --- | --- |
| SMTP page: mailer (`smtp`, `log`, or the server setting) | `MAIL_MAILER` | `mail_mailer` |
| SMTP page: host, port, encryption, username, password (one group) | `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`; `MAIL_URL` is ignored once a host is saved | `mail_host`, `mail_port`, `mail_scheme`, `mail_username`, `mail_password_encrypted` (encrypted) |
| SMTP page: from address, from name | `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `mail_from_address`, `mail_from_name` |
| Google map page: API key | `MAP_API_KEY` | `google_map_api_key` |

Still `.env` only: `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL`, `MAIL_SENDMAIL_PATH`, the
other mailers' keys, and the from address of the health and backup notifications
(`config/health.php`, `config/backup.php` read `MAIL_FROM_*` directly).

## Tests

- `tests/Feature/Store/Admin/SmtpSettingsTest.php`: save, encryption at rest, the
  password never in a response, precedence and the group, `.env` fallback, keep /
  change / clear, cache forgetting, the queue-worker and console paths, an
  unreadable table or password, authorization, validation (header injection), the
  immediate test email and the public API filter.
- `SmtpSettingsClientValidationParityTest`: the browser copy of the rules.
- `tests/Feature/Store/SetupChecklistTest.php`: the checklist reads the effective
  settings.
- `resources/js/pages/Store/Admin/Settings/Smtp.test.ts`: the page.

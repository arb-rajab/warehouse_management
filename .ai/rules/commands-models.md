---
paths:
  - 'app/Http/Middleware/EnsureMinimumAppVersion.php,app/Console/Commands/SetMinimumAppVersionCommand.php,app/Models/MobileAppVersionRequirement.php'
---

# Commands Models

## Minimum mobile app version gate
To force old mobile-app builds to upgrade, run `php artisan app:set-minimum-app-version {version}` — it upserts the single row (id 1) in `mobile_app_version_requirements`. `EnsureMinimumAppVersion` (appended to the `api` middleware group in bootstrap/app.php, after SetLocaleFromHeader) reads the client's `X-App-Version` header on every `api/*` request (including login) and rejects it with 426 + `error_code: app_version_outdated` (lang key `messages.app_version_outdated`, kept in sync across en/ar) if missing or `version_compare() <` the configured minimum. No row in the table means no restriction — the gate is opt-in until the command is run once. `MobileAppVersionRequirement::minimumVersion()` is the single read path; don't query the table directly elsewhere.

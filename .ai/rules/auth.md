---
paths:
  - 'bootstrap/app.php,app/Http/Controllers/LoginController.php,resources/js/pages/Auth/Login.vue,routes/web.php'
---

# Auth

## Security hardening: secure-headers + honeypot on the login form
bepsvpt/secure-headers' headers are sent by `App\Http\Middleware\SendSecureHeaders`, appended globally in bootstrap/app.php (alongside BlockMaliciousRequests). config/secure-headers.php now carries a real CSP: `script-src 'self'`, no inline scripts, report-only outside production (see that config's comments, and tests/Feature/SecureHeadersTest.php). The store's unconverted Blade pages get the looser `config('store.blade_csp')` instead, through a per-request override (store-backoffice-migration.md §2.5).

spatie/laravel-honeypot only has a Blade component (`<x-honeypot>` / `@honeypot`), but this app is Inertia/Vue with no Blade forms. The pattern used instead: controller injects `Honeypot $honeypot` and passes `$honeypot->toArray()` as an Inertia prop (see LoginController@create); the Vue page renders the same hidden-field markup by hand (matching honeypotFormFields.blade.php) inside the `<Form>` component — Inertia's `<Form>` reads any named `<input>` in its slot without v-model, so plain hidden inputs work. The `honeypot` middleware alias (`Spatie\Honeypot\ProtectAgainstSpam`) is applied per-route (only `POST /login`, the one public form), not globally — the check is a no-op when its fields are absent from the request, so this doesn't need to be added to every route, only ones that actually render the honeypot fields.

roave/security-advisories:dev-latest is a dev-only metapackage (no code, just conflict rules) that fails `composer require`/`update` if a vulnerable package version would be installed — no wiring needed, it's self-enforcing.

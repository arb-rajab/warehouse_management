---
paths:
  - routes/web.php
  - routes/api.php
  - routes/console.php
---

# Routes

## `/` redirects to the external marketing site; sign-in lives at /admin/login
`/` (`WelcomeController`, still named `welcome`) issues an HTTP redirect via `redirect()->away('https://albaraka-holland.nl/')` to the external site `https://albaraka-holland.nl/`, with no auth branching — it redirects unconditionally for guests and authenticated users alike. `redirect()->away()` is used (not an Inertia-side redirect) because the target is an external domain. The formerly-rendered `Welcome.vue` "under construction" page and its test were removed since nothing else referenced them once `/` stopped rendering it. Sign-in is `Route::get('admin/login', ...)`/`Route::post('admin/login', ...)` inside the `guest` middleware group, still named `login` (so every `route('login')` call — `LoginController::destroy`, `RedirectIfAuthenticated::redirectUsing()` in AppServiceProvider, tests — is unaffected by the URI move). Covered by `tests/Feature/RootRedirectTest.php`.

## API routes: prefix('v1'), auth:sanctum group, role middleware for authorization
All API routes are grouped under `prefix('v1')`; only `POST v1/login` is public, everything else sits in an inner `auth:sanctum` middleware group. Admin-only web routes are gated the same way in routes/web.php with spatie/laravel-permission's `role:admin` middleware. Add new role-gated route groups with `role:` middleware, not a policy, unless the check becomes per-record ownership rather than a blanket role check — `app/Policies` holds exactly one policy, `CellVerificationRoundPolicy`, for precisely that "unless": a mobile worker may only act on their own verification rounds, so `Api\V1\CellVerificationRoundController`'s `show()`/`complete()` and `Api\V1\CellVerificationReportController::store()` each call `Gate::authorize('view'|'update', $round)` per record. The admin panel's own listings of the same rounds stay behind `role:admin` and never consult it, which is why the policy has no `before()` admin bypass — an admin failing it on the API is intended, and pinned by tests/Feature/Policies/CellVerificationRoundPolicyTest.php. Routes use explicit `Route::get/post` per action, not `Route::resource`, matching routes/web.php. The one exception is the `/health` route, gated with `can:viewHealth` (a `Gate::define()` in HealthServiceProvider, same pattern as Telescope/Pulse) rather than `role:admin` — these internal-tool gates are a distinct authorization axis from the app's role-based route groups, not a policy replacement for them.

## routes/console.php requires explicit wiring in bootstrap/app.php
This app's `withRouting()` call in bootstrap/app.php does not pass a `commands:` path, so `routes/console.php` is NOT auto-loaded by default — `Schedule::` definitions placed there are silently ignored until `commands: __DIR__.'/../routes/console.php'` is added to the `withRouting()` call. Verify with `php artisan schedule:list` after adding any scheduled command here.

---
paths:
  - routes/web.php
  - routes/api.php
  - routes/console.php
---

# Routes

## No landing page — `/` is a bare redirect to /login
This is an internal admin panel, not a public site. Laravel's starter `Welcome` page and its `home` named route were deleted; `/` is now `Route::redirect('/', '/login')` with no name, so `route('home')` does not exist. Signed-in users get bounced from /login to `admin.rows.index` by the `RedirectIfAuthenticated::redirectUsing()` callback in AppServiceProvider. Do not reintroduce an Inertia landing page at `/`. Covered by `tests/Feature/RootRedirectTest.php`.

## API routes: prefix('v1'), auth:sanctum group, role middleware for authorization
All API routes are grouped under `prefix('v1')`; only `POST v1/login` is public, everything else sits in an inner `auth:sanctum` middleware group. Admin-only web routes are gated the same way in routes/web.php with spatie/laravel-permission's `role:admin` middleware. Add new role-gated route groups with `role:` middleware, not a policy, unless the check becomes per-record ownership rather than a blanket role check — `app/Policies` holds exactly one policy, `CellVerificationRoundPolicy`, for precisely that "unless": a mobile worker may only act on their own verification rounds, so `Api\V1\CellVerificationRoundController`'s `show()`/`complete()` and `Api\V1\CellVerificationReportController::store()` each call `Gate::authorize('view'|'update', $round)` per record. The admin panel's own listings of the same rounds stay behind `role:admin` and never consult it, which is why the policy has no `before()` admin bypass — an admin failing it on the API is intended, and pinned by tests/Feature/Policies/CellVerificationRoundPolicyTest.php. Routes use explicit `Route::get/post` per action, not `Route::resource`, matching routes/web.php. The one exception is the `/health` route, gated with `can:viewHealth` (a `Gate::define()` in HealthServiceProvider, same pattern as Telescope/Pulse) rather than `role:admin` — these internal-tool gates are a distinct authorization axis from the app's role-based route groups, not a policy replacement for them.

## routes/console.php requires explicit wiring in bootstrap/app.php
This app's `withRouting()` call in bootstrap/app.php does not pass a `commands:` path, so `routes/console.php` is NOT auto-loaded by default — `Schedule::` definitions placed there are silently ignored until `commands: __DIR__.'/../routes/console.php'` is added to the `withRouting()` call. Verify with `php artisan schedule:list` after adding any scheduled command here.

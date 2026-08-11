---
paths:
  - routes/web.php
  - routes/api.php
---

# Routes

## No landing page — `/` is a bare redirect to /login
This is an internal admin panel, not a public site. Laravel's starter `Welcome` page and its `home` named route were deleted; `/` is now `Route::redirect('/', '/login')` with no name, so `route('home')` does not exist. Signed-in users get bounced from /login to `admin.rows.index` by the `RedirectIfAuthenticated::redirectUsing()` callback in AppServiceProvider. Do not reintroduce an Inertia landing page at `/`. Covered by `tests/Feature/RootRedirectTest.php`.

## API routes: prefix('v1'), auth:sanctum group, role middleware for authorization
All API routes are grouped under `prefix('v1')`; only `POST v1/login` is public, everything else sits in an inner `auth:sanctum` middleware group. Admin-only web routes are gated the same way in routes/web.php with spatie/laravel-permission's `role:admin` middleware — there is no app/Policies directory and no Gate::/->authorize()/->can() usage anywhere. Add new role-gated route groups with `role:` middleware, not a policy, unless the check becomes per-record ownership rather than a blanket role check. Routes use explicit `Route::get/post` per action, not `Route::resource`, matching routes/web.php.

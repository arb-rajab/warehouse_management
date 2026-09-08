---
paths:
  - app/Providers/TelescopeServiceProvider.php
  - 'app/Providers/AppServiceProvider.php,bootstrap/app.php,routes/web.php,routes/api.php'
---

# App Providers

## Telescope::tag()/filter() closures must never force a fresh Auth resolution
Use Auth::hasResolvedGuards() && Auth::hasUser() to peek at an already-resolved user, never Auth::check()/Auth::id()/Auth::user() directly, inside any Telescope::tag() or Telescope::filter() closure. Those force a guard to resolve if not cached, which queries the users table; Telescope then tries to tag/filter *that* query too, re-entering the closure before the original lookup finishes, recursing until PHP's max_execution_time kills the request. This caused real login hangs/500s. hasResolvedGuards()+hasUser() only read state, never trigger a lookup — Telescope's own core tagging code (vendor/laravel/telescope/src/Telescope.php) uses the same pattern. Covered by tests/Feature/TelescopeAccessTest.php ("never forces a fresh auth lookup" / "includes the resolved user once one exists"). Not caught by phpunit.xml's normal run since TELESCOPE_ENABLED=false there — the regression tests call Telescope::$tagUsing directly instead of relying on the watcher pipeline.

## Eloquent strict mode is on outside production, via Model::shouldBeStrict()
`AppServiceProvider::configureDefaults()` calls `Model::shouldBeStrict(! app()->isProduction())`, alongside the other environment-gated defaults there (`DB::prohibitDestructiveCommands`, `Password::defaults`). In Laravel 13 that helper sets exactly three guards — `preventLazyLoading`, `preventSilentlyDiscardingAttributes`, `preventAccessingMissingAttributes` — turning three rules this codebase already followed by convention into enforced ones: no lazy loading, no reading an attribute a narrow `select()` left out, and no silently dropping a non-fillable attribute on `fill()`/`create()`. It does *not* enable `automaticallyEagerLoadRelationships()`; that stays at the framework default (off), where it is only consulted in `Model::__wakeup()`.

It is deliberately off in production — a missed eager load should make a page slow, not 500 for a real user. `phpunit.xml` sets `APP_ENV=testing`, so CI runs with all three on and a violation fails the suite. `tests/Feature/EloquentStrictModeTest.php` asserts each guard directly *and* behaviourally, so a change to what the bundle contains can't quietly drop one.

### The lazy-loading guard only fires on result sets of 2+ models
`Model::preventsLazyLoading()` reporting true is not enough for a violation to throw. `getRelationValue()` checks a *per-instance* `$preventsLazyLoading` property, and `Builder::hydrate()` only copies the static flag onto it when `count($items) > 1` — lazy loading a single model is one extra query, not an N+1. So `find()`, `findOrFail()`, `first()` and `firstOrFail()` never throw, however strict the global flag is. A test asserting the violation has to go through a query returning 2+ rows: `Row::factory()->count(2)->create(...)` then `Row::query()->get()`, then touch the relation on `->first()`. This cost two CI rounds, both spent rewriting the provider while the guard was working correctly and the test was the thing that was wrong — check the arming condition before concluding the flag is inert.

Two further things this does *not* break: Eloquent allows lazy loading on a model that was just created (`wasRecentlyCreated`), so a factory's own instance won't throw — re-fetch to exercise the guard. And `Factory::makeInstance()` wraps instantiation in `Model::unguarded()`, so factories may keep setting non-fillable attributes.

The one real violation this surfaced when first enabled: `SetMinimumAppVersionCommand` used `updateOrCreate(['id' => 1], ...)`, which mass-assigns the primary key on the create path. `id` was never fillable, so Eloquent had been dropping it silently and the row got an auto-increment id regardless — the `id => 1` never did anything. It now uses `firstOrNew()`, which expresses the single-row intent without touching the key.

The prose conventions this enforces still live in controllers.md ("Always select() only needed columns") — strict mode is the mechanism, not a replacement for the rule.

## Login throttled 5/min by email+IP via RateLimiter::for('login')
AppServiceProvider::boot() defines RateLimiter::for('login') — Limit::perMinute(5)->by(lowercased email + '|' + $request->ip()). Applied via `throttle:login` middleware on both routes/web.php's POST /login and routes/api.php's POST /v1/login, so web and mobile clients share the same limiter definition. Keying by IP as well as email means the IP seen by Laravel must be the real client IP, not a proxy's — see the TRUSTED_PROXIES rule in this same area. A 429 is returned raw (ThrottleRequestsException), not routed through Inertia's error-prop handling, so the web login form gets a hard error response rather than an inline validation message on lockout. Tests: 'login attempts are throttled after too many failures' in tests/Feature/Admin/LoginControllerTest.php and tests/Feature/Api/V1/AuthControllerTest.php.

## Telescope authorization must not bypass the gate in the local environment
The vendor TelescopeApplicationServiceProvider::authorization() registers `Telescope::auth(fn ($request) => app()->environment('local') || Gate::check('viewTelescope', [$request->user()]))` — the `environment('local') ||` ORs away the gate entirely whenever APP_ENV=local, which is also the exact environment real local dev machines run under. That left Telescope open to any visitor, authenticated or not, on local.

App\Providers\TelescopeServiceProvider now overrides `authorization()` (not just `gate()`) to drop that bypass: `Telescope::auth(fn ($request) => Gate::check('viewTelescope', [$request->user()]))`. Don't reintroduce the environment bypass.

Note Gate::check() resolves the current user from the auth guard, not from `$request->user()` — the array arg is passed through to the ability closure as extra args, not used to pick the user being checked. Tests must use `actingAs()`, not `$request->setUserResolver()`, to exercise this (see 'Telescope::check() still enforces the viewTelescope gate in the local environment' in tests/Feature/TelescopeAccessTest.php, which forces app()['env']='local' and asserts guest/non-admin/admin outcomes directly against Telescope::check()).

Pulse's equivalent (Laravel\Pulse\Http\Middleware\Authorize) has no such bypass — it just calls `$gate->authorize('viewPulse')` — so Pulse was already safe; AppServiceProvider::boot() defines `Gate::define('viewPulse', User::isAdminGate())` which wins over Pulse's own vendor default (`fn ($user=null) => $app->environment('local')`) since it runs after Pulse's queued callAfterResolving(Gate::class, ...) callback fires. Don't remove that Gate::define call from AppServiceProvider.

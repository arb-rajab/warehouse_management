---
paths:
  - app/Providers/TelescopeServiceProvider.php
  - 'app/Providers/AppServiceProvider.php,bootstrap/app.php,routes/web.php,routes/api.php'
---

# App Providers

## Telescope::tag()/filter() closures must never force a fresh Auth resolution
Use Auth::hasResolvedGuards() && Auth::hasUser() to peek at an already-resolved user, never Auth::check()/Auth::id()/Auth::user() directly, inside any Telescope::tag() or Telescope::filter() closure. Those force a guard to resolve if not cached, which queries the users table; Telescope then tries to tag/filter *that* query too, re-entering the closure before the original lookup finishes, recursing until PHP's max_execution_time kills the request. This caused real login hangs/500s. hasResolvedGuards()+hasUser() only read state, never trigger a lookup — Telescope's own core tagging code (vendor/laravel/telescope/src/Telescope.php) uses the same pattern. Covered by tests/Feature/TelescopeAccessTest.php ("never forces a fresh auth lookup" / "includes the resolved user once one exists"). Not caught by phpunit.xml's normal run since TELESCOPE_ENABLED=false there — the regression tests call Telescope::$tagUsing directly instead of relying on the watcher pipeline.

## Login throttled 5/min by email+IP via RateLimiter::for('login')
AppServiceProvider::boot() defines RateLimiter::for('login') — Limit::perMinute(5)->by(lowercased email + '|' + $request->ip()). Applied via `throttle:login` middleware on both routes/web.php's POST /login and routes/api.php's POST /v1/login, so web and mobile clients share the same limiter definition. Keying by IP as well as email means the IP seen by Laravel must be the real client IP, not a proxy's — see the TRUSTED_PROXIES rule in this same area. A 429 is returned raw (ThrottleRequestsException), not routed through Inertia's error-prop handling, so the web login form gets a hard error response rather than an inline validation message on lockout. Tests: 'login attempts are throttled after too many failures' in tests/Feature/Admin/LoginControllerTest.php and tests/Feature/Api/V1/AuthControllerTest.php.

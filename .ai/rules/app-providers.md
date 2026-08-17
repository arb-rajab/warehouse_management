---
paths:
  - app/Providers/TelescopeServiceProvider.php
---

# App Providers

## Telescope::tag()/filter() closures must never force a fresh Auth resolution
Use Auth::hasResolvedGuards() && Auth::hasUser() to peek at an already-resolved user, never Auth::check()/Auth::id()/Auth::user() directly, inside any Telescope::tag() or Telescope::filter() closure. Those force a guard to resolve if not cached, which queries the users table; Telescope then tries to tag/filter *that* query too, re-entering the closure before the original lookup finishes, recursing until PHP's max_execution_time kills the request. This caused real login hangs/500s. hasResolvedGuards()+hasUser() only read state, never trigger a lookup — Telescope's own core tagging code (vendor/laravel/telescope/src/Telescope.php) uses the same pattern. Covered by tests/Feature/TelescopeAccessTest.php ("never forces a fresh auth lookup" / "includes the resolved user once one exists"). Not caught by phpunit.xml's normal run since TELESCOPE_ENABLED=false there — the regression tests call Telescope::$tagUsing directly instead of relying on the watcher pipeline.

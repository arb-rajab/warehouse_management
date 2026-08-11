---
paths:
  - 'app/Models/*.php'
---

# Models

## Every model needs a dedicated test file
Whenever a model is added or its behavior changes (relationships, casts, computed attributes, scopes, or custom methods like `isAdmin()`/`hasPallets()`), add or update its test in `tests/Feature/Models/{Model}Test.php` — don't rely only on incidental coverage from controller tests. Cover:
- Every relationship, with noise data (an unrelated record of the same type) proving it isn't just returning everything.
- Every cast — note this app casts dates to `Carbon\CarbonImmutable` (via `Date::use()` in `AppServiceProvider`), not `Illuminate\Support\Carbon`.
- Every computed attribute (`Attribute::make(get: ...)`) and custom `#[Scope]`, including cases where the scope only matters on non-trivial/shuffled input (e.g. `Cell::orderedByCoordinates` needs cells inserted out of order to actually exercise the ORDER BY).
- Every custom public method, in both its true/false or populated/empty branches.

This is additive to, not a replacement for, controller/feature tests that exercise the model through HTTP routes.

---
paths:
  - 'app/Models/*.php'
  - app/Models/CellStatusLog.php
  - app/Models/Row.php
  - app/Models/Pallet.php
---

# Models

## Every model needs a dedicated test file
Whenever a model is added or its behavior changes (relationships, casts, computed attributes, scopes, or custom methods like `isAdmin()`/`hasPallets()`), add or update its test in `tests/Feature/Models/{Model}Test.php` — don't rely only on incidental coverage from controller tests. Cover:
- Every relationship, with noise data (an unrelated record of the same type) proving it isn't just returning everything.
- Every cast — note this app casts dates to `Carbon\CarbonImmutable` (via `Date::use()` in `AppServiceProvider`), not `Illuminate\Support\Carbon`.
- Every computed attribute (`Attribute::make(get: ...)`) and custom `#[Scope]`, including cases where the scope only matters on non-trivial/shuffled input (e.g. `Cell::orderedByCoordinates` needs cells inserted out of order to actually exercise the ORDER BY).
- Every custom public method, in both its true/false or populated/empty branches.

This is additive to, not a replacement for, controller/feature tests that exercise the model through HTTP routes.

## CellStatusLog::attachNextLogs() computes next_log_at/duration_seconds for a pallet
`CellStatusLog::attachNextLogs($logs)` sets `next_log_at` and `duration_seconds` on each log in an Eloquent collection — the `created_at` of the next log sharing the same `pallet_id` (ordered by `created_at`, then `id`), or `null` when this is the newest entry for that pallet, plus the seconds until that timestamp (or until `now()` when `next_log_at` is null). It's one extra query for the whole collection (grouped in PHP, selecting only `id`/`pallet_id`/`created_at` off the siblings — no other columns, no eager loads), not N+1 per row. Call it on `$logs->getCollection()` right after `paginate()`, before building `CellStatusLogResource::collection()`. Both `Admin\CellStatusLogController::index()` and `Api\V1\CellStatusLogController::index()` call it.

Special case: a `TransferredOut` entry's immediate next same-pallet log is always the paired `TransferredIn` written in the same transaction (see `PalletController::transfer`) — that pairing isn't a movement of its own, so it's skipped (index+2 instead of index+1) in favor of whatever happens after the pallet lands in the destination cell.

`duration_seconds` is computed server-side deliberately: by the time `attachNextLogs()` runs, both timestamps are already Carbon instances in memory, so the diff is a single CPU-only subtraction — negligible next to the sibling query/hydration/serialization already happening in the same request, so there's no real load argument for pushing it to the client. Both `next_log_at`/`duration_seconds` are plain dynamic attributes (`setAttribute`), not real columns/relations — `CellStatusLogResource` reads them directly and exposes `next_log_at` as an ISO8601 string or `null`. Do not remove `duration_seconds` in favor of frontend-computed durations without discussing it first — this was tried and reverted.

## Row::filterOptions() is the shared rows+maxColumnNumber filter-dropdown shape
`Row::filterOptions()` returns `['rows' => ..., 'maxColumnNumber' => ...]` — the row-letter list and max column count used to populate the row/column filter dropdowns. `Admin\CellController::index()` and `Admin\CellStatusLogController::index()` both spread it into their Inertia `filterOptions` prop (`...Row::filterOptions()`) instead of re-querying Row directly. Extend this method (not a second inline query) if another admin listing needs the same dropdown data.

## Stale pallets have no fixed threshold — every caller supplies the day count
`Pallet::STALE_AFTER_DAYS` (and the `is_stale` attribute/`stale()` scope built on it) were removed entirely — there is no default "stale" day count anywhere, admin or mobile API. `Pallet::isStaleAfter(int $days): bool` is the single source of truth for the computation; `CellResource`'s `is_stale` field reads `$request->integer('stale_after_days')` per-request and is `null` when that param is absent, not `false`. Any new stale-related feature must accept an explicit day count from its caller rather than reintroducing a constant. In tests, use `Pallet::factory()->stale()` (backdates `created_at` by a fixed, generously-old 30 days in `afterCreating`) as a pallet old enough to read as stale under any reasonable threshold, instead of hand-rolling a `backdate()` call.

## Don't call another model's #[Scope] method inside whereHas() — Larastan loses the generic type
Calling a model-specific `#[Scope]` method from inside a `whereHas('relation', fn (Builder $q) => ...)` closure on a *different* model fails PHPStan/Larastan with "Call to an undefined method Builder<Model>::x()" — the closure's `Builder $q` type-hint has no generic, so Larastan can't resolve the relation to the related model's class and falls back to the base `Model` template, even though it works fine at runtime. `CellStatusLog::filtered()`'s `expires_within_days` condition (inside `whereHas('pallet', ...)`) is the current concrete example: it inlines `whereDate('expiration_date', '<=', now()->addDays(...))` rather than calling any `Pallet` scope. Do the same for any future cross-model filter inside `whereHas`/`whereDoesntHave` — don't try to fix it with `@phpstan-ignore`, an inline `@var` override, or a type cast.

## There is no single-window "expiring soon" server prop anymore — only the expires-within-days highlight filter
`Admin\RowController::show()` and `Admin\CellController::index()` (the warehouse map) used to accept a caller-adjustable `expiring_days` query param and compute/pass `expiringDays`/`expiringSoonUntil` as Inertia props (backed by a since-removed `Pallet::EXPIRING_SOON_DAYS` default and the `ExpiringDaysInput.vue` control), driving an automatic expiry border/badge on every cell regardless of any filter. This was removed as a straight duplicate of the client-side expires-within-days highlight filter (`cellHighlight.expiresWithinDays`, see lib.md) and because emphasizing expired/soon cells without the user opting into a filter was undesired. Don't reintroduce a page-local `expiring_days`/`expiringSoonUntil` prop for Rows/Show or the Cells map — the highlight filter already covers it, filter-gated instead of automatic.

`Admin\DashboardController::index()` is unaffected by this: it still shows fixed 7/14/30/60-day cards (`EXPIRING_WINDOW_DAYS`) plus a separate adjustable custom-days card (`DEFAULT_CUSTOM_EXPIRING_DAYS`, currently 45) via its own `expiring_days` query param — this is a dashboard-only aggregate-count feature, not a per-cell highlight, and was intentionally left as-is.

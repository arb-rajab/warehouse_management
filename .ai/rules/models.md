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

## Pallet staleness: STALE_AFTER_DAYS is the single source of truth for the admin UI and dashboard; the mobile API takes an explicit day count
A pallet is "stale" once it's been stored longer than `Pallet::STALE_AFTER_DAYS` (currently 3 days), regardless of its cell's current state (full/opened). The admin `Cell::filtered()`'s `stale` filter and `DashboardController`'s stale-pallet count both still read this constant. The mobile API instead uses `Pallet::isStaleAfter(int $days): bool`, which takes a caller-supplied day count rather than the fixed constant — `CellResource`'s `is_stale` field reads `$request->integer('stale_after_days')` per-request and is `null` when that param is absent, not `false`. In tests, use `Pallet::factory()->stale()` (backdates `created_at` by a fixed, generously-old 30 days in `afterCreating`, comfortably past either the fixed constant or any reasonable caller-chosen threshold) instead of hand-rolling a `backdate()` call.

## Don't call another model's #[Scope] method inside whereHas() — Larastan loses the generic type
Calling a model-specific `#[Scope]` method (e.g. `Pallet::stale()`) from inside a `whereHas('relation', fn (Builder $q) => ...)` closure on a *different* model fails PHPStan/Larastan with "Call to an undefined method Builder<Model>::stale()" — the closure's `Builder $q` type-hint has no generic, so Larastan can't resolve the relation to the related model's class and falls back to the base `Model` template, even though it works fine at runtime. This is why `Cell::filtered()`'s `stale` filter inlines the condition (`$palletQuery->where('created_at', '<=', now()->subDays(Pallet::STALE_AFTER_DAYS))`) instead of calling `$palletQuery->stale()`, matching how the adjacent `expiration_date` filter in the same method already inlines its condition rather than calling into a Pallet scope. Do the same for any future cross-model filter inside `whereHas`/`whereDoesntHave` — don't try to fix it with `@phpstan-ignore`, an inline `@var` override, or a type cast.

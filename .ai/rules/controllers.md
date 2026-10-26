---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Inertia pagination helper and shared Cell query shapes
Inertia pages need the `data` + `meta` array shape, which plain serialization of a ResourceCollection drops. Use `$this->paginated($resourceCollection)` from the base Controller — do not re-type `->response()->getData(true)`.

For cell queries use the shared shapes rather than inline strings:
- `Cell::WITH_CONTENTS` — the pallet + product eager loads.
- `Cell::WITH_ROW_AND_CONTENTS` — the same plus `row:id,letter`, for cells not already queried through their row.
- `->orderedByCoordinates()` scope — replaces `->orderBy('cell_number')->orderBy('flat_number')`.

Pallet actions delegate cell/pallet locking to `PalletActionService`. Its public `lockCell($id)` locks and guards a cell inside the caller's transaction; its private `lockPalletInCell()` locks cell first, pallet second, then checks movement, ETag preconditions and cell guards. Keep these checks in the service. Row resize/delete and cell activation have their own locked transactions for their distinct operations.

## Cell status logging
`PalletActionService` owns pallet mutations and their matching `CellStatusLog` writes inside the same `DB::transaction`; both pallet controllers and accepted verification corrections delegate to it. Store/open/empty/transfer log cell-state transitions; removing boxes and changing product also log without changing state. Expiration-date and positive remaining-box corrections alone do not log. `transfer()` writes two rows (`TransferredOut` on the source cell, `TransferredIn` on the destination cell), linked via `related_cell_id`. `emptyLockedPallet()` captures product id, pallet id and remaining boxes before deleting the pallet. `CellStatusLog.product_id` is nullable with `nullOnDelete` so history survives deleting the product; `pallet_id` intentionally has no FK. `Admin\CellController::toggleActive()` separately logs activation changes in its own transaction.

## Always select() only needed columns before paginate()/get()
Every `Model::query()->...->paginate()/get()` in a controller must have an explicit `->select([...])` (or scoped eager-load like `'relation:col,col'`) listing only the columns the Resource/Inertia page actually reads — never bare `Model::query()->paginate(...)`. This was missed in both `RowController::index` (Admin and Api/V1) until fixed; every other index action (`UserController`, `ProductController`, `CellController`, `CellStatusLogController`) already follows this. Keep the foreign key in a relation's column list even if the frontend doesn't render it.

## CellStatusLog.pallet_id has no FK constraint on purpose
`cell_status_logs.pallet_id` is a plain nullable `unsignedBigInteger`, not `constrained()`. `PalletActionService::emptyLockedPallet()` hard-deletes the pallet. A `nullOnDelete` FK would clear its id from earlier logs; a cascade would delete the logs, and a restrictive FK would block emptying. Keeping the id without a constraint preserves "view all logs for this pallet". The service captures `$pallet->id` before deletion and passes it to `logCellStatus()`. `CellStatusLog::pallet()` is a plain `belongsTo` with no DB constraint backing it; it resolves to null once the pallet is gone, but `pallet_id` stays intact for filtering/grouping. Admin/CellStatusLogController filters on `pallet_id` without an `exists:pallets,id` rule for the same reason.

## Shared dashboard stats computation lives in Concerns/BuildsDashboardStats trait
Admin\DashboardController (Inertia page) and Api\V1\DashboardController (mobile JSON endpoint at GET /api/v1/dashboard) both use the `App\Http\Controllers\Concerns\BuildsDashboardStats` trait for occupancy/expiring/activity-count computation, so the two stay identical. Each controller still owns its own response envelope (Inertia::render vs response()->json) and its own ShowDashboardRequest (Admin\ vs Api\V1\ namespace), both delegating `rules()` entirely to `App\Http\Requests\Concerns\FiltersDashboard::dashboardFilterRules()` the same way `FilterCellStatusLogsRequest` delegates to `FiltersCellStatusLogs`. The mobile dashboard route has no role gate — it sits in the same auth:sanctum group as the other v1 endpoints (products, rows, pallets), matching the existing convention that v1 API routes aren't role-restricted. If a third dashboard consumer appears, extend the trait rather than copying its private methods again.

The admin dashboard sends `Product::selectedOptions($productIds ?? [])` in `filterOptions.products`: just the already-selected products, since its filter fetches the catalog on demand (http-controllers-admin.md). The Api/V1 dashboard sends the normalized filter values but no product-options list.

## Carbon's diffIn*() methods return float, not int
`CarbonInterface::diffInDays()`/`diffInMonths()`/etc. return `float` (they carry fractional precision even though the two `CarbonImmutable::toDateString()`-only inputs here are always whole days apart). Any PHPDoc `@return` array shape typing that field `int` will fail PHPStan's `return.type` check — cast explicitly (`(int) $today->diffInDays($until)`) rather than typing the shape `float`, since every consumer (Inertia prop, JSON response, query param) expects a whole day count. This bit `BuildsDashboardStats::expiringMonthWindow()`'s `days` field and only surfaced in CI, since PHPStan can't run in this sandbox (see CLAUDE.md's sandbox section) — don't assume a Carbon diff method returns `int` just because the values in practice are whole numbers.

## Redirect quick-actions to an explicit route + $request->query(), never back()
Any mutating action fired from a row inside a paginated/filtered Inertia index (acknowledge, delete, etc.) must redirect with `redirect()->route('admin.x.index', $request->query())`, not `back()`. `back()` depends on session/referrer state that isn't reliable for XHR/Inertia requests and can land on an unrelated page. The frontend must also send the current page/filters on the action's URL — use the Wayfinder action helper's `{ mergeQuery: {} }` option (e.g. `destroy(row, { mergeQuery: {} })`), which merges in `window.location.search` automatically. Without it, `$request->query()` is empty and the redirect silently resets to page 1 with no filters. See UserController::destroy, RowController::destroy for the single-origin version of this pattern.

## Read vendor source before writing generics-style PHPDoc
Before writing `@return SomeClass<T>` or `@param SomeClass<T>` on a framework or library class, verify the class is declared generic by checking its source on disk:

```bash
grep -n 'class JsonResponse\|@template' vendor/laravel/framework/src/Illuminate/Http/JsonResponse.php
```

If the class carries no `@template` declaration, PHPStan's `generics.notGeneric` rule will reject the annotation. `Illuminate\Http\JsonResponse` is not generic — omit the type parameter entirely and let Scramble infer the response shape from `response()->json([...])`. Only annotate with a type parameter when the class's own source declares `@template`.

## CellStatusLogController::acknowledgeFlags redirects by an explicit return_to, not Referer/back()
Acknowledging a log's flags is triggered from two different pages — CellStatusLogs/Index.vue (the default/omitted case) and Users/Show.vue (`return_to: 'user'`) — so the target can't be inferred server-side from the Referer header (stripped by `config/secure-headers.php`) or session `back()` state. `AcknowledgeCellStatusLogFlagsRequest` validates `return_to` against `in:user` (anything else/omitted falls through to the `admin.cell-logs.index` default); `CellStatusLogController::redirectAfterAcknowledge` picks the route. The 'user' destination always resolves to `$cellStatusLog->user_id` (the log's own actor), never a request-supplied user id — Users/Show only ever lists that one user's actions, so there's nothing to look up. `resources/js/lib/cellStatusLogDisplay.ts`'s shared `acknowledgeFlags(log, returnTo?)` sends the field; don't duplicate the `hasUnacknowledgedFlags`/`acknowledgeFlags` logic locally in a third page — extend the shared helper instead.

## Boolean filters are read with boolean(), never filled()
`NormalizesBooleanFilters` merges a real `false` into the request for `?expired=false`, and `$request->filled()` is true for `false`. So `filled('expired')` applies the expired filter when the caller turned it off. `Admin\ProductController` did exactly that until its three sites were switched to `boolean('expired')`, matching `CellController` and `Pallet::matchingHighlightFilters()`. Use `boolean()` for every normalized boolean param; keep `filled()` for value params (`state`, `expires_within_days`, ids).

## One spelling for each response and role check
- An empty API response is `response()->noContent()` (return type `Illuminate\Http\Response`), not `response()->json(null, 204)`.
- "Is this user an admin" is `User::isAdmin()`. Don't call `hasRole('admin')` directly; the role name lives in that one method.

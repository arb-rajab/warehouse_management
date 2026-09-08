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

PalletController::lockCell($id) is the only place that should build `Cell::query()->lockForUpdate()->findOrFail(...)`; every state transition reads its cells through it inside the surrounding transaction.

## Cell status logging
Every action in `PalletController` that changes a cell's `state` (`store`, `open`, `empty`, `transfer`) must write a matching `CellStatusLog` row inside the same `DB::transaction` — this is the audit trail `Admin/CellStatusLogController` reads. `transfer` writes two rows (`TransferredOut` on the source cell, `TransferredIn` on the destination cell), linked via `related_cell_id`, since one row can't hold two `cell_id`s. Capture the pallet's `product_id` before any delete (`empty` removes the pallet) — `CellStatusLog.product_id` is nullable and `nullOnDelete` specifically so the log survives losing its pallet.

## Always select() only needed columns before paginate()/get()
Every `Model::query()->...->paginate()/get()` in a controller must have an explicit `->select([...])` (or scoped eager-load like `'relation:col,col'`) listing only the columns the Resource/Inertia page actually reads — never bare `Model::query()->paginate(...)`. This was missed in both `RowController::index` (Admin and Api/V1) until fixed; every other index action (`UserController`, `ProductController`, `CellController`, `CellStatusLogController`) already follows this. Keep the foreign key in a relation's column list even if the frontend doesn't render it.

## CellStatusLog.pallet_id has no FK constraint on purpose
`cell_status_logs.pallet_id` is a plain nullable `unsignedBigInteger`, not `constrained()`. `PalletController::empty()` hard-deletes the pallet, so any `onDelete` rule (including `nullOnDelete`) would null out `pallet_id` on every earlier log row for that pallet the instant it's emptied — wiping the exact history "view all logs for this pallet" needs most. Always pass `$pallet->id` into `logCellStatus()` (capture it into a local var before `$pallet->delete()` in `empty()`, same as `$productId`). `CellStatusLog::pallet()` is a plain `belongsTo` with no DB constraint backing it; it resolves to null once the pallet is gone, but `pallet_id` stays intact for filtering/grouping. Admin/CellStatusLogController filters on `pallet_id` without an `exists:pallets,id` rule for the same reason.

## Shared dashboard stats computation lives in Concerns/BuildsDashboardStats trait
Admin\DashboardController (Inertia page) and Api\V1\DashboardController (mobile JSON endpoint at GET /api/v1/dashboard) both use the `App\Http\Controllers\Concerns\BuildsDashboardStats` trait for occupancy/expiring/activity-count computation, so the two stay identical. Each controller still owns its own response envelope (Inertia::render vs response()->json) and its own ShowDashboardRequest (Admin\ vs Api\V1\ namespace), both delegating `rules()` entirely to `App\Http\Requests\Concerns\FiltersDashboard::dashboardFilterRules()` the same way `FilterCellStatusLogsRequest` delegates to `FiltersCellStatusLogs`. The mobile dashboard route has no role gate — it sits in the same auth:sanctum group as the other v1 endpoints (products, rows, pallets), matching the existing convention that v1 API routes aren't role-restricted. If a third dashboard consumer appears, extend the trait rather than copying its private methods again.

The two dashboards deliberately diverge on one prop: the admin sends `Product::selectedOptions($productIds ?? [])` (just the already-selected chips, since the admin filter fetches the catalog on demand — see http-controllers-admin.md), while Api\V1\DashboardController sends `Product::filterOptions()`, the whole catalog, because the mobile client has no search endpoint to fetch it from. That is the single remaining caller of `Product::filterOptions()` and it is intentional — don't "fix" it to match the admin side, and don't delete `Product::filterOptions()` as an unused helper.

## Redirect quick-actions to an explicit route + $request->query(), never back()
Any mutating action fired from a row inside a paginated/filtered Inertia index (acknowledge, delete, etc.) must redirect with `redirect()->route('admin.x.index', $request->query())`, not `back()`. `back()` depends on session/referrer state that isn't reliable for XHR/Inertia requests and can land on an unrelated page. The frontend must also send the current page/filters on the action's URL — use the Wayfinder action helper's `{ mergeQuery: {} }` option (e.g. `destroy(row, { mergeQuery: {} })`), which merges in `window.location.search` automatically. Without it, `$request->query()` is empty and the redirect silently resets to page 1 with no filters. See UserController::destroy, RowController::destroy for the single-origin version of this pattern.

## CellStatusLogController::acknowledgeFlags redirects by an explicit return_to, not Referer/back()
Acknowledging a log's flags is triggered from two different pages — CellStatusLogs/Index.vue (the default/omitted case) and Users/Show.vue (`return_to: 'user'`) — so, like the pallet actions and toggle-active (see `Concerns\RedirectsAfterCellAction`), the target can't be inferred server-side from the Referer header (stripped by `config/secure-headers.php`) or session `back()` state. `AcknowledgeCellStatusLogFlagsRequest` validates `return_to` against `in:user` (anything else/omitted falls through to the `admin.cell-logs.index` default); `CellStatusLogController::redirectAfterAcknowledge` picks the route. The 'user' destination always resolves to `$cellStatusLog->user_id` (the log's own actor), never a request-supplied user id — Users/Show only ever lists that one user's actions, so there's nothing to look up. `resources/js/lib/cellStatusLogDisplay.ts`'s shared `acknowledgeFlags(log, returnTo?)` sends the field; don't duplicate the `hasUnacknowledgedFlags`/`acknowledgeFlags` logic locally in a third page — extend the shared helper instead.

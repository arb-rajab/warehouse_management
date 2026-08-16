---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## No authorize() stubs; resolve slot coordinates through the shared trait
Do not add `public function authorize(): bool { return true; }`. `FormRequest::passesAuthorization()` returns true when the method does not exist, so the stub is pure noise — it was removed from all 8 requests. Only define `authorize()` when it actually denies something.

Requests that address a slot by human-readable coordinates (row letter + cell number + flat number) use `App\Http\Requests\Concerns\ResolvesSlotFromCoordinates`:
- `coordinateRules($prefix)` returns the three field rules.
- `resolveSlot($validator, $prefix)` looks up the Row by letter, resolves the Cell via the `atCoordinates` scope, and adds "This slot does not exist for this row." to `{prefix}cell_number` when there is none.
- `resolvedSlot()` is the post-validation accessor (throws if called before validation).

Pass the field prefix (`''` for StorePalletRequest, `'to_'` for TransferPalletRequest) rather than re-implementing the lookup — the two copies used to drift.

## Cell status log filters: shared trait + model scope, not per-request duplication
The product/pallet/row/column/user/action/date-range filters for listing `CellStatusLog` are shared by `Admin\FilterCellStatusLogsRequest` and `Api\V1\FilterCellStatusLogsRequest` via `App\Http\Requests\Concerns\FiltersCellStatusLogs::cellStatusLogFilterRules()`. The actual query filtering lives once, as `CellStatusLog::filtered(Request $request)` (a `#[Scope]`), used by both `Admin\CellStatusLogController::index()` and `Api\V1\CellStatusLogController::index()` — it reads straight off `$request->filled()`/`->integer()`/etc, not `->validated()`, so an absent filter is skipped. Column/eager-load shape is likewise shared via `CellStatusLog::SELECT_COLUMNS` / `CellStatusLog::WITH_DETAILS`. If a third cell-log listing appears, extend these rather than re-writing the filter chain.

## ValidatesOptionalNote: merge via noteRules(), don't inline the note rule
Requests that need an optional note field alongside other rules (e.g. `StorePalletRequest`, `TransferPalletRequest`) must merge `App\Http\Requests\Concerns\ValidatesOptionalNote::noteRules()` into their `rules()` array with `...$this->noteRules()`, not retype `'note' => ['nullable', 'string', 'max:1000']`. `EmptyPalletRequest`/`OpenPalletRequest` (which validate nothing else) just `use` the trait and inherit its `rules()` directly. The trait's `rules()` itself calls `noteRules()`, so there is exactly one definition of the note rule.

## Row/column/expiration filter rules live in FiltersByRowAndExpiration
`row_id`, `column_number`, `expiration_date_from`, `expiration_date_to` validation is shared via `App\Http\Requests\Concerns\FiltersByRowAndExpiration::rowAndExpirationFilterRules()`, used by both `Admin\FilterCellsRequest` and `Concerns\FiltersCellStatusLogs` (itself shared by Admin/Api\V1 `FilterCellStatusLogsRequest`). Merge it with `...$this->rowAndExpirationFilterRules()` inside `rules()` rather than retyping these four rules. If a third request needs this slot/expiration filter slice, extend this trait instead of copying.

## FiltersDashboard: Admin/Api\V1 ShowDashboardRequest share rules() via a trait
Admin\ShowDashboardRequest and Api\V1\ShowDashboardRequest both `use App\Http\Requests\Concerns\FiltersDashboard` and delegate `rules()` entirely to `$this->dashboardFilterRules()` — same pattern as FilterCellStatusLogsRequest -> FiltersCellStatusLogs. FiltersDashboard itself composes FiltersByProductIds (adds `expiring_days`), so `productIds()` stays available. Don't inline `['expiring_days' => [...], ...$this->productIdsFilterRules()]` directly in a ShowDashboardRequest again — extend FiltersDashboard instead if a third dashboard consumer needs different/extra rules.

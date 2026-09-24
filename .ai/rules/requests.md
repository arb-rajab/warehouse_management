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

## FiltersDashboard: Admin/Api\V1 ShowDashboardRequest share rules() via a trait
Admin\ShowDashboardRequest and Api\V1\ShowDashboardRequest both `use App\Http\Requests\Concerns\FiltersDashboard` and delegate `rules()` entirely to `$this->dashboardFilterRules()` — same pattern as FilterCellStatusLogsRequest -> FiltersCellStatusLogs. `dashboardFilterRules()` defines `expiring_days` and `stale_days` (both `nullable|integer|min:1`) directly and composes FiltersByProductIds, so `productIds()` stays available. Don't inline `['expiring_days' => [...], 'stale_days' => [...], ...$this->productIdsFilterRules()]` directly in a ShowDashboardRequest again — extend FiltersDashboard instead if a third dashboard consumer needs different/extra rules.

## GET-query booleans need prepareForValidation() before a 'boolean' rule
Laravel's `boolean` validation rule only accepts `[true, false, 0, 1, '0', '1']` (strict in_array) — it rejects the literal string "true"/"false", which is exactly how a JS boolean serializes into an Inertia `<Link>` GET query string (e.g. `?expired=true`). A field validated as `['nullable', 'boolean']` will 422 (redirecting back) on that value.

Fix: normalize in `prepareForValidation()` with `filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`, merging back only when non-null — this coerces "true"/"1"/"on"/"yes" and "false"/"0"/"off"/"no" to real booleans while leaving genuinely invalid values (e.g. "bogus") untouched so the `boolean` rule still rejects them. See `ShowCellMapRequest::prepareForValidation()` for the pattern (fixed for the dashboard's "Expired" tile, which links with `expired=true`).

A test asserting `?expired=1` passes is not proof the feature works — the frontend actually sends `expired=true`; test that literal value too.

## Validation-failure redirects: don't override getRedirectUrl()/redirectTo per request

A `FormRequest` validation failure's default `redirect()->back()` used to silently land on `admin.dashboard` instead of the submitted form — caused by this app's `no-referrer` policy plus Inertia's `X-Requested-With` header breaking Laravel's session-based "previous URL" tracking for every SPA navigation, not just one form. Fixed once, systemically, via `App\Http\Middleware\StoreInertiaPreviousUrl` (see `.ai/rules/middleware.md`) rather than per-`FormRequest` `getRedirectUrl()`/`redirectTo` overrides — don't add those overrides to work around a redirect-target issue; check whether the middleware's criteria need adjusting instead.

## FiltersByProductStatus now also covers the mobile product listing and rows/full
`App\Http\Requests\Concerns\FiltersByProductStatus` (`product_status=active|inactive` -> `product_published` boolean) is used by `Api\V1\ShowDashboardRequest`, `Api\V1\FilterCellStatusLogsRequest`, `Api\V1\FilterProductsRequest`, and `Api\V1\ShowRowsFullRequest`. `ProductController::index()` applies it directly against `products.published`; `RowController::full()` applies it as `whereHas('pallet.product', ...)` inside the `cells` eager-load closure. Since `FilterProductsRequest`/`ShowRowsFullRequest` have no `product_id` field, the trait's `prohibits:product_id,product_id.*` rule is inert there — harmless, kept for consistency with the other two consumers rather than forked into a narrower rule set.

`ProductController::index()` is the one consumer that treats the trait's `productPublished(): ?bool` as defaulting to `true` rather than "no filter" — an unfiltered `GET /api/v1/products` excludes inactive products, and `?product_status=inactive` is the only way to see them. Every other consumer (`ShowDashboardRequest`, `FilterCellStatusLogsRequest`, `ShowRowsFullRequest`) keeps `null` meaning "don't filter by product status" — don't change those to match without a specific reason, since they're occupancy/reporting views where an inactive product's existing pallets/logs still need to show up unfiltered.

`RowController::full()`'s `product_status` filter drops every cell whose pallet doesn't match, **including empty cells** — a filtered call no longer returns the full per-row grid. This mirrors what the mobile client used to compute itself (pre-resolve matching product ids, then keep only cells whose pallet's `product_id` was in that set) now done server-side in one query; an unfiltered call is unaffected. Extend this trait rather than adding a fourth private `product_status` implementation if another mobile listing needs the same filter.

---
paths:
  - 'app/Models/Product.php,app/Http/Requests/Concerns/FiltersByProductIds.php'
---

# Concerns

## Product::selectedOptions() and FiltersByProductIds are the shared product-filter shapes
`Product::selectedOptions($ids)` returns the id/name list for the given ids only — used by Admin CellController, CellStatusLogController, DashboardController, and RowController's Inertia `filterOptions` prop to hydrate a product filter's already-selected labels, not to list every product (see `.ai/rules/http-controllers-admin.md` for why `Product::filterOptions()` — no args, every product — is reserved for the mobile API and must not be reused here). Any FormRequest that accepts a caller-chosen set of product ids (currently FilterCellMapRequest, ShowDashboardRequest) should `use App\Http\Requests\Concerns\FiltersByProductIds` and merge `...$this->productIdsFilterRules()` into `rules()`, then read `$request->productIds()` (returns `list<int>|null`, null when absent) instead of re-deriving `array_map('intval', $request->array('product_id'))` at the call site.

## Product searches return an inactive product only while the warehouse holds it
`Product::activeOrInWarehouse()` is `published = 1 OR inWarehouse()`, with the OR grouped. Apply it to every product listing or search a client browses: currently `Api\V1\ProductController::index()` and `Admin\ProductController::search()`. Two kinds of endpoint are deliberately exempt (issue #383):
- `Admin\ProductController::index()`, the products page, lists every product. Its `inactive`/`hide_inactive` filters are unchanged.
- Lookups by id (`Api\V1\ProductController::show()`/`pallets()`) still resolve a deactivated product with no pallets, so an old QR label shows `active: false` rather than 404ing.

`selectedOptions()` stays unscoped: it only echoes labels for ids the caller already chose. Endpoints that reach a product through a pallet (cells, rows, pallets) satisfy the rule by construction. A new product listing or search takes the scope rather than its own `published` condition.

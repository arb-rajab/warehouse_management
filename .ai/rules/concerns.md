---
paths:
  - 'app/Models/Product.php,app/Http/Requests/Concerns/FiltersByProductIds.php'
---

# Concerns

## Product::selectedOptions() and FiltersByProductIds are the shared product-filter shapes
`Product::selectedOptions($ids)` returns the id/name list for the given ids only — used by Admin CellController, CellStatusLogController, DashboardController, and RowController's Inertia `filterOptions` prop to hydrate a product filter's already-selected labels, not to list every product (see `.ai/rules/http-controllers-admin.md` for why `Product::filterOptions()` — no args, every product — is reserved for the mobile API and must not be reused here). Any FormRequest that accepts a caller-chosen set of product ids (currently ShowCellMapRequest, ShowDashboardRequest) should `use App\Http\Requests\Concerns\FiltersByProductIds` and merge `...$this->productIdsFilterRules()` into `rules()`, then read `$request->productIds()` (returns `list<int>|null`, null when absent) instead of re-deriving `array_map('intval', $request->array('product_id'))` at the call site.

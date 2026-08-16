---
paths:
  - 'app/Models/Product.php,app/Http/Requests/Concerns/FiltersByProductIds.php'
---

# Concerns

## Product::filterOptions() and FiltersByProductIds are the shared product-filter shapes
`Product::filterOptions()` (mirrors `Row::filterOptions()`) returns the id/name list for admin product filter dropdowns — used by Admin CellController, CellStatusLogController, DashboardController, and RowController's Inertia `filterOptions` prop instead of each re-querying Product directly. Any FormRequest that accepts a caller-chosen set of product ids (currently ShowCellMapRequest, ShowDashboardRequest) should `use App\Http\Requests\Concerns\FiltersByProductIds` and merge `...$this->productIdsFilterRules()` into `rules()`, then read `$request->productIds()` (returns `list<int>|null`, null when absent) instead of re-deriving `array_map('intval', $request->array('product_id'))` at the call site.

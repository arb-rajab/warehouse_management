---
paths:
  - 'app/Http/Requests/Admin/FilterProductsRequest.php,resources/js/pages/Admin/Products/Index.vue'
---

# Warehouse products index filters and inactive stock

## Products page's state filter excludes `empty`
Unlike the cells map, the products index's `state` filter only allows `full`/`opened` (backend rule: `in:full,opened`; frontend options: `occupiedCellStates`). An empty cell never holds a product, so `state=empty` would always zero out every column on a per-product listing — it's a dead option here even though it's meaningful on the cell-centric map/log pages.

## `inactive` means deactivated AND still occupying the warehouse — a deliberate, later reversal
`FilterProductsRequest`'s `inactive` boolean (`?inactive=true`, normalized via `NormalizesBooleanFilters` the same way `expired` is) used to filter to bare `products.published = 0`, occupancy-agnostic by design. That was intentionally reversed on explicit user request: `Admin\ProductController::index()`'s `inactive` filter now also requires `Product::inWarehouse()` (`app/Models/Product.php`) — i.e. `published = 0 AND` at least one pallet still in a cell. The point of the filter is to surface *actionable* deactivated stock (something to pull or reconcile), not every deactivated catalog row regardless of whether the warehouse holds any of it. A deactivated product with zero pallets no longer matches `inactive=true`.

`Product::inWarehouse()` is a `#[Scope]` shared by this filter and the dashboard's `inactive_in_warehouse` count (`BuildsDashboardStats::inactiveInWarehouse()`) — the dashboard card's tile links straight to `admin/products?inactive=true`, so the two must always agree on which products count; don't let one drift to a different definition than the other. Note `products.published` itself is still exactly what `.ai/rules/shared-database.md`'s `published` section describes — a store-admin toggle unrelated to occupancy at the column level. What changed is only this UI filter's definition, not the column's meaning: a deactivated product with pallets and a freshly-activated product with none still coexist exactly as that section describes, `inactive=true` just no longer surfaces the latter kind of row (there is none in that scenario) or a deactivated-but-empty one.

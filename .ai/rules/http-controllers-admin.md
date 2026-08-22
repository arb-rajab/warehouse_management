---
paths:
  - 'app/Models/Product.php,app/Http/Requests/Concerns/FiltersByProductIds.php,app/Http/Controllers/Admin/ProductController.php'
---

# Http Controllers Admin

## Product::filterOptions() vs selectedOptions() — two different product-list shapes
`Product::filterOptions()` (no args) returns every product and is only for the mobile app's dashboard (`Api\V1\DashboardController`) — it doesn't scale and must not be reused elsewhere.

`Product::selectedOptions(array $ids = [])` returns only the given ids' id/name pairs. It's used by the 4 admin web controllers (RowController::show, CellController::index, CellStatusLogController::index, Admin\DashboardController::index) purely to hydrate a product filter's already-selected chip labels — the admin UI fetches the searchable catalog on demand instead, via `GET admin/products/search` (Admin\ProductController::search, paginated 20/page, `q` search param) consumed by the frontend's `FilterProductSelect.vue` (search box + infinite scroll, replacing `FilterMultiSelect` for the product filter only).

If a third admin page needs a product filter, use `selectedOptions()` + `FilterProductSelect.vue`, never `filterOptions()`.

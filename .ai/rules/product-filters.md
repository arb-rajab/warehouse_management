---
paths:
  - 'app/Models/Product.php'
  - 'app/Http/Requests/Concerns/FiltersByProductIds.php'
  - 'app/Http/Controllers/Admin/ProductController.php'
  - 'app/Http/Controllers/Api/V1/{ProductController,DashboardController}.php'
  - 'resources/js/components/FilterProductSelect.vue'
---

# Warehouse product search, visibility and selected filter labels

## Selected labels and searchable catalog

`Product::selectedOptions($ids)` hydrates only already-selected labels for admin
product filters, including pallet, cell-log, verification-round, dashboard and
row listings. It stays unscoped so labels for caller-selected IDs remain visible.
The admin catalog is fetched on demand with `FilterProductSelect.vue` from
`GET admin/products/search` (`q`, paginated 20 per page), not loaded in full.

Do not use `Product::filterOptions()` for new listings: it loads every product.
Older guidance described it as the mobile dashboard source; the current mobile
dashboard returns stats, dates and filters without a product-options list.
The method's legacy PHPDoc does not establish a current consumer.

Requests accepting chosen IDs should compose
`FiltersByProductIds::productIdsFilterRules()` and use `$request->productIds()`
(`list<int>|null`, null when absent), rather than repeating integer conversion.

## Active or still in the warehouse

`Product::activeOrInWarehouse()` groups `published = 1 OR inWarehouse()`.
Use it for browsable listings/searches, currently the API product index and
admin product search. Deliberate exemptions (issue #383):

- The admin products index lists every product, retaining its own
  `inactive`/`hide_inactive` filters (see [products index](products.md)).
- ID lookups (`Api/V1/ProductController::show()`/`pallets()`) resolve deactivated,
  unheld products so old QR labels return `active: false`, not a 404.
- Selected-label hydration is unscoped; pallet-based reads already reach a held
  product by construction. Occupancy/reporting filters must retain inactive
  products' existing pallets/logs.

Only the products index's search control opts out with
`<FilterProductSelect include-inactive>` → `useProductSearch({ includeInactive:
true })` → `include_inactive=1`. Keep that exemption local; other admin searches
and the API retain their visibility rule.

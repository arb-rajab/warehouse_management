---
paths:
  - 'app/Http/Requests/Admin/FilterProductsRequest.php,resources/js/pages/Admin/Products/Index.vue'
---

# Products

## Products page's state filter excludes `empty`
Unlike the cells map, the products index's `state` filter only allows `full`/`opened` (backend rule: `in:full,opened`; frontend options: `occupiedCellStates`). An empty cell never holds a product, so `state=empty` would always zero out every column on a per-product listing — it's a dead option here even though it's meaningful on the cell-centric map/log pages.

## `inactive` is the store admin's `published` flag, not an occupancy signal
`FilterProductsRequest`'s `inactive` boolean (`?inactive=true`, normalized via `NormalizesBooleanFilters` the same way `expired` is) filters to `products.published = 0` — the store admin's own kill switch for a product. It is unrelated to whether the product currently occupies any cell: a deactivated product can still have pallets sitting in cells, and a freshly activated one can have none yet. Do not redefine it as `whereDoesntHave('pallets')` or similar occupancy check — see `.ai/rules/shared-database.md`'s `published` section for why the two never imply each other.

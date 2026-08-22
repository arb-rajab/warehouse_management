---
paths:
  - 'app/Http/Requests/Admin/FilterProductsRequest.php,resources/js/pages/Admin/Products/Index.vue'
---

# Products

## Products page's state filter excludes `empty`
Unlike the cells map, the products index's `state` filter only allows `full`/`opened` (backend rule: `in:full,opened`; frontend options: `occupiedCellStates`). An empty cell never holds a product, so `state=empty` would always zero out every column on a per-product listing — it's a dead option here even though it's meaningful on the cell-centric map/log pages.

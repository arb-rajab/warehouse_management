---
paths:
  - 'resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellMap3D.vue'
---

# Cells Js Components

## 3D map view renders every flat, not just the selected one
CellMap3D.vue (toggled via viewMode in Cells/Index.vue) renders ALL flats at once — flats are stacked as real shelf levels along world Y — unlike the 2D grid, which pages one flat at a time. Its `map3DBands` computed is built from `cellHighlightSamples` (already loaded for every flat, for the 2D map's per-flat match badges), not from the current-flat-only `cells` prop. Don't feed `cells`/`cellAt` into the 3D view; keep using `cellHighlightSamples` so 2D/3D can never disagree on what counts as a highlight match (`matchesCellHighlight` is reused as-is).

`cellHighlightSamples`' pallet shape now also carries `product_name`/`product_image_url` (added when the 3D "faced cell" panel needed 2D-parity detail — `CellController::cellHighlightSamples()` eager-loads `pallet.product:id,name,image_url`) — it's no longer just the minimal match-computation shape the original doc comment describes. Every cell's 3D box itself still only shows `state` coloring + highlight/pulse outline (rendering full detail on every box at once would be unreadable/expensive); the full 2D-parity detail (image/name/dates) only ever renders in the single "faced cell" HUD panel (`CellMap3D.vue`'s `facedItem`/`facedGridCoordinate`), not per-box.

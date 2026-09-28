---
paths:
  - 'resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellMap3D.vue'
---

# Cells Js Components

## 3D map view renders every flat, not just the selected one
CellMap3D.vue (toggled via viewMode in Cells/Index.vue) renders ALL flats at once — flats are stacked as real shelf levels along world Y — unlike the 2D grid, which pages one flat at a time. Its `map3DBands` computed is built from `cellHighlightSamples` (already loaded for every flat, for the 2D map's per-flat match badges), not from the current-flat-only `cells` prop. Don't feed `cells`/`cellAt` into the 3D view; keep using `cellHighlightSamples` so 2D/3D can never disagree on what counts as a highlight match (`matchesCellHighlight` is reused as-is).

`cellHighlightSamples`' pallet shape now also carries `product_name`/`product_image_url` (added when the 3D "faced cell" panel needed 2D-parity detail — `CellController::cellHighlightSamples()` eager-loads `pallet.product:id,name,image_url`) — it's no longer just the minimal match-computation shape the original doc comment describes. Every cell's 3D box shows `state` coloring + highlight/pulse outline, plus — for a pallet whose product has an image — that image on inset panels on the box's four sides and top (`addProductImages`), with a border left so the state color stays readable. Nothing else is drawn per box (rendering full detail on every box at once would be unreadable/expensive); the full 2D-parity detail (name/dates/actions) only ever renders in the single "faced cell" HUD panel (`CellMap3D.vue`'s `facedItem`/`facedGridCoordinate`), not per-box.

## Product image panels: one instanced mesh per distinct image, rebuilt not patched
Image panels are one `THREE.InstancedMesh` per distinct `product_image_url` (named `'product-image'`, five face instances per pallet), rebuilt wholesale on both the full build and the `updateCellStatesAndLookup` fast path (placements are collected in those same passes — don't add a separate walk over bands). They're never mutated after creation, so they don't need `invalidateInstanceBounds`. Materials are cached per URL across rebuilds (`productImageMaterialsByUrl`, disposed with their textures on unmount) so each image downloads once; a material stays `visible: false` until its texture loads. Product images are served by the store app/CDN (`config/store.php`), not this app — WebGL needs CORS headers from that host, and a blocked load just leaves the box its plain state color. Panels are excluded from raycasting (`cellBoxAtScreenPoint` only tests `cellInstanceMeshes`).

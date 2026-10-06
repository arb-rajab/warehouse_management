---
paths:
  - 'resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/mapViewport.ts'
---

# Js Lib

## Cells map search is location-only; the 2D viewport never moves on its own
The Cells/Index.vue free-text search box only resolves row-letter+cell-number(+flat) locations (CellController::resolveLocationSearch) — there is deliberately no product-name search box. A prior "jump to next expired pallet" button was removed and, for a time, deliberately not reintroduced — that decision has since been reversed: the map now has generalized next/previous-match navigation across every active highlight filter (not just expired), see `.ai/rules/js-types.md`. Don't reintroduce a second, narrower "next expired" mechanism alongside it — extend the existing one instead.

The 2D map must never drag/re-center unless the user asks for it. Zoom (buttons, wheel, pinch) keeps the point under the cursor (wheel) or the container center fixed via `panKeepingAnchor` in mapViewport.ts; rotate keeps pan and zoom as they are (the previous A1 re-center, `centerFirstCellInViewport`/`recenterInstantly`, was removed — don't bring it back). The only deliberate centering is `centerLabelInViewport()` for the jumpToCell/search-pulse watcher.

No blank space may surround the pallets: the map content element has no padding, and `clampPanAxis` keeps the content's edges flush with or beyond the container's (content smaller than the container is pinned to the top-left, never floated to the middle). Reset (pan 0, zoom 1) and fit (`fitMapToView`) therefore leave the map flush at the top-left too.

The map-content flex-col container uses gap-6 (not gap-2) between row bands to visually read like warehouse aisles; the gap-2 between individual cell slots within a band is unchanged.

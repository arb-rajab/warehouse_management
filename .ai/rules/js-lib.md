---
paths:
  - 'resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/mapViewport.ts'
---

# Js Lib

## Cells map search is location-only; rotate/reset always re-center on cell A1
The Cells/Index.vue free-text search box only resolves row-letter+cell-number(+flat) locations (CellController::resolveLocationSearch) — there is deliberately no product-name search box. A prior "jump to next expired pallet" button was removed and, for a time, deliberately not reintroduced — that decision has since been reversed: the map now has generalized next/previous-match navigation across every active highlight filter (not just expired), see `.ai/rules/js-types.md`. Don't reintroduce a second, narrower "next expired" mechanism alongside it — extend the existing one instead.

Cell A1 (rows[0].letter + cell_number 1, on the current flat) is treated as the map's fixed orientation anchor: rotateLeftAndRecenter/rotateRightAndRecenter/resetViewAndRecenter in Index.vue call viewport.rotateLeft()/rotateRight()/reset() then `await nextTick()` then centerFirstCellInViewport(), so the view always re-centers on A1 after any rotation or reset regardless of the current axis orientation. The shared centering logic (container/target getBoundingClientRect + viewport.panBy) lives in centerLabelInViewport() and is reused by both the rotate/reset handlers and the existing jumpToCell search-pulse watcher — don't duplicate it.

The map-content flex-col container uses gap-6 (not gap-2) between row bands to visually read like warehouse aisles; the gap-2 between individual cell slots within a band is unchanged.

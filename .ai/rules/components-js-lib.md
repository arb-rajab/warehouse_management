---
paths:
  - 'resources/js/pages/Admin/Rows/Show.vue,resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellHighlightFilters.vue,resources/js/lib/cellHighlight.ts,resources/js/lib/cellStatus.ts'
---

# Components Js Lib

## Cell highlight "state" filter is multi-select
CellHighlightFilters.vue's state field uses FilterMultiSelect (not FilterSelect) so admins can highlight more than one cell state at once. `CellHighlightFiltersValue.state` is `Cell['state'][]` (empty array = no filter), not a single nullable string — `emptyCellHighlightFilters()`/`countActiveCellHighlightFilters()`/`matchesCellHighlight()` in lib/cellHighlight.ts all treat it as an array (`.length`/`.includes()`). Cells/Index.vue still receives a single nullable `initialHighlight.state` from the dashboard deep-link URL (CellController still validates `state` as a single string) and wraps it into a one-item array when seeding `highlightFilters`.

## isCellExpiringWithin excludes past-due; use isCellExpired for that
`isCellExpiringWithin` (cellStatus.ts) only matches pallets expiring today-through-N-days-out — it deliberately excludes already-past-due pallets. Use the separate `isCellExpired` helper (and the `CellHighlightFiltersValue.expired` / `CellHighlightSeed.expired` boolean) for "already overdue" highlighting, e.g. the dashboard's expired-pallets tile links to `/admin/cells?expired=1`, not `expires_within_days=0`. Don't reintroduce the old "already-expired trivially matches any window" behavior.

## Cells/Index.vue re-serializes active highlight filters into in-page navigation
`goToFlat`/`onSearchSubmit` in Cells/Index.vue rebuild the current `highlightFilters` into the `router.get` query via a local `highlightQuery()` helper, so switching flats or searching doesn't drop the active highlight filter from the URL. When adding a new highlight filter field, add it to `highlightQuery()` too, or it will silently disappear from the URL on in-page navigation.

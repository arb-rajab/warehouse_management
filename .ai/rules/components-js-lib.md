---
paths:
  - 'resources/js/pages/Admin/Rows/Show.vue,resources/js/components/CellHighlightFilters.vue,resources/js/lib/cellHighlight.ts,resources/js/lib/cellStatus.ts'
---

# Components Js Lib

## Cell highlight "state" filter is multi-select
CellHighlightFilters.vue's state field uses FilterMultiSelect (not FilterSelect) so admins can highlight more than one cell state at once. `CellHighlightFiltersValue.state` is `Cell['state'][]` (empty array = no filter), not a single nullable string — `emptyCellHighlightFilters()`/`countActiveCellHighlightFilters()`/`matchesCellHighlight()` in lib/cellHighlight.ts all treat it as an array (`.length`/`.includes()`).

## isCellExpiringWithin excludes past-due; use isCellExpired for that
`isCellExpiringWithin` (cellStatus.ts) only matches pallets expiring today-through-N-days-out — it deliberately excludes already-past-due pallets. Use the separate `isCellExpired` helper (and the `CellHighlightFiltersValue.expired` boolean) for "already overdue" highlighting, e.g. the dashboard's expired-pallets tile links with `expired: true`, not `expires_within_days=0`. Don't reintroduce the old "already-expired trivially matches any window" behavior. That `expired: true` boolean prop serializes into the GET query string as the literal string `"true"`, which `FilterMatchingPalletsRequest` must normalize in `prepareForValidation()` before its `boolean` rule runs — see requests.md.

## Matching functions accept the structural MatchableCell, not Cell
`isCellExpired`/`isCellExpiringWithin`/`isCellStale` (cellStatus.ts) and `matchesCellHighlight` (cellHighlight.ts) take a structural `MatchableCell` type (state + a `Pick`'d pallet) instead of the full `Cell` — a real `Cell` still satisfies it. Keep matching functions accepting `MatchableCell`, not `Cell`, so a lighter cell-like shape can reuse them without duplicating the matching logic.

---
paths:
  - 'resources/js/pages/Admin/Rows/Show.vue,resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellHighlightFilters.vue,resources/js/lib/cellHighlight.ts'
---

# Components Js Lib

## Cell highlight "state" filter is multi-select
CellHighlightFilters.vue's state field uses FilterMultiSelect (not FilterSelect) so admins can highlight more than one cell state at once. `CellHighlightFiltersValue.state` is `Cell['state'][]` (empty array = no filter), not a single nullable string — `emptyCellHighlightFilters()`/`countActiveCellHighlightFilters()`/`matchesCellHighlight()` in lib/cellHighlight.ts all treat it as an array (`.length`/`.includes()`). Cells/Index.vue still receives a single nullable `initialHighlight.state` from the dashboard deep-link URL (CellController still validates `state` as a single string) and wraps it into a one-item array when seeding `highlightFilters`.

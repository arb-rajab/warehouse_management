---
paths:
  - 'resources/js/lib/cellStateColor.ts,resources/js/components/CellHighlightFilters.vue,resources/js/pages/Admin/CellStatusLogs/Index.vue'
---

# Js Pages Admin Cell Status Logs

## CELL_STATES and cellStateLabel are the shared state-list/label helpers
lib/cellStateColor.ts now exports `CELL_STATES` (`['empty', 'full', 'opened']`, the canonical iteration order) and `cellStateLabel(state)` (`t(\`cellLog.states.${state}\`)`), alongside the existing CELL_STATE_COLOR/icon exports. They replace the component-local `cellStates` arrays / `stateLabel` functions CellHighlightFilters.vue and CellStatusLogs/Index.vue used to declare (CellStateLegend.vue and the help pages use them too). Use these instead of re-declaring a local state list or state-label lookup.

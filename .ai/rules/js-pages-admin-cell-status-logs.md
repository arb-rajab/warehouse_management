---
paths:
  - 'resources/js/lib/cellStateColor.ts,resources/js/components/CellMap3D.vue,resources/js/components/CellHighlightFilters.vue,resources/js/pages/Admin/CellStatusLogs/Index.vue'
---

# Js Pages Admin Cell Status Logs

## CELL_STATES and cellStateLabel are the shared state-list/label helpers
lib/cellStateColor.ts now exports `CELL_STATES` (`['empty', 'full', 'opened']`, the canonical iteration order) and `cellStateLabel(state)` (`t(\`cellLog.states.${state}\`)`), alongside the existing CELL_STATE_COLOR/icon exports. These were extracted once a third real caller (CellMap3D.vue's state-color legend) duplicated what CellHighlightFilters.vue and CellStatusLogs/Index.vue already had as component-local `cellStates` arrays / `stateLabel` functions. Use these instead of re-declaring a local state list or state-label lookup.

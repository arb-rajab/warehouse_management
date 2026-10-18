---
paths:
  - 'resources/js/components/CellSlot.vue,resources/js/lib/cellStateColor.ts'
---

# Components Js Components Js Lib

## cellStateColor.ts is the single source of truth for cell-state colors and icons
CELL_STATE_COLOR (lib/cellStateColor.ts) holds the Tailwind classes and the `icon` (CircleDashed/Inbox/PackageOpen) for empty/full/opened, read by CellSlot.vue, CellStateLegend.vue, the activity log and the verification pages through `CELL_STATE_COLOR[state]`. CellSlot.vue used to hardcode its own color and icon maps; they were extracted once a second real call site needed them. Add any new state color or icon here, not back into a component-local map. (The `hex` values it once carried existed only for the retired three.js cell map and were removed with it; the warehouse map's 3D colours come from its own CSS tokens via `Palette`.)

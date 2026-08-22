---
paths:
  - 'resources/js/components/CellSlot.vue,resources/js/components/CellMap3D.vue,resources/js/lib/cellStateColor.ts'
---

# Components Js Components Js Lib

## cellStateColor.ts is the single source of truth for cell-state colors
CELL_STATE_COLOR (lib/cellStateColor.ts) holds both the Tailwind classes (used by CellSlot.vue, the 2D map) and hex values (used by CellMap3D.vue's three.js materials) for empty/full/opened. CellSlot.vue used to hardcode its own color maps — they were extracted here once CellMap3D.vue became a second real call site, so the 2D and 3D views can't drift apart. Add any new state color here, not back into either component.

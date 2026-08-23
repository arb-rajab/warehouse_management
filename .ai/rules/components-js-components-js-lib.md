---
paths:
  - 'resources/js/components/CellSlot.vue,resources/js/components/CellMap3D.vue,resources/js/lib/cellStateColor.ts'
---

# Components Js Components Js Lib

## cellStateColor.ts is the single source of truth for cell-state colors
CELL_STATE_COLOR (lib/cellStateColor.ts) holds both the Tailwind classes (used by CellSlot.vue, the 2D map) and hex values (used by CellMap3D.vue's three.js materials) for empty/full/opened. CellSlot.vue used to hardcode its own color maps — they were extracted here once CellMap3D.vue became a second real call site, so the 2D and 3D views can't drift apart. Add any new state color here, not back into either component.

## Per-state icons now live in cellStateColor.ts, not CellSlot.vue's local map
CELL_STATE_COLOR (lib/cellStateColor.ts) now also carries an `icon` field (CircleDashed/Inbox/PackageOpen) alongside the Tailwind classes and hex color. CellSlot.vue no longer has a local `stateIcons` map — it reads `CELL_STATE_COLOR[state].icon`. This supersedes the old js-components.md note that said not to extract icons into a shared lib file because CellSlot was the only caller: CellMap3D.vue's state-color legend (`map-3d-state-legend`) is now a second real call site, so the extraction threshold is met. Add any new state icon here, not back into a component-local map.

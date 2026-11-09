---
paths:
  - 'resources/js/components/CellSlot.vue'
  - 'resources/js/components/CellStateLegend.vue'
  - 'resources/js/components/CellHighlightFilters.vue'
  - 'resources/js/lib/cellStateColor.ts'
  - 'resources/js/pages/Admin/CellStatusLogs/**'
  - 'resources/js/components/CellStatusLogRowCells.vue'
  - 'resources/css/app.css'
---

# Shared cell-state colors, icons and labels

## Canonical web state module

`cellStateColor.ts` owns `CELL_STATE_COLOR` (Tailwind light/dark classes and icons),
`CELL_STATES` (`empty`, `full`, `opened` in canonical iteration order), and
`cellStateLabel(state)` (the translated `cellLog.states.${state}` key). Reuse them in cell
slots, legends, highlight controls, activity logs, verification and help pages;
do not recreate local lists, labels, color maps or icon maps.

The web values are empty = gray-100/gray-400 border/CircleDashed,
full = green-50/green-500 border/Inbox, opened = orange-50/orange-500
border/PackageOpen, with dark variants alongside (neutral-900/neutral-700,
green-950/green-700, orange-950/orange-700), all Tailwind's stock values.

The back office remaps the stock gray/neutral scales to the Direction A
neutrals (views.md "Back office identity"), so the empty state is written as
pinned tokens: `bg-cell-empty dark:bg-cell-empty-dark` and
`border-cell-empty-line dark:border-cell-empty-line-dark`, defined in
`resources/css/app.css` with exactly the stock oklch values of gray-100,
gray-400, neutral-900 and neutral-700. Green and orange are never remapped.
`cellStateColor.test.ts` compares the pins with
`node_modules/tailwindcss/theme.css` and fails if any state uses a remapped
scale; keep both when changing either file. The rendered colours, and so the
Flutter mirror, are unchanged. The separate Flutter mobile
repository mirrors them in `_getBgColor`, `_getBorderColor`, `_getStateIcon`;
update those literals by hand when these values change. There is no shared
cross-repository implementation.

The retired three.js cell map's old `hex` fields were removed. The current
warehouse map gets 3D colors from CSS tokens via `Palette`; do not restore the
unused fields here.

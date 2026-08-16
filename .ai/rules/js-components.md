---
paths:
  - resources/js/components/CellSlot.vue
---

# Js Components

## Cell-state styling is mirrored by hand in the Flutter mobile app
CellSlot.vue's stateClasses/stateBorderClasses/stateIcons maps are the canonical source for cell-state visuals: empty=gray-100/gray-400 border/CircleDashed, full=green-50/green-500 border/Inbox, opened=orange-50/orange-500 border/PackageOpen (light shades; dark variants alongside). The Flutter mobile app (separate repo, not in this codebase) has its own `_getBgColor`/`_getBorderColor`/`_getStateIcon` widget methods that duplicate these same three values as Dart `Color`/`IconData` literals — there's no shared code between the two, so whenever these Tailwind classes change, the Dart literals must be updated by hand to match (green=full, orange=opened, gray=empty; icons: inbox/package-open/circle-dashed equivalents). Don't extract this into a separate TS lib file — CellSlot.vue is still the only caller in this repo (duplication threshold not met on the TS side).

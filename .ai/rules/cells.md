---
paths:
  - resources/js/pages/Admin/Cells/Index.vue
---

# Cells

## Cell map toolbars are visually grouped into bordered cards
The search+flat-tabs controls and the zoom/rotate controls are each wrapped in their own `rounded-lg border border-gray-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900` card (matching the existing dashboard custom-expiring-card style), rather than being four unrelated stacked flex rows with only margin between them. This is purely a visual grouping change — no test ids, roles, or titles moved — keep new toolbar additions inside one of these two cards (or a new card) rather than adding another bare top-level flex row.

## Search/jump/reset branch on viewMode instead of duplicating per view
`pulseAndCenterLabel`, `focusMatchAt`, and `resetViewAndRecenter` each branch on `viewMode` ('2d' | '3d') rather than having separate 2D/3D versions of the search/jump/reset flow. In 3D mode, `focusMatchAt` never does the flat-switching `router.get` reload the 2D view sometimes needs (since 3D already renders every flat via `cellHighlightSamples`) — it always calls the `CellMap3D` ref's `focusCell()`/`resetView()` locally instead. Free-text search still always does its existing `router.get` round trip in both modes (duplicating the server's location-parsing regex client-side just to skip one reload wasn't worth it) — only the *recentering* step branches by view mode, not the search submission itself.

---
paths:
  - resources/js/pages/Admin/Cells/Index.vue
---

# Cells

## Cell map toolbars are visually grouped into bordered cards
The search+flat-tabs controls and the zoom/rotate controls are each wrapped in their own `rounded-lg border border-gray-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900` card (matching the existing dashboard custom-expiring-card style), rather than being four unrelated stacked flex rows with only margin between them. This is purely a visual grouping change — no test ids, roles, or titles moved — keep new toolbar additions inside one of these two cards (or a new card) rather than adding another bare top-level flex row.

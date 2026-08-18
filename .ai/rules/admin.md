---
paths:
  - 'resources/js/pages/Admin/**'
---

# Admin

## Shared filter-dialog scaffolding lives in lib/filters.ts
`resources/js/lib/filters.ts` exports `filterSectionHeadingClass` (the filter-dialog section heading Tailwind classes), `filterTriggerButtonClass`/`filterClearButtonClass` (the dialog's trigger/clear buttons), `fieldLabelClass` (the filter-field label), `countBadgeClass` (the small rounded-pill count badge — blue, not the page's usual gray-900/white pairing, so it stays visible on a selected/dark background), `columnNumberOptions(maxColumnNumber)` (builds the 1..N column dropdown options), and `applySortToggle(filters, key, applyFilters)` (the toggle-sort-direction-then-reapply logic behind a sortable DataTable column). `Cells/Index.vue` and `CellStatusLogs/Index.vue` both import these instead of redefining them locally — import `filterSectionHeadingClass` aliased as `sectionHeadingClass` to keep the template unchanged. Reuse these in any future admin listing page with a filter dialog instead of re-copying them.

## filterSectionHeadingClass is the shared admin section-heading style, not just filter dialogs
`resources/js/lib/filters.ts`'s `filterSectionHeadingClass` export is reused for any admin section heading with this look, not only filter-dialog sections — `Dashboard/Index.vue` imports it (aliased as `sectionHeadingClass`) for its occupancy/expiring/activity section headings, same as `Cells/Index.vue` and `CellStatusLogs/Index.vue` do for their filter-dialog sections. Don't redeclare the class string locally in a new admin page; import and alias it instead.

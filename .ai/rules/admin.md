---
paths:
  - 'resources/js/pages/Admin/**'
---

# Admin

## Shared filter-dialog scaffolding lives in lib/filters.ts
`resources/js/lib/filters.ts` exports `filterSectionHeadingClass` (the filter-dialog section heading Tailwind classes), `filterTriggerButtonClass`/`filterClearButtonClass` (the dialog's trigger/clear buttons), `fieldLabelClass` (the filter-field label), `countBadgeClass` (the small rounded-pill count badge — blue, not the page's usual gray-900/white pairing, so it stays visible on a selected/dark background), and `columnNumberOptions(maxColumnNumber)` (builds the 1..N column dropdown options). `Cells/Index.vue` and `CellStatusLogs/Index.vue` both import these instead of redefining them locally — import `filterSectionHeadingClass` aliased as `sectionHeadingClass` to keep the template unchanged. `countBadgeClass` is used for both the highlight-filter trigger's active-filter count (CellHighlightFilters.vue) and the cell map's per-flat/total highlight-match counts (Cells/Index.vue — see controllers-admin.md). Reuse these in any future admin listing page with a filter dialog instead of re-copying them.

The toggle-sort-direction-then-reapply logic behind a sortable DataTable column is `lib/filters.ts`'s `toggleSort(filters, key)` export, shared by `CellStatusLogs/Index.vue` and `Products/Index.vue` (extracted once the second sortable admin listing appeared). A future sortable admin listing should import it too, rather than re-inlining a local copy.

## filterSectionHeadingClass is the shared admin section-heading style, not just filter dialogs
`resources/js/lib/filters.ts`'s `filterSectionHeadingClass` export is reused for any admin section heading with this look, not only filter-dialog sections — `Dashboard/Index.vue` imports it (aliased as `sectionHeadingClass`) for its occupancy/expiring/activity section headings, same as `Cells/Index.vue` and `CellStatusLogs/Index.vue` do for their filter-dialog sections. Don't redeclare the class string locally in a new admin page; import and alias it instead.

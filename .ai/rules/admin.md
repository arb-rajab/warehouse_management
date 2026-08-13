---
paths:
  - 'resources/js/pages/Admin/**'
---

# Admin

## Shared filter-dialog scaffolding lives in lib/filters.ts
`resources/js/lib/filters.ts` exports `filterSectionHeadingClass` (the filter-dialog section heading Tailwind classes), `columnNumberOptions(maxColumnNumber)` (builds the 1..N column dropdown options), and `applySortToggle(filters, key, applyFilters)` (the toggle-sort-direction-then-reapply logic behind a sortable DataTable column). `Cells/Index.vue` and `CellStatusLogs/Index.vue` both import these instead of redefining them locally — import `filterSectionHeadingClass` aliased as `sectionHeadingClass` to keep the template unchanged. Reuse these in any future admin listing page with a filter dialog + sortable table instead of re-copying them.

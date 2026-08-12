---
paths:
  - 'resources/js/pages/Admin/CellStatusLogs/Index.vue,resources/js/pages/Admin/CellStatusLogs/Index.test.ts'
---

# Pages Admin Cell Status Logs

## Cell log filters live behind a Filters dialog
All filter fields (product/row/column/user/action/date range/created_within_days/expiration range) are rendered inside `<FilterDialog>`, closed by default — they don't exist in the DOM until the "Filters" trigger button (labelled via `cellLog.filters.title`) is clicked. Tests must open the dialog first (see `openFilters()` helper in Index.test.ts) before querying `#filter-*` elements. Submitting the form (`applyFilters()`) closes the dialog; clicking Clear (`clearFilters()`) resets fields but leaves the dialog open so the user can keep adjusting. The trigger button shows a numeric badge (`activeFilterCount`) counting distinct active filter *concepts* (date range and created_within_days count as one, expiration_date_from/to count as one), not raw non-empty fields. The pallet-history banner and its own Clear button stay outside the dialog, unchanged.

---
paths:
  - resources/js/types/admin.ts
---

# Types

## Row/column filter-option shape lives in RowAndColumnFilterOptions
`RowFilterOption` (`{id, letter}`) and `RowAndColumnFilterOptions` (`{rows: RowFilterOption[], maxColumnNumber: number}`) are the base shape for any filterOptions type that includes the row/column dropdown data. `CellStatusLogFilterOptions` `extends RowAndColumnFilterOptions` rather than redeclaring `rows`/`maxColumnNumber` inline — do the same for any new filter-options interface with the same fields.

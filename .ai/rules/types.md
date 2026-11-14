---
paths:
  - resources/js/types/admin.ts
---

# Shared row-and-column filter option types

## Row/column filter-option shape lives in RowAndColumnFilterOptions
`RowFilterOption` (`{id, letter}`) and `RowAndColumnFilterOptions` (`{rows: RowFilterOption[], maxColumnNumber: number}`) are the base shape for any filterOptions type that includes the row/column dropdown data. `CellStatusLogFilterOptions` `extends RowAndColumnFilterOptions` rather than redeclaring `rows`/`maxColumnNumber` inline — do the same for any new filter-options interface with the same fields.

## Warehouse shapes
`WarehouseSummary` (`{id, name}`) is what row, cell and pallet payloads carry and what the warehouse filter's search returns; `Warehouse` adds `code`, `address` and `rows_count` for the warehouses pages; `WarehouseFilterOptions` (`{warehouses}`) is the hydration shape a page with the warehouse filter adds to its `filterOptions` (`CellStatusLogFilterOptions` takes it as optional, because the products page shares that type without it). `RowFilterOption.warehouse` is present only for a letter two warehouses share (`Row::filterOptions()`), `CellSlotLocation.warehouse` only in the admin listings; label a row with `rowOptionLabel()` and link to it with `rowLinkOptions()` (both `lib/location.ts`) instead of using `row.letter` or a bare route.

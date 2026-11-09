---
paths:
  - app/Models/Warehouse.php
  - app/Models/Row.php
  - app/Http/Requests/Concerns/FiltersByWarehouseIds.php
  - app/Http/Requests/Concerns/ResolvesSlotFromCoordinates.php
  - app/Http/Requests/Concerns/FiltersDashboard.php
  - app/Http/Controllers/Admin/WarehouseController.php
  - app/Http/Controllers/Api/V1/WarehouseController.php
  - app/Http/Controllers/Concerns/BuildsDashboardStats.php
  - app/Http/Controllers/Concerns/RedirectsAfterCellAction.php
  - app/Http/Controllers/Concerns/BuildsCellQrLabels.php
  - app/Http/Requests/Admin/StoreRowRequest.php
  - app/Http/Requests/Admin/UpdateRowRequest.php
  - database/factories/RowFactory.php
  - resources/js/components/FilterWarehouseSelect.vue
  - resources/js/components/WarehouseFormFields.vue
  - resources/js/pages/Admin/Warehouses/**
  - resources/js/lib/warehouseForm.ts
  - resources/js/lib/warehouseRules.json
  - resources/js/lib/location.ts
---

# Warehouses: rows belong to one, letters are unique per warehouse, one filter shape

Introduced on 2026-11-08 (draft PR #551). The WMS manages several warehouses.
Every WMS user sees every warehouse: there is no per-user scoping, and
managing warehouses uses the same `role:admin` gate as rows.

## Schema and defaults

- `warehouses` (`name` unique, optional unique `code`, optional `address`).
  `rows.warehouse_id` is NOT NULL with `restrictOnDelete`. Cells, pallets,
  cell logs and verification rounds have no warehouse column: they reach it
  through their row. Add a column only for a query that truly needs it. The
  exception in kind: a pallet received but not yet placed has no cell and
  reaches its warehouse through its receipt (see [receiving](receiving.md)).
- `2026_11_08_120001_add_warehouse_id_to_rows_table` created the "Default"
  warehouse (`Warehouse::DEFAULT_NAME`) and moved every existing row into it,
  so every database has it. `RowFactory` puts a row there unless the caller
  picks one (`Row::factory()->for($warehouse)`); seeders use
  `Warehouse::firstOrCreateDefault()`. A raw `Row::create()` must pass a
  `warehouse_id`.
- A warehouse that still has rows is not deleted: `WarehouseController::destroy()`
  checks `hasRows()` under a lock and redirects with a `warehouse` error
  (`messages.warehouse_cannot_delete_has_rows`); the foreign key is the last
  line behind it.

## A letter names one row per warehouse

The unique index is (`warehouse_id`, `letter`); `StoreRowRequest` requires
`warehouse_id` and checks the letter within it, `UpdateRowRequest` within the
row's own warehouse (an update does not move a row). Everything that finds a
row by letter must therefore say which warehouse, without breaking a caller
that only knows the letter:

- **Route binding.** `{row}` is still bound by letter (URLs, Wayfinder and the
  mobile `rows/{row}/cells` keep their shape). `Row::resolveRouteBinding()`
  reads an optional `?warehouse_id=` from the query string only, and refuses a
  letter that several warehouses use with a `warehouse_id` validation error
  (`messages.row_letter_in_several_warehouses`) rather than picking one.
- **Building URLs.** Redirects to a row use `route('admin.rows.show',
  $row->routeParameters())`, which adds `warehouse_id` only while the letter is
  shared, so single-warehouse URLs and their tests are unchanged. Never pass a
  bare `$row->letter` to a row route.
- **Coordinates.** `ResolvesSlotFromCoordinates` takes an optional
  `{prefix}warehouse_id` (`warehouse_id` on pallet store, `to_warehouse_id` on
  the mobile transfer) with the same refusal on `{prefix}warehouse_id`.
  Placing an unplaced pallet passes its own warehouse as the default that
  settles a shared letter (`resolveSlot(..., defaultWarehouseId:)`, see
  [receiving](receiving.md)).
- **QR labels.** The cell deep link carries `&warehouse={id}`; labels printed
  before it have row/cell/flat only.
- Lookups by id (`row_id[]` filters, rounds' row ids) need nothing.

## The warehouse filter mirrors the product filter

- Requests compose `FiltersByWarehouseIds` (`warehouseIdsFilterRules()`,
  `warehouseIds()` returning `list<int>|null`, null when absent). The
  parameter is `warehouse_ids[]`; omitting it means every warehouse, so every
  endpoint's earlier answer is unchanged.
- Queries narrow with the `forWarehouses(?array $ids)` scope on `Row`, `Cell`,
  `Pallet`, `CellStatusLog` and `CellVerificationRound` (a no-op for null),
  chained in the controller after the request's other filters, not read off
  the raw request inside a `filtered()` scope. `Product::inWarehouse($ids)`
  takes the same ids.
- Consumers: the web and mobile dashboards (`FiltersDashboard`), admin rows,
  warehouse map page and rows payload, pallets, cell logs and rounds; mobile
  rows, rows/full, rows/frozen, cell logs, rounds (list, and `POST` creation,
  where it narrows the rows an omitted `row_ids` would claim and refuses a
  named row outside it as `verification_round_rows_not_found`), `products/{product}/pallets`
  and `pallets/unplaced`. The mobile ETags are content hashes, so the
  filter and the `warehouse` keys need no cache-key work.
- Admin pages echo the ids in `filters.warehouse_ids` (the dashboard and map
  always send the key, `null` when unfiltered; listings send it only when
  present, like their other `only()` filters) and hydrate only the selected
  labels with `Warehouse::selectedOptions($ids)` in
  `filterOptions.warehouses` (`{id, name}`). The rest are fetched on demand
  from `GET admin/warehouses/search?q=` (paginated, `WarehouseSummaryResource`),
  as the product filter fetches products.
- The mobile dashboard echoes `filters.warehouse_ids` and, as for products,
  sends no options list; the app reads `GET api/v1/warehouses`.

## What the dashboard counts under the filter

Cells through their row, pallets through their cell, logs through the cell
they were written against (a transfer between warehouses counts once on each
side). An unplaced pallet counts through its receipt's warehouse in the product
counts and is left out of the expired/expiring/stale cards (see
[receiving](receiving.md)). With a product filter `occupancy.empty` is 0; the warehouse filter alone
keeps those warehouses' empty cells. `low_stock` compares a product's minimum
with the pallets the selected warehouses hold, so a product short in the
selected warehouse counts even when another holds enough; the products page it
links to has no warehouse filter yet. The cache key includes the warehouse ids.

## Payloads

`RowResource`, `RowSummaryResource` (when `warehouse` is loaded: the rows of a
mobile round and `rows/frozen`), `CellResource` (when `row.warehouse` is loaded, as
`Cell::WITH_ROW_AND_CONTENTS` does) and `PalletResource` (when
`cell.row.warehouse` is loaded, or the receipt's for an unplaced pallet) carry `warehouse: {id, name}`
(`WarehouseSummaryResource`); cells nested in a row payload leave it to the
row. Row listings load `Row::WITH_WAREHOUSE`; `rows/frozen` loads it for its
`RowSummaryResource`. `ProductPalletResource` carries the same top-level
`warehouse`. The mobile cell logs, round reports (`cell.warehouse` through
`toLocationWithWarehouseArray()`, from `CellStatusLog::WITH_WAREHOUSES` /
`CellVerificationReport::WITH_WAREHOUSE`) and rounds' `rows`
(`CellVerificationRound::WITH_ROWS`) name their warehouse too. Mobile
`rows/{row}/cells` and its cell route document `?warehouse_id=` with a
`QueryParameter`. A round's `rows_already_in_active_round` message labels a
shared letter with its warehouse ("A (Annex)").

## The WMS pages (Vue)

- **Warehouses pages.** `Admin/Warehouses/{Index,Create,Edit}.vue` with `WarehouseFormFields` (name required, code and address optional, native attributes equal to `ValidatesWarehouse`). Create and Edit also pass `ResourceFormPage` the browser validation schema (`lib/warehouseRules.json`, kept equal to the requests by `WarehouseClientValidationParityTest`; `unique` stays on the server). The list shows the row count, offers Delete only while it is 0 (otherwise a "Has rows" notice), and reads the backend's race refusal from `usePageError('warehouse')` into an `ActionErrorBanner`. The nav item sits after Rows; the help topic is `warehouses`.
- **The filter control.** `FilterWarehouseSelect` (see [filter dialogs](filter-dialogs.md)) is on the dashboard ([dashboard](dashboard.md)), the rows and pallets headers, a bar above the warehouse map, the cell log dialog (Location section and the Cell column popover) the rounds dialog and the receipts dialog ([receiving](receiving.md)). The map remounts on a changed selection (`preserveState: false`) and fetches `warehouse-map/rows` with the same `warehouse_ids[]`. Each page puts `warehouse_ids` in its filter state, its active-filter count and Clear, and the pallets "view on map" link keeps it.
- **Rows.** Create posts a required `warehouse_id` (`StoreSelectField` over the `warehouses` prop); Edit shows the row's warehouse read-only (an update never moves a row); Show lists it in a `DetailList`; the list has a Warehouse column. A pallet transfer from the row page posts the destination row's `warehouse_id`.
- **Linking to a row.** Every link, form action or export URL for a row goes through `rowLinkOptions(row.warehouse)` (`lib/location.ts`), which adds `warehouse_id`; the cell, pallet, report and round listings therefore carry the cell's `warehouse: {id, name}` (`Cell::toLocationWithWarehouseArray()`, loaded through `CellStatusLog::WITH_WAREHOUSES` / `CellVerificationReport::WITH_WAREHOUSE` / `Pallet::listedForAdmin()`; the mobile API loads them too, so its location payloads gained that key). A link built from a bare letter would be refused for a letter two warehouses use.
- **Row dropdowns.** `Row::filterOptions()` returns `{id, letter}` and adds `warehouse: {id, name}` only for a letter that more than one warehouse uses; `LocationFilterFields` labels such rows "A - Annex" (`rowOptionLabel()`), and the rounds' covered rows name their warehouse (`rows.warehouse` loaded by the admin round controller).

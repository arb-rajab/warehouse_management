---
paths:
  - app/Models/Receipt.php
  - app/Models/ReceiptFile.php
  - app/Models/ReceiptLine.php
  - app/Models/ReceiptLinePallet.php
  - app/Models/Pallet.php
  - app/Enums/ReceiptStatus.php
  - app/Enums/ReceiptLineStatus.php
  - database/factories/Receipt*Factory.php
  - database/factories/PalletFactory.php
  - 'database/migrations/*receipt*'
  - 'database/migrations/*_on_pallets_table.php'
  - app/Services/PalletActionService.php
  - app/Http/Controllers/Api/V1/PalletController.php
  - app/Http/Requests/Api/V1/PlacePalletRequest.php
  - app/Http/Requests/Api/V1/FilterUnplacedPalletsRequest.php
  - app/Http/Resources/PalletResource.php
  - app/Http/Resources/ProductPalletResource.php
---

# Truck receiving: receipts, unplaced pallets and their warehouse

Design in [docs/truck-receiving-spec.md](../../docs/truck-receiving-spec.md). A
WMS admin receives a truck against a bill, checks the pallets against it and
stores the bill; the pallets exist before they have a cell. The truck itself is
not recorded. Every WMS user sees every warehouse (see [warehouses](warehouses.md)).

## Schema (bare table names, listed in `SharedDatabaseTableNamesTest`)

- `receipts`: `warehouse_id` (NOT NULL, restrict), `bill_name`, `bill_info`,
  `status` (`ReceiptStatus`: draft, finalized), `created_by`, `finalized_by`,
  `finalized_at`.
- `receipt_files`: the bill's photos/PDFs. A relative `path` on a filesystem
  disk (like `products.warehouse_image_path`), `original_name`, `mime_type`,
  `size`; not the store's `uploads` table. "At least one file before
  finalizing" is a service/request rule, not a constraint.
- `receipt_lines`: `product_id`, `is_extra` (a group not on the bill),
  `expected_pallets_count`, `expected_boxes` (per pallet),
  `expected_expiration_date` (all three null on an extra line), `status`
  (`ReceiptLineStatus`: pending, matched, mismatched, extra), `note`.
- `receipt_line_pallets`: the pallets actually received for a line while the
  receipt is a draft (`boxes` >= 1, `expiration_date`); their product is the
  line's. `pallet_id` is set when finalizing creates the real pallet and is
  `nullOnDelete`, so the receipt survives a later pallet deletion.
- Deleting a receipt cascades to its files and lines; a finalized one cannot be
  deleted while its pallets exist (`pallets.receipt_line_id` restricts).

## Unplaced pallets and their warehouse

- `pallets.cell_id` is nullable (unique index and restricting FK kept; NULLs do
  not collide). `pallets.receipt_line_id` is a nullable FK (`restrictOnDelete`:
  `nullOnDelete` could leave a pallet with no cell and no receipt, i.e. in no
  warehouse) set for pallets created by finalizing a receipt, and kept after
  they are placed.
- There is **no `pallets.warehouse_id`**: a placed pallet reaches its warehouse
  through `cell.row.warehouse`, and a stored copy would go stale when a transfer
  moves it to another warehouse. `Pallet::warehouse` resolves the cell's row
  when `cell_id` is set, otherwise `receiptLine.receipt.warehouse`.
- `Pallet::forWarehouses()` matches a placed pallet by its row only and an
  unplaced one (`cell_id` null) by its receipt's warehouse, so a received pallet
  transferred elsewhere follows its cell. `Receipt::forWarehouses()` is the
  receipt counterpart.
- Code that reads `$pallet->cell` must treat it as nullable; check
  `cell_id === null` rather than touching the relation (strict mode refuses a
  lazy load in a list). `Pallet::placed()` / `unplaced()` scope by it.
- Factories: `Receipt::factory()` uses the Default warehouse unless
  `->for($warehouse)`; `Pallet::factory()->unplaced()` has no cell and a line of
  its own product.

## How each pallet consumer treats an unplaced pallet

- **Model.** `state` is null (no relation read); `etag()` works without a cell
  (state empty), and placing changes it through `cell_id`. `warehouse` is the
  receipt's; `warehouseLoaded()` says whether the relations it reads are loaded
  (`cell.row.warehouse` placed, `Pallet::WITH_RECEIPT_WAREHOUSE` unplaced).
  `toListingSummaryArray()`'s `state` is nullable. `cellEnteredLog` is null.
- **Resources.** `PalletResource` and `ProductPalletResource` add `placed`
  (bool); an unplaced pallet has null `state`, `location` (and `is_active` on
  the product list) and still names its `warehouse`. A placed pallet's shape is
  unchanged. `PalletResource` sends the ETag for an unplaced pallet too.
- **Mobile `products/{product}/pallets`.** Included (they are stock): left
  joins, the same expiry/added order, unplaced after placed on a tie.
- **Pallet actions** (open, remove-boxes, empty, transfer, and the admin
  correction `update()`): `PalletActionService` refuses an unplaced pallet with
  409 `pallet_not_placed` before locking anything. The admin pallet routes 404
  first (`cellFor()`): they are offered from a cell's row page only.
- **Admin matching pallets list and the dashboard's selected-product list**
  (`Pallet::listedForAdmin()`): excluded; every row links to its map location
  and the cell filters cannot apply. The dashboard's expired, expiring and
  stale cards exclude them too (`placed()`), so a card matches the list it
  opens. Low stock, inactive in the warehouse (`Product::inWarehouse()`),
  `activeOrInWarehouse()` and the products page's `pallets_count`/low-stock
  filter count them: the product is held. The products page's full/opened/
  expired/expiring columns already go through the cell and leave them out.
- **Out of scope by design:** cell logs (written against a cell; the first is
  the `stored` row `place()` writes), verification rounds and reports (they
  count cells), row/cell payloads and the map (reach pallets through cells).

## Placing and listing (mobile API)

- `POST api/v1/pallets/{pallet}/place` (`api.v1.pallets.place`,
  `PlacePalletRequest`: `ResolvesSlotFromCoordinates` with the optional
  `warehouse_id`, plus `note`) calls `PalletActionService::place()`: cell lock
  then pallet lock; 409 `pallet_already_placed` (a placed pallet moves by
  transfer), the `If-Match` check (412), 409 `pallet_warehouse_mismatch` when
  the cell's row is in another warehouse than the receipt (crossing warehouses
  is a transfer after placing), then `slot_inactive`, `cell_in_active_round`
  and `slot_not_empty` as for store. The cell goes Empty to Full (Opened on an
  allowed first level) and the log is a `stored` row with the pallet's boxes:
  no new `CellLogAction`, so `cell_entered_at` and the quick-flip flag read it
  like a store. Answers the `PalletResource` with its new ETag.
- `GET api/v1/pallets/unplaced` (`api.v1.pallets.unplaced`, registered before
  `pallets/{pallet}`; `FilterUnplacedPalletsRequest`: `warehouse_ids[]` through
  `Pallet::forWarehouses()`, `product_id[]`) lists `PalletResource`s, oldest
  received first (`created_at`, `id`), 20 per page.

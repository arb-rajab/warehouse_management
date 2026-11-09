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
- Code that reads `$pallet->cell` (ETag, logs, actions, resources, dashboard)
  must treat it as nullable; the migrations only made the column nullable.
- Factories: `Receipt::factory()` uses the Default warehouse unless
  `->for($warehouse)`; `Pallet::factory()->unplaced()` has no cell and a line of
  its own product.

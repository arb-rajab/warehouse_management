# Truck receiving and multi-warehouse mobile API (spec)

Agreed design for the draft PR. Work proceeds in prompts; this file is the shared source of truth.

## Goals
1. Audit existing mobile API (`/api/v1`) for multi-warehouse gaps and close them.
2. Let a WMS admin receive a truck of pallets, store the bill as images/PDFs, record the pallets as written on the bill, and store the pallets before they have a cell.

## Receipt
- A receipt belongs to one warehouse. Access: WMS `role:admin` only. The truck itself is not recorded.
- The bill is stored only as one or more uploaded photos/PDFs (private disk, authenticated download). No bill details are stored: no bill name or info, no expected lines, no matched/mismatched computation. The admin checks the physical pallets against the bill image by eye.
- The admin records the pallets as written on the bill (and physically verified): a flat list, one entry per pallet, each with product, boxes (>= 1) and optional expiry (past dates accepted: it records what arrived). No grouping into lines, no pallet counts per line, no extra-line concept. The order typed is kept.
- The receipt's only text is an optional free-text note by the admin.
- Pallet ids are never shown in lists of received pallets: the receipt shows only the pallets as written by the merchant.
- A draft's pallet list is replaced as a whole (`PUT receipts/{receipt}/pallets`, optionally with the note); it may also be sent when creating the receipt.
- Finalizing needs at least one file and at least one pallet entry; it creates one unplaced pallet per entry (cell empty, owned by the receipt's warehouse). A finalized receipt is not editable; corrections use normal pallet actions.

## Unplaced pallets
- `pallets.cell_id` becomes nullable. An unplaced pallet belongs to its receipt's warehouse.
- Placing is optional and can happen later via `POST pallets/{pallet}/place` (row/cell/flat, `warehouse_id`), logged like a store, cell must be in the pallet's warehouse. A list of unplaced pallets is filterable by `warehouse_ids[]`.
- Existing pallet/cell code (logs, ETag, actions, dashboard, product pallet lists) must be safe for unplaced pallets.

## Web
Read-only admin pages: receipts list (column picker, filters) and show (bill files, note, pallets as written on the bill), following `.ai/rules`.

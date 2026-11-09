# Truck receiving and multi-warehouse mobile API (spec)

Agreed design for the draft PR. Work proceeds in prompts; this file is the shared source of truth.

## Goals
1. Audit existing mobile API (`/api/v1`) for multi-warehouse gaps and close them.
2. Let a WMS admin receive a truck of pallets against a bill, check pallets against the bill, store the bill, and store the pallets before they have a cell.

## Receipt
- A receipt belongs to one warehouse. Access: WMS `role:admin` only.
- Bill header is typed: bill name/number and free-text info. The truck itself is not recorded.
- One or more uploaded photos/PDFs of the bill are stored with the receipt.
- Bill lines: product, expected pallet count (>= 1), boxes per pallet, optional expiry (the same properties a pallet has). A one-pallet line is the individual case; a larger count is the aggregate case.
- Checking a line: the admin confirms or edits the actual pallets of the line (each with its own boxes and expiry, defaulting to the bill). The line is matched when actual count, boxes and expiry equal the bill, otherwise mismatched; optional note. The admin may add extra pallets not on the bill.
- Finalizing with discrepancies is allowed. Finalizing creates one unplaced pallet per actual pallet. A finalized receipt is not editable; corrections use normal pallet actions.

## Unplaced pallets
- `pallets.cell_id` becomes nullable. An unplaced pallet belongs to its receipt's warehouse.
- Placing is optional and can happen later via `POST pallets/{pallet}/place` (row/cell/flat, `warehouse_id`), logged like a store, cell must be in the pallet's warehouse. A list of unplaced pallets is filterable by `warehouse_ids[]`.
- Existing pallet/cell code (logs, ETag, actions, dashboard, product pallet lists) must be safe for unplaced pallets.

## Web
Read-only admin pages: receipts list (column picker, filters) and show (bill files, per-line check results), following `.ai/rules`.

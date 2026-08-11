---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Inertia pagination helper and shared Cell query shapes
Inertia pages need the `data` + `meta` array shape, which plain serialization of a ResourceCollection drops. Use `$this->paginated($resourceCollection)` from the base Controller — do not re-type `->response()->getData(true)`.

For cell queries use the shared shapes rather than inline strings:
- `Cell::WITH_CONTENTS` — the pallet + product eager loads.
- `Cell::WITH_ROW_AND_CONTENTS` — the same plus `row:id,letter`, for cells not already queried through their row.
- `->orderedByCoordinates()` scope — replaces `->orderBy('cell_number')->orderBy('flat_number')`.

PalletController::lockCell($id) is the only place that should build `Cell::query()->lockForUpdate()->findOrFail(...)`; every state transition reads its cells through it inside the surrounding transaction.

## Cell status logging
Every action in `PalletController` that changes a cell's `state` (`store`, `open`, `empty`, `transfer`) must write a matching `CellStatusLog` row inside the same `DB::transaction` — this is the audit trail `Admin/CellStatusLogController` reads. `transfer` writes two rows (`TransferredOut` on the source cell, `TransferredIn` on the destination cell), linked via `related_cell_id`, since one row can't hold two `cell_id`s. Capture the pallet's `product_id` before any delete (`empty` removes the pallet) — `CellStatusLog.product_id` is nullable and `nullOnDelete` specifically so the log survives losing its pallet.

## Always select() only needed columns before paginate()/get()
Every `Model::query()->...->paginate()/get()` in a controller must have an explicit `->select([...])` (or scoped eager-load like `'relation:col,col'`) listing only the columns the Resource/Inertia page actually reads — never bare `Model::query()->paginate(...)`. This was missed in both `RowController::index` (Admin and Api/V1) until fixed; every other index action (`UserController`, `ProductController`, `CellController`, `CellStatusLogController`) already follows this. Keep the foreign key in a relation's column list even if the frontend doesn't render it.

## CellStatusLog.pallet_id has no FK constraint on purpose
`cell_status_logs.pallet_id` is a plain nullable `unsignedBigInteger`, not `constrained()`. `PalletController::empty()` hard-deletes the pallet, so any `onDelete` rule (including `nullOnDelete`) would null out `pallet_id` on every earlier log row for that pallet the instant it's emptied — wiping the exact history "view all logs for this pallet" needs most. Always pass `$pallet->id` into `logCellStatus()` (capture it into a local var before `$pallet->delete()` in `empty()`, same as `$productId`). `CellStatusLog::pallet()` is a plain `belongsTo` with no DB constraint backing it; it resolves to null once the pallet is gone, but `pallet_id` stays intact for filtering/grouping. Admin/CellStatusLogController filters on `pallet_id` without an `exists:pallets,id` rule for the same reason.

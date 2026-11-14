---
paths:
  - app/Services/PalletCorrectionService.php
  - app/Exceptions/BulkPalletChangeRejectedException.php
  - app/Http/Controllers/Api/V1/PalletController.php
  - app/Http/Requests/Api/V1/{FilterPalletsRequest,BulkPalletNoteRequest,ChangePalletProductRequest}.php
  - app/Http/Requests/Concerns/ValidatesPalletSelection.php
  - app/Http/Resources/{PalletResource,PalletListResource,ProductPalletResource,CellStatusLogResource}.php
  - app/Models/Pallet.php
  - app/Models/CellStatusLog.php
  - database/migrations/2026_11_12_10000*
  - tests/Feature/Api/V1/{PalletListTest,PalletCorrectionsTest}.php
---

# Pallet notes, the admin pallet list and bulk corrections

The workflow: at receiving the admin records pallets of a product not in the
system yet (or recorded wrongly) under a stand-in product (DIVERSEN, but any
product works) and writes the real name in the entry's note; accounting
creates the product; the admin finds the pallets in a list, selects some or
all, and changes their product (or gives several one shared note) in one go.

## The note

- `pallets.note` and `receipt_pallets.note`: nullable, at most 255, trimmed,
  empty is null. The pallet's own note, not the action notes the cell log
  keeps (`note` on place/open/... still goes to the log only).
- Finalizing copies each entry's note onto the pallet it creates. Place, open,
  remove-boxes, transfer and the admin `update()` never touch it; a product
  change clears it unless `keep_note: true`; the bulk note sets it.
- `PalletResource`, `ProductPalletResource` (and so `pallets/unplaced`,
  `products/{product}/pallets`) carry `note`. Every query feeding them selects
  `note` (strict mode refuses a missing attribute).
- `Pallet::etag()` appends the note **only when set**, so a pallet without one
  keeps the ETag it had before notes existed, and two note changes within the
  same second (same `updated_at`) still differ.

## `GET api/v1/pallets` (`api.v1.pallets.index`, `role:admin`)

`FilterPalletsRequest`: `product_id[]`, `warehouse_ids[]` (`Pallet::forWarehouses()`),
`placed`, `has_note` (`true`/`false`), `q` (LIKE with escaped wildcards on
`pallets.note`, `products.name`, `products.ar_name`; case-insensitive through
the `_ci` collation), `receipt_id`, `per_page` 1..200 (default 20). Sorted by
product name (correlated subquery, then `product_id`), note with nulls last,
`created_at`, `id`. Items are `PalletListResource`: the `PalletResource` fields
plus `receipt` (`{id, created_at}` or null) and `etag`, **quoted** as in the
`ETag` header so it can be sent as `If-Match` unchanged.

## The bulk corrections (`role:admin`)

`POST pallets/bulk/note` (`BulkPalletNoteRequest`, `note` present, null
clears) and `POST pallets/change-product` (`ChangePalletProductRequest`:
`product_id` must exist and be `published`, `reason` 3..255, `keep_note`).
Both take `pallets[]` (`ValidatesPalletSelection`): 1..500 `{id, etag?}`,
`distinct` ids (422), `etag` quoted or bare. One endpoint serves one pallet.

`PalletCorrectionService`:

- **All or nothing.** One transaction; the selected pallets are locked with
  `lockForUpdate()` in ascending id order and every check runs under those
  locks. Any failure throws `BulkPalletChangeRejectedException`: 409
  `bulk_rejected`, `message` with the count (`trans_choice`), and `failed[]`
  of `{id, error_code, message}` in the order sent. Codes, first match per
  pallet: `pallet_not_found` (gone, i.e. emptied), `pallet_changed` (etag),
  then for a product change `pallet_same_product` and `cell_in_active_round`
  (a placed pallet in a row an unfinished verification round claims). An
  inactive cell does not block a correction: it is a record fix, not a move.
- **Only pallets are locked**, never cells. Every other action locks a cell
  and then at most one pallet, so this cannot close a cycle; and a cell's
  state changes only under its pallet's lock, so the ETag read here is current.
- **Writes are two statements**: one `UPDATE` of the pallets (query builder,
  so `updated_at` moves and the ETag changes) and one multi-row `INSERT` of the
  cell log. Both skip model events: the service flushes `DashboardStatsCache`
  after commit for a product change (a note is not on the dashboard).
- A bulk note leaves a pallet that already has the note untouched (no update,
  no log entry, same ETag) and still answers it.
- Answers the selected pallets in the order sent as a **bare JSON array** of
  `PalletListResource` (resources are unwrapped app-wide; only paginated
  lists carry `data`/`meta`/`links`).

## The cell log entries

One entry per changed pallet, all sharing a UUID `batch_id`:
`note_changed` (`old_note`, `new_note`, `product_id` the pallet's product) or
`product_changed` (`product_id` the new product, `old_product_id`, `reason`,
`old_note`/`new_note`). `boxes_count` is the pallet's boxes, `note` null.

- A placed pallet's entry names its cell with `from_state` = `to_state` = the
  cell's state. An **unplaced pallet's entry has a null `cell_id`,
  `from_state` and `to_state`** (`2026_11_12_100002` made them nullable; only
  these corrections use it). Such an entry matches no warehouse/row/column
  filter (those are cell facts); `pallet_id` finds it.
- `CellStatusLogResource` adds `batch_id`, `reason`, `old_note`, `new_note`,
  `old_product` / `new_product` (`{id, name, ar_name}`, `new_product` only on
  `product_changed`); `cell`, `from_state`, `to_state` are nullable.
- The admin single-pallet correction (`PalletActionService::update()`) now
  records `old_product_id` too, with no `batch_id`.
- Batch entries are never flagged and do not count towards a later log's
  rapid-actions window (see [cell-log flags](cell-log-flags.md)).

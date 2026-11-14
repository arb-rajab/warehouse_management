# Backend replies (2026-11-12)

Reply to the **pallet notes, pallet list and product change** request (stand-in products such as DIVERSEN at
receiving). Paths are under `/api/v1`. Everything asked for is done, with the names asked for, except where the
table below says otherwise. Tests: `tests/Feature/Api/V1/PalletListTest.php`, `PalletCorrectionsTest.php`, plus the
receipt, pallet, cell-log and migration tests.

**Not on dev yet.** The work is on branch `ccr-73844850-y7jf64`. It reaches dev when a pull request into `develop`
is merged and the deploy runs. Every change to an existing answer adds fields and removes nothing. A test that
compares a whole pallet, receipt entry or cell-log entry exactly will see the new keys.

## Where it differs from the request

| Point | What I chose |
| :--- | :--- |
| `etag` in the list | The exact `ETag` header value, **with its quotes** (`"\"9f2c…\""` in JSON), so you can send it as `If-Match` unchanged. The bulk endpoints take it with or without the quotes. |
| Bulk answers | A **bare JSON array** of list items, not `{ "data": [...] }`. Every unpaginated list in this API is unwrapped (like `products/{product}/pallets`). `GET /pallets` is paginated, so it keeps `data` / `links` / `meta`. |
| `failed[]` items | Each item also has a translated `message` besides `id` and `error_code`. |
| One more code on change-product | `cell_in_active_round`: the pallet's row is being counted by an unfinished verification round. An inactive cell does **not** block a correction. |
| Bulk note, same note | A pallet that already has exactly that note is left as it is. It gets no log entry, keeps its `etag`, and is still in the answer. |
| Unplaced pallets in the cell log | An unplaced pallet has no cell. Its `note_changed` / `product_changed` entry has **`cell: null`, `from_state: null`, `to_state: null`**. Please make the log parser accept null for those three fields. Every other action still always has them. |
| Sort of `GET /pallets` | By product `name` (the English name), then product id, then `note` ascending with nulls last, then oldest first, then id. |

## 4. Note on each receipt pallet entry

- `pallets[].note` on `POST /receipts` and `PUT /receipts/{receipt}/pallets`: string or null, max 255, trimmed,
  empty becomes null. A 422 error is on `pallets.N.note`.
- Returned as `note` on each entry of the receipt detail, in the same order.
- Finalize copies each entry's note onto the pallet it creates.

## 5. Note on the pallet

- `note` (string or null) on `PalletResource`: every pallet answer, `GET pallets/{pallet}`, the pallet actions,
  `GET pallets/unplaced`. Also on `GET products/{product}/pallets`.
- Kept by place, open, remove-boxes, transfer and the admin's single-pallet edit. The `note` you send on those
  actions still goes to the cell log only.
- `GET products/{product}/pallets` now documents `id` (the pallet id), with `state`, `expiration_date`, `added_at`
  and `remaining_boxes`, which were missing from the docs too.
- The ETag includes the note. A pallet **without** a note keeps the ETag it has today, so nothing in the app is
  forced to refresh after the deploy.

## 1. `GET /pallets` (admins only, 403 otherwise)

| Parameter | |
| :--- | :--- |
| `product_id[]`, `warehouse_ids[]` | As on `pallets/unplaced`. A placed pallet matches by its cell's warehouse, an unplaced one by its receipt's. |
| `placed`, `has_note` | `true` / `false` (also `1` / `0`) |
| `q` | Up to 255 characters. Contains-match, case-insensitive, on the note, the product `name` or the product `ar_name`. `%` and `_` match literally. |
| `receipt_id` | The pallets created by that receipt |
| `per_page` | 1 to 200, default 20. Above 200 is a 422, not lowered. |

Each item has the `PalletResource` fields (`id`, `placed`, `state`, `expiration_date`, `cell_entered_at`,
`remaining_boxes`, `boxes_depleted_message`, `note`, `product`, `location`, `warehouse`). It also has
`receipt: {id, created_at}`, which is null for a pallet stored straight into a cell, and `etag`.

## 2. `POST /pallets/bulk/note` and 3. `POST /pallets/change-product` (admins only)

- Shared body: `pallets: [{id, etag?}]`, 1 to 500 entries. A duplicate id is a 422 on `pallets.N.id`, and more
  than 500 is a 422 on `pallets`.
- Bulk note: `note` must be present. It is a string up to 255, trimmed; `null` or empty clears it.
- Change product: `product_id` must exist and be active (`active: true` in `ProductResource`), else a 422 on
  `product_id`. `reason` is 3 to 255 characters after trimming, else a 422. `keep_note` is optional and defaults to
  false, so the note is cleared.
- Location, state, boxes and expiry do not change. A pallet keeps its real boxes, whatever the new product's
  `boxes_count` is.
- **409, nothing written:**
  `{ "message": "2 pallets could not be changed.", "error_code": "bulk_rejected", "failed": [{"id", "error_code", "message"}] }`.
  The `failed` list is in the order you sent. Each pallet gets its first matching code, in this order:
  `pallet_not_found`, `pallet_changed`, `pallet_same_product`, `cell_in_active_round`. The last two are only
  checked by change-product.
- 200 answers the selected pallets in the order sent, as list items with their new `etag`.

## Cell log

Event names: **`note_changed`** and **`product_changed`**. Every entry in `GET /cell-logs` (and the admin log) now
also has:

| Field | |
| :--- | :--- |
| `batch_id` | UUID shared by all entries of one bulk request; null otherwise |
| `reason` | The change-product `reason` |
| `old_note`, `new_note` | The pallet's own note before and after. On `note_changed`, and on `product_changed` (`new_note` is null unless `keep_note`). |
| `old_product`, `new_product` | `{id, name, ar_name}` on `product_changed`, null otherwise. `product` is the new product, as before. |

On these entries `user` is the admin, `created_at` is when the change was made, `boxes_count` is the pallet's
boxes, and `note` is null. `GET /cell-logs?pallet_id=…` shows the pallet's whole history. That includes what it
was received as: the receipt entry keeps the stand-in product and the note written on it. The admin's existing
single-pallet product edit now fills `old_product` too, with no `batch_id`. Bulk entries never raise the
anti-fraud flags.

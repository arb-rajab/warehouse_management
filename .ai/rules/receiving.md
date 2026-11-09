---
paths:
  - app/Models/Receipt.php
  - app/Models/ReceiptFile.php
  - app/Models/ReceiptPallet.php
  - app/Models/Pallet.php
  - app/Enums/ReceiptStatus.php
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
  - app/Services/ReceiptService.php
  - app/Services/PdfOptimizer.php
  - app/Exceptions/ReceiptFinalizedException.php
  - app/Exceptions/InvalidReceiptStateException.php
  - 'app/Http/Controllers/Api/V1/Receipt*Controller.php'
  - 'app/Http/Requests/Api/V1/*Receipt*Request.php'
  - app/Http/Requests/Concerns/ValidatesReceivedPallets.php
  - app/Http/Requests/Concerns/ResolvesSlotFromCoordinates.php
  - 'app/Http/Resources/Receipt*Resource.php'
  - 'app/Http/Resources/AdminReceipt*Resource.php'
  - app/Http/Controllers/Admin/ReceiptController.php
  - app/Http/Controllers/Admin/ReceiptFileController.php
  - app/Http/Requests/Admin/FilterReceiptsRequest.php
  - app/Http/Requests/Concerns/FiltersByReceiptStatus.php
  - resources/js/pages/Admin/Receipts/**
  - resources/js/pages/Admin/Help/Receipts.vue
  - resources/js/components/ReceiptStatusBadge.vue
  - resources/js/components/ReceiptFileTile.vue
---

# Truck receiving: receipts, unplaced pallets and their warehouse

Design in [docs/truck-receiving-spec.md](../../docs/truck-receiving-spec.md). A
WMS admin receives a truck into one warehouse, stores the bill as photos/PDFs
and records the pallets as written on the bill; the pallets exist before they
have a cell. The truck itself is not recorded. Every WMS user sees every
warehouse (see [warehouses](warehouses.md)).

## No bill details are stored

The bill is kept **only** as its files. There is no bill name, number or
text, no expected lines or counts and no matched/mismatched computation: the
admin compares the physical pallets with the bill's photos by eye and records
the pallets as written on it (and physically verified), a flat list with
**one entry per pallet**. There are no lines, no per-line pallet counts and no
"extra" pallets. The receipt's only free text is the admin's optional `note`.
Do not add bill fields, a comparison or a status per entry back without the
product owner.

## Schema (bare table names, listed in `SharedDatabaseTableNamesTest`)

- `receipts`: `warehouse_id` (NOT NULL, restrict), `note` (nullable text),
  `status` (`ReceiptStatus`: draft, finalized), `created_by`, `finalized_by`,
  `finalized_at`.
- `receipt_files`: the bill's photos/PDFs. A relative `path` on a filesystem
  disk (like `products.warehouse_image_path`), `original_name`, `mime_type`,
  `size`; not the store's `uploads` table. "At least one file before
  finalizing" is a service rule, not a constraint.
- `receipt_pallets` (`ReceiptPallet`): one row per pallet as written on the
  bill: `product_id` (signed `integer`, restrict, per
  [shared-database](shared-database.md)), `boxes` (>= 1, by the request),
  `expiration_date` (nullable), `position` (the order the admin typed, 0
  upwards) and `pallet_id`, set when finalizing creates the real pallet and
  `nullOnDelete`, so the receipt survives a later pallet deletion.
- Deleting a receipt cascades to its files and pallet entries; a finalized one
  cannot be deleted while its pallets exist (`pallets.receipt_id` restricts).
- The migrations (`2026_11_09_100000` to `100004`) were rewritten in place
  before merging; there are no stacked drop migrations for the earlier
  line-based design.

## Unplaced pallets and their warehouse

- `pallets.cell_id` is nullable (unique index and restricting FK kept; NULLs do
  not collide). `pallets.receipt_id` is a nullable FK (`restrictOnDelete`:
  `nullOnDelete` could leave a pallet with no cell and no receipt, i.e. in no
  warehouse) set for pallets created by finalizing a receipt, and kept after
  they are placed.
- There is **no `pallets.warehouse_id`**: a placed pallet reaches its warehouse
  through `cell.row.warehouse`, and a stored copy would go stale when a transfer
  moves it to another warehouse. `Pallet::warehouse` resolves the cell's row
  when `cell_id` is set, otherwise `receipt.warehouse`.
- `Pallet::forWarehouses()` matches a placed pallet by its row only and an
  unplaced one (`cell_id` null) by its receipt's warehouse, so a received pallet
  transferred elsewhere follows its cell. `Receipt::forWarehouses()` is the
  receipt counterpart.
- Code that reads `$pallet->cell` must treat it as nullable; check
  `cell_id === null` rather than touching the relation (strict mode refuses a
  lazy load in a list). `Pallet::placed()` / `unplaced()` scope by it.
- Factories: `Receipt::factory()` is a draft in the Default warehouse unless
  `->for($warehouse)`; `ReceiptPallet::factory()` is an entry of a draft with
  no pallet yet; `Pallet::factory()->unplaced()` has no cell and a finalized
  receipt of its own (pass `receipt_id` to pick one; `->for($receipt)` cannot,
  the state would overwrite it). Tests build pallets in a given warehouse with
  `placedPalletIn($warehouse)` / `unplacedPalletIn($warehouse)` from `tests/Pest.php`.

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

## Receiving API (mobile, `role:admin`)

Under `api/v1/receipts`, in the `auth:sanctum` group behind the same
`role:admin` middleware as the admin web routes (a non-admin gets 403), with
`scopeBindings()` so a `{file}` of another receipt is a 404. Thin controllers
(`ReceiptController`, `ReceiptFileController`) over `ReceiptService`; one
FormRequest per endpoint that takes input.

| Endpoint | Request | Answers | Errors |
| --- | --- | --- | --- |
| `GET receipts` (`api.v1.receipts.index`) | `FilterReceiptsRequest`: `warehouse_ids[]`, `status` | `ReceiptResource`s, newest first, 20 per page, with `pallets_count`/`files_count` | |
| `POST receipts` (`.store`) | `StoreReceiptRequest`: `warehouse_id`, `note?`, `pallets[]?` (each `product_id`, `boxes` >= 1, `expiration_date?`) | 201, the detail (a draft) | |
| `GET receipts/{receipt}` (`.show`) | | the detail: `note`, `files` (download `url`), `pallets` (`product_id`, `product`, `boxes`, `expiration_date`, in `position` order) | |
| `PUT receipts/{receipt}/pallets` (`.pallets.update`) | `UpdateReceiptPalletsRequest`: `pallets[]` (present, `[]` clears; same entry rules), `note?` (sent: set, null clears; omitted: kept) | the detail | 409 `receipt_finalized` |
| `POST receipts/{receipt}/files` (`.files.store`, multipart) | `StoreReceiptFileRequest`: `file` (`ReceiptFile::MIMES`, `MAX_KILOBYTES`) | 201, `ReceiptFileResource` | 409 `receipt_finalized`, 422 `receipt_file_limit_reached` |
| `GET receipts/{receipt}/files/{file}` (`.files.show`) | | the file inline (`private, no-store`) | 404 when the bytes are gone |
| `DELETE receipts/{receipt}/files/{file}` (`.files.destroy`) | | 204 | 409 `receipt_finalized` |
| `POST receipts/{receipt}/finalize` (`.finalize`) | | the detail | 409 `receipt_finalized`, 422 `receipt_has_no_files` / `receipt_has_no_pallets` |

Pallet entries are validated by `ValidatesReceivedPallets` (shared by both
requests that take them): the product by
`ValidatesPalletContents::palletProductRules()`, the rule pallet store uses,
`boxes` an integer >= 1, expiry dates normalized to `Y-m-d` and not required
to be in the future (the receipt records what arrived). The note is
`ValidatesOptionalNote`.

- **Pallet ids stay out of receipt payloads.** The detail lists the pallets
  as written by the merchant only: no entry id and no `pallet_id`, also once
  finalized. The created pallets are reached through `GET pallets/unplaced`
  (and, placed, through the normal pallet and cell endpoints).
- **Replacing the list.** `PUT .../pallets` deletes the draft's entries and
  writes the ones sent, `position` 0 upwards in the order sent. There is no
  per-entry endpoint.
- **Locking and immutability.** Every mutating call runs in one transaction
  that locks the receipt row first (`ReceiptService::lockDraft()`) and refuses
  a finalized receipt with 409 `receipt_finalized` (`ReceiptFinalizedException`).
  A finalized receipt never changes again; its pallets are corrected through
  the normal pallet actions. Precondition failures are
  `InvalidReceiptStateException` (422, `error_code` as above); every code has
  a `lang/{en,ar}/messages.php` key.
- **Finalize preconditions** (under the lock, in this order): a draft, at
  least one file, at least one pallet entry. Then one `Pallet` per entry in
  `position` order (its product, `remaining_boxes` = `boxes`, its expiry,
  `cell_id` null, `receipt_id` the receipt), its id stored on the entry's
  `pallet_id`, and the receipt stamped `finalized`, `finalized_by`,
  `finalized_at`.
- **Bill files are private.** Stored on `ReceiptFile::DISK` (`local`,
  `storage/app/private`, served by Laravel only with a valid signature), under
  `receipt-bills/` with a random name; never on `public`. `original_name` is
  the client's base name with the stored extension, and `mime_type`/`size`
  describe the bytes actually stored (`ReceiptService::STORED_MIME_TYPES`).
  `ReceiptFile::MAX_PER_RECEIPT` (10) is checked under the lock; the
  bytes are written before the lock and removed when the record is refused,
  and a deleted record's bytes are removed after its transaction commits.
  Payloads never carry `path`: `ReceiptFileResource.url` is the authenticated
  `files.show` route.
- **Bill files are stored compressed** (`ReceiptService::addFile()`):
  - *Photos* (JPEG, PNG, WebP) are re-encoded to JPEG by `ImageOptimizer` at
    quality 80, longest side at most 2400 px, EXIF orientation applied and
    metadata/appended bytes dropped. To meet `ImageOptimizer::MAX_OUTPUT_BYTES`
    (1 MiB) it shrinks further, but `minDimension` stops it at 1600 px (legible
    text wins over the budget). The re-encode is **always** stored, even in
    the rare case it is larger than the upload: an untrusted photo is never
    kept byte-for-byte (it would keep EXIF/GPS and appended bytes). Only PDFs
    fall back to the original.
  - *PDFs* go through `PdfOptimizer`: Ghostscript (`gs -dSAFER -dBATCH
    -dNOPAUSE -dQUIET -sDEVICE=pdfwrite -dCompatibilityLevel=1.5
    -dPDFSETTINGS=/ebook`) through the `Process` facade with an argument list
    (no shell), on its own temp files in the system temp dir, removed in a
    `finally`. Best effort: when it is turned off, the binary is missing,
    exits non-zero, times out, throws, or writes something that is not a
    smaller PDF (`%PDF-` header and `%%EOF`), the PDF is stored as sent and a
    warning is logged; compression never fails an upload.
  - Configured in `config/services.php` `ghostscript`: `binary`
    (`GHOSTSCRIPT_BINARY`, default `gs`), `compress_pdfs`
    (`GHOSTSCRIPT_COMPRESS_PDFS`, default true), `timeout` in seconds
    (`GHOSTSCRIPT_TIMEOUT`, default 30).
  - **Server requirement.** PDF compression needs the `ghostscript` system
    package (binary `gs`, 9.50 or later so `-dSAFER` is the hardened default;
    keep it patched, it parses untrusted PDFs) and `proc_open` enabled for PHP.
    Neither the deploy workflow (`.github/workflows/deploy.yml`,
    `.github/scripts/deploy-remote.sh`, which only installs Composer/npm
    dependencies) nor the Sail image (`compose.yaml`) installs it: install it
    on the host by hand or set `GHOSTSCRIPT_BINARY` to its path. Without it
    PDFs are simply stored as sent, with a warning in the log per upload.
  - Tests fake Ghostscript with `Process::fake()` (`PdfOptimizerTest`), and the
    one real-binary test skips where `gs` is not installed. `billPhoto()` in
    `tests/Pest.php` builds a photo that compresses like a real one (the solid
    black `UploadedFile::fake()->image()` does not show a size reduction).

## Admin web pages (read-only, `role:admin`)

`Admin\ReceiptController` (`admin.receipts.index`, `.show`) and
`Admin\ReceiptFileController` (`admin.receipts.files.show`) in the admin group
of `routes/web.php`; Vue pages `Admin/Receipts/{Index,Show}.vue`, Help topic
`receipts`, menu item "Receipts" (Truck icon) after Pallets. There are no
create, edit or delete screens: receipts are made and finalized from the mobile
API only.

- **List.** `Admin\FilterReceiptsRequest` (`warehouse_ids[]`, `status` through
  `FiltersByReceiptStatus` shared with the API request, `sort_direction`,
  `per_page`); `Receipt::ofStatus()` and `forWarehouses()` scopes (the API list
  uses the same ones), explicit `SELECT_COLUMNS`, `WITH_SUMMARY` and
  `withCount(pallets, files)`, newest first by default, sortable on creation
  time only. Filters are a `FilterDialog` with `FilterWarehouseSelect` and a
  status select. The table uses the shared column picker: `useTableColumns` with
  `listId: 'admin.receipts'` (stored per admin user under
  `store.tableColumns.{id}.admin.receipts`) and `StoreColumnPicker`, the one WMS
  list that has one (the spec asks for it). Required: receipt number, warehouse,
  status; hidden by default: created by, finalized at.
- **Show.** `Receipt::withDetails()` through `AdminReceiptResource`, rendered as
  a `DetailList` (empty values show "-"), the bill files (`ReceiptFileTile`:
  thumbnail for a photo with an image-failed state, link for a PDF) and the
  pallets as written in typed order: a "No." column (the position on the bill,
  computed in the page), the product (`ProductOptionLabel` and its thumbnail),
  boxes and expiry. **No pallet id, no entry id and no cell link anywhere**;
  a draft shows a hint that the pallets are created when it is finalized.
- **Bill download.** `ReceiptFile::inlineResponse()` is the one place a bill is
  read (404 when the bytes are gone, `private, no-store`, `nosniff`); the API
  and the admin controllers both return it. The admin route uses
  `scopeBindings()` so a file of another receipt is a 404, and
  `AdminReceiptResource` (its `files` presented by `AdminReceiptFileResource`,
  which overrides `ReceiptFileResource::downloadUrl()`) makes the payload's
  `url` this session-authenticated route instead of the bearer one. The storage path never leaves the model.

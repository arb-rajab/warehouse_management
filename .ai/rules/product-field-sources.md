---
paths:
  - 'config/product_field_sources.php'
  - 'app/Enums/ProductFieldSource.php'
  - 'app/Services/ProductFieldSourceService.php'
  - 'app/Support/ProductColumnOwnership.php'
  - 'app/Models/Product.php'
  - 'app/Models/ProductBuilder.php'
  - 'app/Console/Commands/SyncProductsCommand.php'
  - 'app/Console/Commands/AllProductsCommand.php'
  - 'app/Console/Commands/NewFetchProductsCommand.php'
  - 'app/Http/Controllers/Store/Admin/ProductFieldSourceController.php'
  - 'app/Http/Requests/Store/Admin/UpdateProductFieldSourcesRequest.php'
  - 'app/Http/Controllers/Store/Admin/ProductController.php'
  - 'app/Http/Requests/Store/Admin/UpdateProductRequest.php'
  - 'app/Services/Store/InHouseProductWriter.php'
  - 'resources/js/pages/Store/Admin/Setup/ProductFieldSources/**'
  - 'resources/js/pages/Store/Admin/Product/Products/Edit.vue'
  - 'resources/js/components/StoreProductFormFields.vue'
---

# Product field sources: Otajer synced or Admin panel set

Every `products` column the Otajer sync writes has exactly one owner at a
time, chosen globally per field (not per product) under Store admin
Setup > Product data sources (`product_field_sources.index`, permission
`product_field_sources`). Added 2026-11-01; it refines Option A in
[shared-database.md](shared-database.md#product-column-ownership-option-a-two-writers-with-separated-columns).

## One registry, one reader and writer

- `config/product_field_sources.php` is the registry: each syncable field
  with its `default` owner and its `insert_value`. Today (all defaulting to
  `otajer`, so the first sync overwrites them on feed-origin rows):

  | Field | Feed key | Insert value while admin-owned |
  | --- | --- | --- |
  | `name` | `enName` | `''` |
  | `ar_name` | `arName` | `''` |
  | `published` | `Active` | `0` |
  | `collection_id` | `Mat_Class2_ID` | `0` |
  | `serial` | `Serial` | `''` |
  | `unit_price` | `Price` | `0` |
  | `unit` | `UnitEnName` (cut to 20) | `''` |
  | `api_unitId` | `UnitID` | `''` |
  | `api_unit_name` | `UnitEnName` | `''` |
  | `tax` | `TaxPercent` | `0` |
  | `description` | `Desc` (`clean_html()`) | `''` |
  | `unit_equal` | `Unit_Equal` (whole number >= 1) | `1` |

  `collection_id` is the product's margin collection (the rep-order profit
  check): the sync maps the feed's `Mat_Class2_ID` to
  `product_collections.serial` (filled hourly by `import:collections`).
  `unit_equal` is inserted as `1`, not `0`, because the legacy imports divide
  the box price by it. The scalar fields after `collection_id` were added
  2026-11-02.
- **Deliberately not synced** (other tables, files or computation): `Mat_ID`
  (it is the id key; the `products.mat_id` column was dropped on 2026-11-02 and the
  `mat_id` payload keys are aliases of `id`, see shared-database.md), `Barcodes`, `Variations` (and the `variations`,
  `choice_options` columns), `Image`, `enCatName`/`arCatName` (category
  relation), `Mat_Class4_ID` (`brand_id` needs `import:brands`, which is not
  scheduled), `UnitArName` (a translation), and constants the legacy imports set
  (`tax_type`, `for_sale`, `from_api`).
- **Keep the current value when the feed has none.** `mapProducts()` maps a
  missing, blank or unusable value of a `KEEP_WHEN_MISSING` column (every field
  above except `name`, `ar_name`, `published`) to null, and the update
  expression is `COALESCE(incoming, products.<col>)` inside the conditional
  `CASE`. A NOT NULL column in that list (`NOT_NULL_KEEP`, today `unit_price`)
  cannot carry a NULL in the insert list (MySQL rejects it even for an existing
  row), so `syncChunk()` binds that product's current value, or the registry's
  insert value for a new product. A new keep-when-missing field joins
  `KEEP_WHEN_MISSING`, and `NOT_NULL_KEEP` if the column is NOT NULL.
- **Stock prices follow `unit_price`.** While `unit_price` is Otajer-owned the
  sync, in the same transaction as the upsert, updates existing
  `product_stocks` rows of the chunk's products: variants containing `PerBox`
  get `products.unit_price`; variants containing `PerPiece` get
  `ROUND(unit_price / unit_equal, 2)` (only when `unit_equal > 0`), mirroring
  the legacy import. Each statement requires `products.synced_at IS NOT NULL`
  in the database, so the conditional protection of store-origin rows holds for
  stocks too (an id conflict, or a row inserted since, is skipped). Custom
  variants, quantities and SKUs are never touched, and missing stock rows are
  not created. An admin-owned `unit_price` leaves every stock alone, and the
  legacy imports likewise skip the price of an existing stock row then.
- **A column the sync starts writing must be registered there in the same
  change**, and mapped in `SyncProductsCommand::mapProducts()` and its
  `MAPPED_COLUMNS` (only those literal names reach the upsert's raw update
  expressions), with a default that preserves current behaviour, its en/ar label
  under `store.productFieldSources.fields.*`, and tests. An unregistered
  column is outside this mechanism: no guard protects it and the page never
  offers it. Field names are at most 15 characters (the key is
  `product_source_<field>` in `business_settings.type`, VARCHAR(30)).
- `App\Services\ProductFieldSourceService` is the only reader and writer of
  the stored choice (`sourceFor()`, `isOtajerOwned()`, `isAdminOwned()`,
  `otajerOwnedFields()`, `adminOwnedFields()`, `readOnlyFieldsFor()`,
  `update()`). One row per field holds a single enum value
  (`App\Enums\ProductFieldSource`), so both owners can never apply at once;
  `update()` validates every key and value before writing, removes stray
  duplicate rows, and clears the `business_settings` cache. A missing or
  unreadable stored value means the default. No migration stores defaults.
- `ProductColumnOwnership::syncColumns()` is the registry;
  `protectedColumns()` is its Otajer-owned subset. Do not reintroduce a
  second hard-coded list of synced columns.

## What each owner means

- **Otajer synced:** `products:sync` writes the field on feed-origin rows
  (non-null `synced_at`). Store writers are refused by the Product/ProductBuilder
  guard there (seller, import and other legacy writers get a validation error;
  none skips these columns silently), and the admin edit form shows the field
  read-only with a note; `InHouseProductWriter::update()` ignores what is
  posted for it (the stored synced value stays, and the default-language
  translation mirrors the synced name, unit and description). Only `name`,
  `ar_name`, `unit`, `unit_price` and `description` have an edit-form input; the
  others (`serial`, `api_unitId`, `api_unit_name`, `tax`, `unit_equal`,
  `collection_id`, `published`) appear in the note and are protected by the
  guard alone. A read-only `unit_price` is also merged into the request before
  validation (`UpdateProductRequest`), so the discount rule checks the stored
  price. A read-only description renders as a locked block
  (`StoreSanitizedHtml`) with a hidden input posting the stored HTML; the
  controller sends the synced column, not a stale translation, for a read-only
  `unit` or `description`. `Product::setAttribute()` compares the numeric synced
  columns as numbers, so re-posting `12.5` over a stored `12.50` is no change.
- **Admin panel set:** the sync never writes the field: it is left out of the
  upsert's update list and inserted as its `insert_value` on a new row. The
  guard no longer protects it, so store writers may change it on any row. The
  manual legacy imports (`import:all-products`, `import:all-products-v2`)
  also skip admin-owned fields, and the matching translation values (name:
  English for `name`, `sa` for `ar_name`; description in both languages; the
  English unit), through `withoutAdminOwned()`, `withInsertValues()` and
  `translationValues()`.
- **Store-origin rows** (null `synced_at`) are untouched by the sync and stay
  fully store-owned whatever the choice; nothing on them is read-only.
- The name, unit and description locks apply to the default-language tab
  only: other languages edit a translation the sync never writes. The Arabic name is an edit-form
  field (`ar_name`, nullable, max 191) so an admin-owned value can be set.
  `ProductController::renderEdit()` prefills it from the column; while the
  column is blank and the field is editable it falls back to the product's
  Arabic translation (`ar` or `sa`) name, and saving the form writes that into
  the column. A read-only (Otajer-owned) field shows the synced column alone.
  Active status has no edit-form field; the list switch is refused by the
  guard while it is Otajer-owned on a feed-origin row, and the products list
  shows it locked beforehand: `ProductListResource` sends `published_locked`
  (feed-origin row and Otajer owns `published`, the owner read once per
  request) and the Active `StoreStatusSwitch` renders `locked` with
  `store.products.otajerLocked` and the `otajerSynced` hint
  ([status-switches.md](status-switches.md)).

- `products.can_expire` (boolean, NOT NULL, default false: a perishable
  product such as Labneh) is **not** a synced field: the sync never writes it,
  so it is not in the registry and nothing guards it. The product form posts it
  (`sometimes|boolean`) and `PhysicalProductWriter::postedColumns()` writes it
  when present; `ProductFormResource` sends it as `can_expire`.

## Tests

`ProductFieldSourceServiceTest`, `ProductColumnOwnershipTest`,
`Console/SyncProductsCommandTest`, `Store/Admin/ProductFieldSourceControllerTest`,
`Store/Admin/ProductEditFieldSourcesTest`, `Store/Admin/ProductControllerTest`
(`published_locked`), and the Vitest files of the settings page,
`Products/Edit.vue`, `Products/Index.vue` and `StoreProductFormFields.vue`. A new syncable field
extends each of them. Keep the settings form free of required-fields scanner
findings: the per-field rules carry the requirement, so the `sources` array
itself is not `required` (a required parent array with runtime-named controls
is a class A finding).

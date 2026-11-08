---
paths:
  - 'app/Models/ProductBarcode.php'
  - 'app/Rules/NewProductBarcode.php'
  - 'app/Services/ProductBarcodeLabels.php'
  - 'app/Services/Store/InHouseProductWriter.php'
  - 'app/Console/Commands/ProductBarcodeConflictsCommand.php'
  - 'app/Http/Requests/Admin/StoreProductBarcodeRequest.php'
  - 'app/Http/Requests/Concerns/ValidatesStoreProduct.php'
  - 'app/Http/Resources/ProductSummaryResource.php'
  - 'app/Http/Resources/Store/ProductFormResource.php'
  - 'app/Http/Controllers/Admin/ProductController.php'
  - 'app/Http/Controllers/Api/V1/ProductController.php'
  - 'app/Http/Controllers/Store/Admin/ProductController.php'
  - 'app/Http/Controllers/Store/Admin/DigitalProductController.php'
  - 'app/Http/Controllers/Store/Admin/BrandController.php'
  - 'app/Http/Controllers/Store/Admin/SellerController.php'
  - 'app/Http/Controllers/Seller/ProductController.php'
  - 'app/Http/Controllers/Seller/DigitalProductController.php'
  - 'app/Http/Controllers/Api/V1/Seller/ProductController.php'
  - 'resources/js/components/StoreProductListField.vue'
  - 'resources/js/components/StoreProductFormFields.vue'
  - 'resources/js/pages/Admin/Products/Index.vue'
---

# Product barcodes: the one table, own-id rows, deletion, labels, the seen list

Added 2026-11-08. `product_barcodes` (`App\Models\ProductBarcode`) is the only
barcode table, shared by the WMS and the store; the schema side (no unique index,
the retired `wms_product_barcodes`) is in
[shared-database.md](shared-database.md#barcodes-one-table) and the deploy steps
in [the runbook](../barcode-single-source-runbook-20261108.md). These behaviours
hold on every screen and path that touches it.

## Rows equal to the product's id are import-created and must never be deleted by a form

The Otajer import stores each product's `Mat_ID`, which is the product id, as a
barcode (`NewFetchProductsCommand`). The store app's `barcode_search` finds
products by id through that row, so deleting it breaks that search.
`ProductBarcode::isOwnProductId()` recognises such a row (value normalised as a
label shows it). Consequences:

- The WMS dialog does not list them (`ProductBarcode::listedInWms()`, through
  `ProductSummaryResource`), counts and ZIPs leave them out, the product's
  default barcode (its id, computed and never stored) stays the only own-id
  line, and the WMS destroy answers 404 for one.
- No form deletes one by posting a list: the Store form's merge-save deletes only
  values it showed and the user removed (see "The Store form always posts
  `product_barcodes_seen`" below), so a row the form did not show is kept.
- `NewProductBarcode` refuses a *new* value equal to an existing product's id, but
  lets a product that already holds a value post it again (the grandfathered
  own-id row and legacy duplicates).

## Uniqueness is checked by `App\Rules\NewProductBarcode`, under a lock

There is no unique index. Both forms (WMS `StoreProductBarcodeRequest`, store
`ValidatesStoreProduct`) validate a new value with `NewProductBarcode`; the
writers (`Admin\ProductController::storeBarcode` and
`InHouseProductWriter` create/update) repeat the check and insert inside
`NewProductBarcode::locked()`, the `product-barcodes` cache lock, so two admins
adding the same value at once cannot both pass. A new writer of
`product_barcodes` does the same; the importer is the one that deliberately
skips the product-id rule. Do not add a second uniqueness implementation or
rely on a database error.

## Every product delete path deletes its barcodes explicitly

The foreign key from `product_barcodes.product_id` restricts, so a product's
barcodes are deleted by the code that deletes the product, inside its
transaction where it has one: Store admin physical `destroy`/bulk
(`deleteProduct`), Store admin digital destroy, the brand and seller cascades
(`BrandController`, `SellerController`), the Blade seller physical and digital
destroys, and the seller API destroy. A new path that deletes products does the
same; `SellerProductBarcodeDeletionTest` and the controllers' tests pin each.

## Label generation goes through `ProductBarcodeLabels`

Preview, download, the ZIP, the Store form image, the storefront label and the
image-review list all build from `App\Services\ProductBarcodeLabels`:
`normalize()` (trimmed, Arabic-Indic digits converted), `isPrintable()` (Code 128
can draw it), `barcodeSvg()`/`etag()`/`dataUri()`, `merged()`. Never encode a
raw value or write a second printable test. A value that cannot be drawn
(`printable` false) answers 422 on preview/download/image; "Download all"
leaves it out and adds `not-printable.txt`; screens show it with the
`barcodeNotPrintable` note and no image or download.

## The Store form always posts `product_barcodes_seen`

The Store edit form posts, besides `product_barcodes[]`, the values it loaded as
`product_barcodes_seen[]` (`StoreProductListField`'s `seenName`).
`InHouseProductWriter::update` deletes only values that were seen and are no
longer posted, inserts new ones, and keeps every other row with its id, so a
save never removes a barcode added elsewhere (the WMS, the import, another tab)
after the form loaded. Only the key's *presence* matters: without it the server
falls back to replacing the list. A list that loaded empty therefore posts one
blank `product_barcodes_seen[]` entry (the browser drops an empty array from a
multipart post), which the server reads as "nothing seen". Keep the client rule
copy (`storeProductRules.json`, `physical.edit`) equal to
`ValidatesStoreProduct`; `ProductClientValidationParityTest` enforces it. The
create form does not post it.

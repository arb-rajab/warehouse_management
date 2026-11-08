# Runbook: product barcodes in one table (2026-11-08)

Dated deploy record, not a rule. The standing behaviour is in
[rules/product-barcodes.md](rules/product-barcodes.md) and
[rules/shared-database.md](rules/shared-database.md#barcodes-one-table); this
file says how to ship the change and what to check. Do not rewrite it later:
add a dated note below if the facts change.

## What ships

`product_barcodes` becomes the only barcode table. `App\Models\ProductBarcode`
reads it, the WMS products page, the store form, the import, the storefront and
the scan lookup share it, and `wms_product_barcodes` stays in place, unused, for
one release.

Two migrations, which run in this order and ship **with the code**:

1. `2026_11_08_100000_add_barcode_index_to_product_barcodes_table`: a plain,
   non-unique index on `product_barcodes.barcode`. Skipped when any index on
   exactly that column exists.
2. `2026_11_08_100001_copy_wms_product_barcodes_into_product_barcodes`: copies
   the WMS rows into `product_barcodes` (see "What the copy skips").

Deploying the code without the second migration leaves the WMS Barcodes dialog
empty (the code no longer reads the old table); running it without the code is
harmless but pointless. Deploy them together, in the normal deploy procedure
(README, "Deployment"). Build the frontend in the same release: the Store
image route keeps its URL but its parameter is now `barcode`, so the generated
Wayfinder actions and the page must come from the same commit. Open WMS admin
pages must be reloaded afterwards: barcode ids change (they are
`product_barcodes` ids now), and so do the ids in downloaded file names.

## Read-only checks in production BEFORE deploying

Run these in a read-only session (nothing here writes).

```sql
-- 1. Collation of the two barcode columns
SELECT TABLE_NAME, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'barcode'
  AND TABLE_NAME IN ('product_barcodes', 'wms_product_barcodes');

-- 2. The real shape of the inherited table
SHOW CREATE TABLE product_barcodes;

-- 3. Sizes
SELECT COUNT(*) FROM product_barcodes;
SELECT COUNT(*) FROM wms_product_barcodes;
```

How to read them:

- **Collation.** The copy never compares one column with the other, so two
  different collations cannot fail it ("Illegal mix of collations"); each WMS
  value is looked up in `product_barcodes` under that table's own collation. What
  the answer changes is the meaning of "same value": a `*_ci` collation makes
  `ABC` and `abc` (and values differing by trailing spaces) the same value, for
  the copy, the uniqueness rule and scans alike. Note the result in the deploy
  record.
- **`SHOW CREATE TABLE`.** Check the `product_id` foreign key to `products`
  exists (product deletion deletes barcodes explicitly either way), whether
  `created_at`/`updated_at` are nullable (the copy fills a missing value with the
  current time), and whether an index on `barcode` already exists (the first
  migration then does nothing).
- **Counts.** `product_barcodes` decides how long the index build takes (an
  online `ALTER` on a `varchar(255)` column; a million rows is still minutes at
  most, schedule accordingly). `wms_product_barcodes` is the number of rows the
  copy walks, in windows of 500. Keep both numbers: they are the comparison for
  the checks after the deploy.

## The conflicts report

`php artisan products:barcode-conflicts` changes nothing: every query is a
select. Options:

| Option | Effect |
| --- | --- |
| (none) | counts per section, then the rows of sections (a) to (d) as tables |
| `--all` | also lists the rows of the information sections (e) and (f), not only their counts |
| `--json` | the whole report as JSON, for a file or `jq` |
| `--csv` | one CSV with a `category` column on stdout; redirect it to a file |
| `--fail-on-conflicts` | exit code 1 while section (a) or (b) has a row; otherwise 0 |

Without `--fail-on-conflicts` it always exits 0: legacy duplicates may never go
away, so a plain run is not a pass/fail check.

**When to run it.** Run it twice and keep both outputs:

- **Before:** on a staging restore of the production database with the new code
  (preferred), or in the deploy window itself, after the new code is in place and
  with the site in maintenance mode, **before** `php artisan migrate`. It does
  not need the new migrations. Section (b) then predicts what the copy will skip.
- **After:** once both migrations have run.

```
php artisan products:barcode-conflicts --json > barcode-conflicts-before.json
php artisan products:barcode-conflicts --csv --all > barcode-conflicts-after.csv
php artisan products:barcode-conflicts --fail-on-conflicts   # exit 1 while (a) or (b) is not empty
```

### Reading the sections

| Section | Meaning | What to do |
| --- | --- | --- |
| (a) Values on more than one product | The same value is held by two or more products (legacy duplicates in the inherited store data, or a WMS value copied before the rule existed). A pallet-app scan of that value answers 409 `ambiguous_barcode` with the product ids. The Store form still lets each product save (it holds the value already); no new duplicate can be added. | Decide per value which product keeps it and remove it from the others in the Store form or the WMS dialog. Never automatic. |
| (b) WMS barcodes not copied because another product holds the value | A WMS row whose value another product already had in `product_barcodes`; copying it would let one scan match two products. The original is still in `wms_product_barcodes`. After the deploy this barcode is no longer on its WMS product. | Each row is an owner decision: free the value on the other product and add it again to the right product in the WMS, or accept the loss. Resolve or record all of them before the old table is dropped. |
| (c) WMS barcodes whose product is gone | Orphans, never copied. The old table's cascade should have left none. | Nothing; investigate only if the list is not empty. |
| (d) Values equal to another product's id | A scan resolves the id first, so this row can never be reached by a scan. | Information; remove the row if it is a mistake. |
| (e) Values that cannot be drawn as a Code 128 label | Store barcodes can be any text, for instance Arabic words. Their WMS preview/download answer 422, "Download all" leaves them out and lists them in `not-printable.txt`. | Information. They keep working in searches and scans of the exact stored text. |
| (f) Values a scan does not match as stored | Arabic-Indic digits, surrounding spaces, or a value only in the legacy `products.barcode` column, which scans never read. | Information. |

After the deploy, (b) and (c) should read the same as before (the copy changed
neither table's other rows). The row count of `product_barcodes` should be the
earlier count plus the copy's `copied` figure, which the migration logs
(`Copied WMS barcodes into product_barcodes`, with `copied`, `already_present`
and `product_missing`): `copied + already_present + product_missing` equals the
`wms_product_barcodes` row count.

### What the copy skips

A WMS row is not copied when its value already exists in `product_barcodes` (on
the same product, so there is nothing to add; on another product, section (b))
or when its product no longer exists (section (c)). Copied rows keep their
product and timestamps and get new ids.

## What is NOT changed automatically

- No row of either table is updated or deleted. Duplicates, own-id rows (the
  import's `Mat_ID` rows), unprintable values and orphans all stay.
- No unique index is added. Uniqueness is the application's, for new values
  only.
- The legacy `products.barcode` column is untouched and still not a scan source.
- `wms_product_barcodes` is neither emptied nor dropped.
- Nothing resolves a conflict: sections (a) and (b) are lists for the owner.

## Known limitation: the Otajer import is best-effort

`NewFetchProductsCommand` still checks `exists()` and then inserts, without the
`NewProductBarcode` lock, a transaction or trimming/Arabic-digit normalisation
(the form rule does all three). Two overlapping import runs, or an import racing
a form save, can therefore add the same value to two products. There is no
unique index to stop it, by design: the legacy data already holds shared values.
A residual duplicate shows up in `products:barcode-conflicts` section (a) and
makes the scan lookup answer 409 `ambiguous_barcode`. Run the report after large
imports, and do not schedule overlapping import runs.

Behaviours that do change with the code (no data change): a product's barcodes
are deleted with it on every delete path; the store app's search and
`barcode_search` now see barcodes added in the WMS, and the WMS scan lookup sees
the store's; the Store form no longer replaces the whole list on save.

## Rollback

- Neither `down()` undoes the copy. The copy's `down()` is a deliberate no-op: a
  copied row cannot be told apart from the same value added again later, and
  deleting by value could remove a barcode someone added on purpose. The
  index's `down()` drops only an index with its own name and never in production.
- `wms_product_barcodes` still holds every WMS row as of the copy, so nothing is
  lost. To go back to the previous release, redeploy the previous code: its WMS
  reads the old table again. Barcodes added in the WMS **after** the deploy exist
  only in `product_barcodes`; list them (rows created after the deploy time that
  are not in the old table) and re-add them by hand if the rollback stays.
  The copied WMS rows also stay in `product_barcodes`, so the previous release's
  Store form shows them.
- Fixing forward is preferred: nothing in this change is destructive.

## Follow-up task: drop `wms_product_barcodes` after one release

Open. After one release with no rollback, and once sections (b) and (c) are
resolved or recorded:

1. Export `wms_product_barcodes` (CSV) and keep it with the deploy record.
2. New migration dropping `wms_product_barcodes`, with a `down()` that recreates
   the empty schema.
3. Remove the report's (b) and (c) sections (the command already prints that they
   no longer apply once the table is missing) and their tests.
4. Replace `CreateWmsProductBarcodesTableTest` with a drop test, adapt every test
   that still names the table (`rg wms_product_barcodes tests`, the copy
   migration and report tests among them) and remove it from the e2e cleanup
   lists.
5. Update [rules/shared-database.md](rules/shared-database.md) (the product-id FK
   list and "Barcodes: one table") and this file's status.

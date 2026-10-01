-- ============================================================================
-- One-off dev cleanup: delete the junk product(s) named 'aaaaa' and every row
-- that depends on them. Hand-run MySQL; deliberately NOT a migration/seeder.
--
-- DEV DATABASE ONLY. Run `SELECT DATABASE();` first and confirm it is the dev
-- database. Never run this in production.
--
-- How to run (mysql client):
--   1. Back up first, e.g.
--        mysqldump <db> products pallets cells cell_status_logs \
--          cell_verification_reports uploads wms_product_settings \
--          wms_product_barcodes > backup_before_aaaaa_cleanup.sql
--   2. Run PART 1 (preview). Check the product list and the per-table counts.
--   3. Run PART 2. It opens a transaction and does NOT commit.
--   4. Check the PART 3 verification output: every count must be 0.
--   5. Run COMMIT; (or ROLLBACK; if anything looks wrong), then drop the four
--      tmp_doomed_* helper tables (last statement of the file).
--   6. Afterwards run `php artisan cache:clear` so the dashboard stats cache
--      (DashboardStatsCache) does not show the old occupancy numbers; raw SQL
--      bypasses the PalletObserver that normally flushes it. Files behind
--      deleted `uploads` rows stay on disk; SQL cannot remove them.
--
-- Match rule: name or ar_name equal to 'aaaaa'. Exact equality, so a real
-- product that merely contains those letters is never caught. MySQL's default
-- case-insensitive collation also matches 'AAAAA'. Change the predicate in
-- BOTH temp-table statements below if the target ever differs.
--
-- Safe to re-run: once the rows are gone every statement matches nothing.
-- Every table below is created by this app's migrations, but the store-origin
-- ones are guarded there; if a table is missing in your dev DB the statement
-- errors, the transaction is still open, so ROLLBACK, delete that line, retry.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- PART 1: preview (read-only)
-- ----------------------------------------------------------------------------

DROP TABLE IF EXISTS tmp_doomed_products;
DROP TABLE IF EXISTS tmp_doomed_products_lookup;
DROP TABLE IF EXISTS tmp_doomed_pallets;
DROP TABLE IF EXISTS tmp_doomed_uploads;

CREATE TABLE tmp_doomed_products AS
    SELECT id FROM products WHERE name = 'aaaaa' OR ar_name = 'aaaaa';

-- Real (not TEMPORARY) helper tables: MySQL cannot reference one temporary
-- table twice in a single query (error 1137), and several statements below
-- need to. They are dropped again at the very end. Created before
-- START TRANSACTION because DDL commits implicitly.
-- This identical copy serves the upload-orphan check, which already reads
-- tmp_doomed_products once.
CREATE TABLE tmp_doomed_products_lookup AS
    SELECT id FROM tmp_doomed_products;

CREATE TABLE tmp_doomed_pallets AS
    SELECT id, cell_id FROM pallets WHERE product_id IN (SELECT id FROM tmp_doomed_products);

-- Thumbnails used ONLY by the doomed products. `thumbnail_img` holds an
-- uploads row id as a string; anything non-numeric is ignored.
CREATE TABLE tmp_doomed_uploads AS
    SELECT DISTINCT CAST(p.thumbnail_img AS UNSIGNED) AS id
    FROM products p
    WHERE p.id IN (SELECT id FROM tmp_doomed_products)
      AND p.thumbnail_img REGEXP '^[0-9]+$'
      AND NOT EXISTS (
          SELECT 1 FROM products o
          WHERE o.thumbnail_img = p.thumbnail_img
            AND o.id NOT IN (SELECT id FROM tmp_doomed_products_lookup)
      );

SELECT id, name, ar_name, thumbnail_img, created_at
FROM products
WHERE id IN (SELECT id FROM tmp_doomed_products);

SELECT 'pallets' AS table_name, COUNT(*) AS rows_affected FROM tmp_doomed_pallets
UNION ALL SELECT 'cells reset to empty', COUNT(*) FROM cells WHERE id IN (SELECT cell_id FROM tmp_doomed_pallets)
UNION ALL SELECT 'cell_status_logs', COUNT(*) FROM cell_status_logs
    WHERE product_id IN (SELECT id FROM tmp_doomed_products) OR pallet_id IN (SELECT id FROM tmp_doomed_pallets)
UNION ALL SELECT 'cell_verification_reports', COUNT(*) FROM cell_verification_reports
    WHERE expected_product_id IN (SELECT id FROM tmp_doomed_products) OR reported_product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'wms_product_settings', COUNT(*) FROM wms_product_settings WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'wms_product_barcodes', COUNT(*) FROM wms_product_barcodes WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_barcodes', COUNT(*) FROM product_barcodes WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_variations', COUNT(*) FROM product_variations WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_queries', COUNT(*) FROM product_queries WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_stocks', COUNT(*) FROM product_stocks WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_taxes', COUNT(*) FROM product_taxes WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_translations', COUNT(*) FROM product_translations WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'wishlists', COUNT(*) FROM wishlists WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'carts', COUNT(*) FROM carts WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'carts_offered', COUNT(*) FROM carts_offered WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'offer_products', COUNT(*) FROM offer_products WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'flash_deal_products', COUNT(*) FROM flash_deal_products WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'uploads (orphaned thumbnails)', COUNT(*) FROM uploads WHERE id IN (SELECT id FROM tmp_doomed_uploads)
-- Historical commerce records: NOT deleted by default (see PART 2, step 5).
UNION ALL SELECT 'order_details (kept by default)', COUNT(*) FROM order_details WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'reviews (kept by default)', COUNT(*) FROM reviews WHERE product_id IN (SELECT id FROM tmp_doomed_products);


-- ----------------------------------------------------------------------------
-- PART 2: delete (child rows first, products last). Does not commit.
-- ----------------------------------------------------------------------------

START TRANSACTION;

-- 1. Product-attached store data with no FK or a non-cascading FK.
DELETE FROM product_variations     WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM product_barcodes       WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM product_queries        WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM product_stocks         WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM product_taxes          WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM product_translations   WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM wishlists              WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM carts                  WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM carts_offered          WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM offer_products         WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM flash_deal_products    WHERE product_id IN (SELECT id FROM tmp_doomed_products);

-- 2. WMS history about these products. The FKs are nullOnDelete, which would
--    leave unreadable rows with no product, so remove them instead. Logs are
--    also matched by pallet_id (no FK, kept after a pallet is emptied) so a
--    junk pallet's log trail goes with it.
DELETE FROM cell_status_logs
    WHERE product_id IN (SELECT id FROM tmp_doomed_products)
       OR pallet_id IN (SELECT id FROM tmp_doomed_pallets);
DELETE FROM cell_verification_reports
    WHERE expected_product_id IN (SELECT id FROM tmp_doomed_products)
       OR reported_product_id IN (SELECT id FROM tmp_doomed_products);

-- 3. Free the cells the junk pallets occupied. `cells.state` is a stored
--    column; PalletActionService sets it to 'empty' when a pallet is emptied.
UPDATE cells SET state = 'empty' WHERE id IN (SELECT cell_id FROM tmp_doomed_pallets);

-- 4. Pallets (RESTRICT FK to products), then the cascade tables, spelled out.
DELETE FROM pallets                WHERE id IN (SELECT id FROM tmp_doomed_pallets);
DELETE FROM wms_product_settings   WHERE product_id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM wms_product_barcodes   WHERE product_id IN (SELECT id FROM tmp_doomed_products);

-- 5. OPTIONAL, off by default: historical orders and reviews. Uncomment only
--    if the PART 1 preview showed rows you are sure are junk. Left in place
--    they keep a product_id that no longer resolves.
-- DELETE FROM order_details WHERE product_id IN (SELECT id FROM tmp_doomed_products);
-- DELETE FROM reviews       WHERE product_id IN (SELECT id FROM tmp_doomed_products);

-- 6. The products themselves, then their now-orphaned thumbnails.
DELETE FROM products WHERE id IN (SELECT id FROM tmp_doomed_products);
DELETE FROM uploads  WHERE id IN (SELECT id FROM tmp_doomed_uploads);


-- ----------------------------------------------------------------------------
-- PART 3: verify (every count must be 0), then COMMIT or ROLLBACK
-- ----------------------------------------------------------------------------

SELECT 'products still matching' AS check_name, COUNT(*) AS leftover FROM products WHERE name = 'aaaaa' OR ar_name = 'aaaaa'
UNION ALL SELECT 'pallets', COUNT(*) FROM pallets WHERE id IN (SELECT id FROM tmp_doomed_pallets)
UNION ALL SELECT 'cell_status_logs by product', COUNT(*) FROM cell_status_logs WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'cell_status_logs by pallet', COUNT(*) FROM cell_status_logs WHERE pallet_id IN (SELECT id FROM tmp_doomed_pallets)
UNION ALL SELECT 'cell_verification_reports', COUNT(*) FROM cell_verification_reports
    WHERE expected_product_id IN (SELECT id FROM tmp_doomed_products) OR reported_product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'wms_product_settings', COUNT(*) FROM wms_product_settings WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'wms_product_barcodes', COUNT(*) FROM wms_product_barcodes WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_barcodes', COUNT(*) FROM product_barcodes WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_variations', COUNT(*) FROM product_variations WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_queries', COUNT(*) FROM product_queries WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_stocks', COUNT(*) FROM product_stocks WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_taxes', COUNT(*) FROM product_taxes WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'product_translations', COUNT(*) FROM product_translations WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'wishlists', COUNT(*) FROM wishlists WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'carts', COUNT(*) FROM carts WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'carts_offered', COUNT(*) FROM carts_offered WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'offer_products', COUNT(*) FROM offer_products WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'flash_deal_products', COUNT(*) FROM flash_deal_products WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'uploads', COUNT(*) FROM uploads WHERE id IN (SELECT id FROM tmp_doomed_uploads)
UNION ALL SELECT 'cells still full/opened', COUNT(*) FROM cells
    WHERE id IN (SELECT cell_id FROM tmp_doomed_pallets) AND state <> 'empty'
-- Not deleted by default; non-zero here is expected unless step 5 was enabled.
UNION ALL SELECT 'order_details (kept by default)', COUNT(*) FROM order_details WHERE product_id IN (SELECT id FROM tmp_doomed_products)
UNION ALL SELECT 'reviews (kept by default)', COUNT(*) FROM reviews WHERE product_id IN (SELECT id FROM tmp_doomed_products);

-- Review the output above, then run exactly one of:
-- COMMIT;
-- ROLLBACK;

-- Finally (after COMMIT/ROLLBACK, since DROP TABLE commits implicitly):
-- DROP TABLE IF EXISTS tmp_doomed_products, tmp_doomed_products_lookup, tmp_doomed_pallets, tmp_doomed_uploads;

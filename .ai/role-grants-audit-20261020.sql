-- =============================================================================
-- Store back-office role-grants audit, 2026-10-20: READ-ONLY queries.
--
-- Every statement below is a plain SELECT (derived tables, no CTEs). No statement
-- writes rows, changes the schema, changes privileges, sets variables, uses a
-- locking hint or selects into anything. Nothing takes a lock beyond MySQL's
-- ordinary consistent (non-locking) read.
-- Run it against production with a read-only account if one exists.
--
-- Companion files: .ai/role-grants-audit-20261020.md (report) and
-- .ai/role-grants-audit-20261020.json (inventory). The literal lists below were
-- generated from that inventory at 6ec65e40 (origin/develop, PR #450).
--
-- Tables (config/permission.php `table_names`): wms_roles, wms_permissions
-- (with the store's `section` column), wms_role_has_permissions,
-- wms_model_has_roles, wms_model_has_permissions; role display names are in
-- role_translations; store accounts are rows of `users` (App\Models\StoreUser,
-- whose $table is 'users').
--
-- Guard: every query filters guard_name = 'store' explicitly. Rows of WMS's
-- 'web' guard (its 'admin' role) are never selected.
--
-- Store user model_type: the pivots' model_type for store users is the class
-- name App\Models\StoreUser. Checked: StoreUser uses spatie's HasRoles with no
-- morph map anywhere in app/, bootstrap/ or config/ (no Relation::morphMap or
-- enforceMorphMap), so spatie stores getMorphClass() = the FQCN; an in-memory
-- migrate + StoreUserSeeder run wrote exactly that value. WMS users are
-- App\Models\User. To stay independent of the NO_BACKSLASH_ESCAPES sql_mode,
-- the value is built as CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser').
--
-- Written without CTEs (derived tables only), so it also runs on MySQL 5.7.
-- Statements that can return many rows carry a LIMIT.
-- =============================================================================


-- Q1. All store-guard permissions with their section.
SELECT p.id, p.name, p.section, p.created_at
FROM wms_permissions p
WHERE p.guard_name = 'store'
ORDER BY p.section, p.name
LIMIT 1000;


-- Q2. Permissions in the code inventory that are MISSING from wms_permissions
--     (store guard). A missing row means every route gated only by it answers
--     403 to everyone but a Super Admin (Gate::before), and the role form cannot
--     offer it. origin: enforced = checked by code but not defined by any
--     migration; defined = created by a repo migration; defined+enforced = both.
SELECT inv.name, inv.origin
FROM (
        SELECT 'add_blog' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_blog_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_brand' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_coupon' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_driver' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_new_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_notification' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_offer_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_offers_catalog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_order' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_product_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_staff' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_staff_role' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_trip' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_truck' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_website_page' AS name, 'enforced' AS origin
        UNION ALL SELECT 'admin_dashboard' AS name, 'enforced' AS origin
        UNION ALL SELECT 'analytics_tools_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'approve_representative_payment' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'assign_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'ban_customer' AS name, 'enforced' AS origin
        UNION ALL SELECT 'ban_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'commission_history_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'currency_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_all_uploads' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_blog' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_blog_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_brand' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_classified_package' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_classified_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_color' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_coupon' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_customer' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_driver' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_notification' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_offer_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_offers_catalog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_order' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_attribute' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_attribute_value' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_conversations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_representative_visit' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_staff' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_staff_role' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_subscriber' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_trip' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_truck' AS name, 'defined' AS origin
        UNION ALL SELECT 'delete_uploads' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_website_page' AS name, 'enforced' AS origin
        UNION ALL SELECT 'download_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_blog' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_blog_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_brand' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_classified_package' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_color' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_coupon' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_customer' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_driver' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_offer_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_order' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_order_price' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_product_attribute' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_product_attribute_value' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_product_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_staff' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_staff_role' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_trip' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_truck' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_website_page' AS name, 'enforced' AS origin
        UNION ALL SELECT 'export_representative_payment' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'facebook_chat' AS name, 'enforced' AS origin
        UNION ALL SELECT 'facebook_comment' AS name, 'enforced' AS origin
        UNION ALL SELECT 'features_activation' AS name, 'enforced' AS origin
        UNION ALL SELECT 'file_system_&_cache_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'footer_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'general_settings' AS name, 'enforced' AS origin
        UNION ALL SELECT 'google_firebase_setting' AS name, 'enforced' AS origin
        UNION ALL SELECT 'google_map_setting' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'google_recaptcha_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'header_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'in_house_product_sale_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'language_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'login_as_customer' AS name, 'enforced' AS origin
        UNION ALL SELECT 'login_as_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_carriers' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_shipping_cities' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_shipping_states' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_zones' AS name, 'enforced' AS origin
        UNION ALL SELECT 'order_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'pay_to_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'payment_methods_configurations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'pickup_point_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_bulk_export' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_bulk_import' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_delete' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_duplicate' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_edit' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_wishlist_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'products_stock_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_blog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'publish_classified_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_product_review' AS name, 'enforced' AS origin
        UNION ALL SELECT 'reply_to_product_conversations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'reply_to_product_queries' AS name, 'enforced' AS origin
        UNION ALL SELECT 'reply_to_support_tickets' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'seller_commission_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'seller_payment_history' AS name, 'enforced' AS origin
        UNION ALL SELECT 'seller_products_sale_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'seller_verification_form_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'send_newsletter' AS name, 'enforced' AS origin
        UNION ALL SELECT 'shipping_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'shipping_country_setting' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_all_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_digital_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_in_house_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_seller_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'smtp_settings' AS name, 'enforced' AS origin
        UNION ALL SELECT 'social_media_logins' AS name, 'enforced' AS origin
        UNION ALL SELECT 'update_order_delivery_status' AS name, 'enforced' AS origin
        UNION ALL SELECT 'update_order_notes' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'update_order_payment_status' AS name, 'enforced' AS origin
        UNION ALL SELECT 'update_order_tracking' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'upload_files' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'user_search_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'vat_&_tax_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_brands' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_coupons' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_customers' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_drivers' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_all_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_flash_deals' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_offer_deals' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_product_conversations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_product_queries' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_staffs' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_subscribers' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_support_tickets' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_trips' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_all_trucks' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_all_website_pages' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_blog_categories' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_blogs' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_classified_packages' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_classified_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_colors' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_inhouse_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_offers_catalog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_order_details' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_pickup_point_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_attribute_values' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_attributes' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_categories' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_reviews' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_representative_packages' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_representative_payments' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_representative_visits' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_seller_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_seller_payout_requests' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_seller_profile' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_staff_roles' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_uploads' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'wallet_transaction_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'website_appearance' AS name, 'enforced' AS origin
) AS inv
LEFT JOIN wms_permissions p ON p.name = inv.name AND p.guard_name = 'store'
WHERE p.id IS NULL
ORDER BY inv.origin, inv.name;


-- Q3. Store-guard permissions present in wms_permissions but ABSENT from the
--     inventory (no code enforces or defines them: removed addons, retired
--     screens, typos). With the number of roles and users still holding each.
SELECT p.id, p.name, p.section,
       (SELECT COUNT(*) FROM wms_role_has_permissions rp WHERE rp.permission_id = p.id) AS roles_holding,
       (SELECT COUNT(*) FROM wms_model_has_permissions mp
         WHERE mp.permission_id = p.id AND mp.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')) AS users_holding_directly
FROM wms_permissions p
LEFT JOIN (
        SELECT 'add_blog' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_blog_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_brand' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_coupon' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_driver' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_new_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_notification' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_offer_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_offers_catalog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_order' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_product_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_staff' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_staff_role' AS name, 'enforced' AS origin
        UNION ALL SELECT 'add_trip' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_truck' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'add_website_page' AS name, 'enforced' AS origin
        UNION ALL SELECT 'admin_dashboard' AS name, 'enforced' AS origin
        UNION ALL SELECT 'analytics_tools_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'approve_representative_payment' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'assign_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'ban_customer' AS name, 'enforced' AS origin
        UNION ALL SELECT 'ban_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'commission_history_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'currency_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_all_uploads' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_blog' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_blog_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_brand' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_classified_package' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_classified_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_color' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_coupon' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_customer' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_driver' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_notification' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_offer_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_offers_catalog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_order' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_attribute' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_attribute_value' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_product_conversations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_representative_visit' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_staff' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_staff_role' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_subscriber' AS name, 'enforced' AS origin
        UNION ALL SELECT 'delete_trip' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_truck' AS name, 'defined' AS origin
        UNION ALL SELECT 'delete_uploads' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'delete_website_page' AS name, 'enforced' AS origin
        UNION ALL SELECT 'download_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_blog' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_blog_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_brand' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_classified_package' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_color' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_coupon' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_customer' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_digital_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_driver' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_offer_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_order' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_order_price' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_product_attribute' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_product_attribute_value' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_product_category' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_representative_package' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_staff' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_staff_role' AS name, 'enforced' AS origin
        UNION ALL SELECT 'edit_trip' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_truck' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'edit_website_page' AS name, 'enforced' AS origin
        UNION ALL SELECT 'export_representative_payment' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'facebook_chat' AS name, 'enforced' AS origin
        UNION ALL SELECT 'facebook_comment' AS name, 'enforced' AS origin
        UNION ALL SELECT 'features_activation' AS name, 'enforced' AS origin
        UNION ALL SELECT 'file_system_&_cache_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'footer_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'general_settings' AS name, 'enforced' AS origin
        UNION ALL SELECT 'google_firebase_setting' AS name, 'enforced' AS origin
        UNION ALL SELECT 'google_map_setting' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'google_recaptcha_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'header_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'in_house_product_sale_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'language_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'login_as_customer' AS name, 'enforced' AS origin
        UNION ALL SELECT 'login_as_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_carriers' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_shipping_cities' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_shipping_states' AS name, 'enforced' AS origin
        UNION ALL SELECT 'manage_zones' AS name, 'enforced' AS origin
        UNION ALL SELECT 'order_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'pay_to_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'payment_methods_configurations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'pickup_point_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_bulk_export' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_bulk_import' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_delete' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_duplicate' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_edit' AS name, 'enforced' AS origin
        UNION ALL SELECT 'product_wishlist_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'products_stock_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_blog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'publish_classified_product' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_flash_deal' AS name, 'enforced' AS origin
        UNION ALL SELECT 'publish_product_review' AS name, 'enforced' AS origin
        UNION ALL SELECT 'reply_to_product_conversations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'reply_to_product_queries' AS name, 'enforced' AS origin
        UNION ALL SELECT 'reply_to_support_tickets' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'seller_commission_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'seller_payment_history' AS name, 'enforced' AS origin
        UNION ALL SELECT 'seller_products_sale_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'seller_verification_form_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'send_newsletter' AS name, 'enforced' AS origin
        UNION ALL SELECT 'shipping_configuration' AS name, 'enforced' AS origin
        UNION ALL SELECT 'shipping_country_setting' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_all_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_digital_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_in_house_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'show_seller_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'smtp_settings' AS name, 'enforced' AS origin
        UNION ALL SELECT 'social_media_logins' AS name, 'enforced' AS origin
        UNION ALL SELECT 'update_order_delivery_status' AS name, 'enforced' AS origin
        UNION ALL SELECT 'update_order_notes' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'update_order_payment_status' AS name, 'enforced' AS origin
        UNION ALL SELECT 'update_order_tracking' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'upload_files' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'user_search_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'vat_&_tax_setup' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_brands' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_coupons' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_customers' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_drivers' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_all_dynamic_popups' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_flash_deals' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_offer_deals' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_product_conversations' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_product_queries' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_seller' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_staffs' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_subscribers' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_support_tickets' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_all_trips' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_all_trucks' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_all_website_pages' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_blog_categories' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_blogs' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_classified_packages' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_classified_products' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_colors' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_inhouse_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_offers_catalog' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_order_details' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_pickup_point_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_attribute_values' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_attributes' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_categories' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_product_reviews' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_representative_packages' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_representative_payments' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_representative_visits' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'view_seller_orders' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_seller_payout_requests' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_seller_profile' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_staff_roles' AS name, 'enforced' AS origin
        UNION ALL SELECT 'view_uploads' AS name, 'defined+enforced' AS origin
        UNION ALL SELECT 'wallet_transaction_report' AS name, 'enforced' AS origin
        UNION ALL SELECT 'website_appearance' AS name, 'enforced' AS origin
) AS inv ON inv.name = p.name
WHERE p.guard_name = 'store' AND inv.name IS NULL
ORDER BY p.section, p.name
LIMIT 1000;


-- Q4. Each store role: member count (store users only) and permission count,
--     with its translated display names.
SELECT r.id, r.name,
       (SELECT GROUP_CONCAT(CONCAT(rt.lang, '=', rt.name) ORDER BY rt.lang SEPARATOR ' | ')
          FROM role_translations rt WHERE rt.role_id = r.id) AS display_names,
       (SELECT COUNT(*) FROM wms_model_has_roles mr
         WHERE mr.role_id = r.id AND mr.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')) AS store_members,
       (SELECT COUNT(*) FROM wms_role_has_permissions rp
          JOIN wms_permissions p ON p.id = rp.permission_id AND p.guard_name = 'store'
         WHERE rp.role_id = r.id) AS permissions_held
FROM wms_roles r
WHERE r.guard_name = 'store'
ORDER BY r.name
LIMIT 500;


-- Q5. For each NEW or CHANGED permission (report §E), the roles that hold it.
--     A permission with no role (and no direct holder) means only a Super
--     Admin passes its gate.
SELECT chg.name AS permission,
       p.id AS permission_id,
       CASE WHEN p.id IS NULL THEN 'ROW MISSING' ELSE 'present' END AS row_state,
       (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ')
          FROM wms_role_has_permissions rp
          JOIN wms_roles r ON r.id = rp.role_id AND r.guard_name = 'store'
         WHERE rp.permission_id = p.id) AS roles_holding,
       (SELECT COUNT(*) FROM wms_model_has_permissions mp
         WHERE mp.permission_id = p.id AND mp.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')) AS users_holding_directly
FROM (
        SELECT 'add_blog' AS name
        UNION ALL SELECT 'add_blog_category' AS name
        UNION ALL SELECT 'add_brand' AS name
        UNION ALL SELECT 'add_classified_package' AS name
        UNION ALL SELECT 'add_coupon' AS name
        UNION ALL SELECT 'add_digital_product' AS name
        UNION ALL SELECT 'add_driver' AS name
        UNION ALL SELECT 'add_dynamic_popups' AS name
        UNION ALL SELECT 'add_flash_deal' AS name
        UNION ALL SELECT 'add_new_product' AS name
        UNION ALL SELECT 'add_notification' AS name
        UNION ALL SELECT 'add_offer_deal' AS name
        UNION ALL SELECT 'add_offers_catalog' AS name
        UNION ALL SELECT 'add_order' AS name
        UNION ALL SELECT 'add_product_attribute' AS name
        UNION ALL SELECT 'add_product_attribute_values' AS name
        UNION ALL SELECT 'add_product_category' AS name
        UNION ALL SELECT 'add_representative_package' AS name
        UNION ALL SELECT 'add_staff' AS name
        UNION ALL SELECT 'add_staff_role' AS name
        UNION ALL SELECT 'add_trip' AS name
        UNION ALL SELECT 'add_truck' AS name
        UNION ALL SELECT 'add_website_page' AS name
        UNION ALL SELECT 'admin_dashboard' AS name
        UNION ALL SELECT 'analytics_tools_configuration' AS name
        UNION ALL SELECT 'approve_representative_payment' AS name
        UNION ALL SELECT 'approve_seller' AS name
        UNION ALL SELECT 'assign_representative_package' AS name
        UNION ALL SELECT 'currency_setup' AS name
        UNION ALL SELECT 'delete_all_uploads' AS name
        UNION ALL SELECT 'delete_customer' AS name
        UNION ALL SELECT 'delete_driver' AS name
        UNION ALL SELECT 'delete_dynamic_popups' AS name
        UNION ALL SELECT 'delete_notification' AS name
        UNION ALL SELECT 'delete_offer_deal' AS name
        UNION ALL SELECT 'delete_offers_catalog' AS name
        UNION ALL SELECT 'delete_representative_package' AS name
        UNION ALL SELECT 'delete_representative_visit' AS name
        UNION ALL SELECT 'delete_seller' AS name
        UNION ALL SELECT 'delete_trip' AS name
        UNION ALL SELECT 'delete_truck' AS name
        UNION ALL SELECT 'delete_uploads' AS name
        UNION ALL SELECT 'edit_blog' AS name
        UNION ALL SELECT 'edit_blog_category' AS name
        UNION ALL SELECT 'edit_brand' AS name
        UNION ALL SELECT 'edit_classified_package' AS name
        UNION ALL SELECT 'edit_color' AS name
        UNION ALL SELECT 'edit_coupon' AS name
        UNION ALL SELECT 'edit_customer' AS name
        UNION ALL SELECT 'edit_digital_product' AS name
        UNION ALL SELECT 'edit_driver' AS name
        UNION ALL SELECT 'edit_dynamic_popups' AS name
        UNION ALL SELECT 'edit_flash_deal' AS name
        UNION ALL SELECT 'edit_offer_deal' AS name
        UNION ALL SELECT 'edit_order' AS name
        UNION ALL SELECT 'edit_order_price' AS name
        UNION ALL SELECT 'edit_product_attribute' AS name
        UNION ALL SELECT 'edit_product_attribute_value' AS name
        UNION ALL SELECT 'edit_product_category' AS name
        UNION ALL SELECT 'edit_representative_package' AS name
        UNION ALL SELECT 'edit_seller' AS name
        UNION ALL SELECT 'edit_staff' AS name
        UNION ALL SELECT 'edit_staff_role' AS name
        UNION ALL SELECT 'edit_trip' AS name
        UNION ALL SELECT 'edit_truck' AS name
        UNION ALL SELECT 'edit_website_page' AS name
        UNION ALL SELECT 'export_representative_payment' AS name
        UNION ALL SELECT 'facebook_chat' AS name
        UNION ALL SELECT 'facebook_comment' AS name
        UNION ALL SELECT 'features_activation' AS name
        UNION ALL SELECT 'footer_setup' AS name
        UNION ALL SELECT 'general_settings' AS name
        UNION ALL SELECT 'google_firebase_setting' AS name
        UNION ALL SELECT 'google_map_setting' AS name
        UNION ALL SELECT 'google_recaptcha_configuration' AS name
        UNION ALL SELECT 'header_setup' AS name
        UNION ALL SELECT 'language_setup' AS name
        UNION ALL SELECT 'manage_carriers' AS name
        UNION ALL SELECT 'manage_shipping_cities' AS name
        UNION ALL SELECT 'manage_shipping_states' AS name
        UNION ALL SELECT 'manage_zones' AS name
        UNION ALL SELECT 'order_configuration' AS name
        UNION ALL SELECT 'pay_to_seller' AS name
        UNION ALL SELECT 'payment_methods_configurations' AS name
        UNION ALL SELECT 'pickup_point_setup' AS name
        UNION ALL SELECT 'product_bulk_import' AS name
        UNION ALL SELECT 'product_delete' AS name
        UNION ALL SELECT 'product_edit' AS name
        UNION ALL SELECT 'publish_blog' AS name
        UNION ALL SELECT 'reply_to_product_queries' AS name
        UNION ALL SELECT 'reply_to_support_tickets' AS name
        UNION ALL SELECT 'seller_commission_configuration' AS name
        UNION ALL SELECT 'seller_payment_history' AS name
        UNION ALL SELECT 'seller_verification_form_configuration' AS name
        UNION ALL SELECT 'send_newsletter' AS name
        UNION ALL SELECT 'set_brand_wise_discount' AS name
        UNION ALL SELECT 'shipping_configuration' AS name
        UNION ALL SELECT 'shipping_country_setting' AS name
        UNION ALL SELECT 'smtp_settings' AS name
        UNION ALL SELECT 'update_order_delivery_status' AS name
        UNION ALL SELECT 'update_order_notes' AS name
        UNION ALL SELECT 'update_order_payment_status' AS name
        UNION ALL SELECT 'update_order_tracking' AS name
        UNION ALL SELECT 'upload_files' AS name
        UNION ALL SELECT 'vat_&_tax_setup' AS name
        UNION ALL SELECT 'view_all_brands' AS name
        UNION ALL SELECT 'view_all_customers' AS name
        UNION ALL SELECT 'view_all_drivers' AS name
        UNION ALL SELECT 'view_all_offer_deals' AS name
        UNION ALL SELECT 'view_all_product_conversations' AS name
        UNION ALL SELECT 'view_all_product_queries' AS name
        UNION ALL SELECT 'view_all_seller' AS name
        UNION ALL SELECT 'view_all_support_tickets' AS name
        UNION ALL SELECT 'view_all_trips' AS name
        UNION ALL SELECT 'view_all_trucks' AS name
        UNION ALL SELECT 'view_offers_catalog' AS name
        UNION ALL SELECT 'view_order_details' AS name
        UNION ALL SELECT 'view_representative_packages' AS name
        UNION ALL SELECT 'view_representative_payments' AS name
        UNION ALL SELECT 'view_representative_visits' AS name
        UNION ALL SELECT 'view_seller_payout_requests' AS name
        UNION ALL SELECT 'view_uploads' AS name
        UNION ALL SELECT 'website_appearanceadd_color' AS name
) AS chg
LEFT JOIN wms_permissions p ON p.name = chg.name AND p.guard_name = 'store'
ORDER BY row_state DESC, chg.name;


-- Q6. Roles holding the OLD permission of a changed gate but NOT the new one:
--     their members lose that action (report §E). Super Admin is excluded:
--     Gate::before passes it through every permission gate.
SELECT pr.old_permission, pr.new_permission, r.name AS role,
       (SELECT COUNT(*) FROM wms_model_has_roles mr
         WHERE mr.role_id = r.id AND mr.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')) AS store_members,
       pr.change_note, pr.proof
FROM (
        SELECT 'view_all_customers' AS old_permission, 'edit_customer' AS new_permission, 'customer create/edit/update/notes/verify/password/important routes and representative create/store moved from view_all_customers (8e3f82b6) to edit_customer' AS change_note, 'app/Http/Controllers/Store/Admin/CustomerController.php:48' AS proof
        UNION ALL SELECT 'view_all_customers' AS old_permission, 'delete_customer' AS new_permission, 'sidebar: customer archive link now follows its route (delete_customer)' AS change_note, 'resources/js/lib/storeAdminMenu.ts:325' AS proof
        UNION ALL SELECT 'view_all_customers' AS old_permission, 'add_notification' AS new_permission, 'sidebar: notifications link now follows its route (add_notification)' AS change_note, 'resources/js/lib/storeAdminMenu.ts:331' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes: the any-of view_* list became view_order_details' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_delivery_status' AS new_permission, 'orders.update_delivery_status' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:39' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_payment_status' AS new_permission, 'orders.update_payment_status' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:40' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_tracking' AS new_permission, 'orders.update_tracking_code' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:41' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_notes' AS new_permission, 'orders.update_manager_notes' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:42' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'edit_order_price' AS new_permission, 'order.item.edit_price' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:43' AS proof
        UNION ALL SELECT 'view_inhouse_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes (in-house list holders)' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_seller_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes (seller list holders)' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_pickup_point_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes (pickup point list holders)' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_all_product_queries' AS old_permission, 'reply_to_product_queries' AS new_permission, 'product_query.reply' AS change_note, 'app/Http/Controllers/Store/Admin/ProductQueryController.php:28' AS proof
        UNION ALL SELECT 'view_all_support_tickets' AS old_permission, 'reply_to_support_tickets' AS new_permission, 'support_ticket.admin_store (ticket reply)' AS change_note, 'app/Http/Controllers/Store/Admin/SupportTicketController.php:27' AS proof
        UNION ALL SELECT 'send_newsletter' AS old_permission, 'smtp_settings' AS new_permission, 'test.smtp now needs smtp_settings AND send_newsletter' AS change_note, 'app/Http/Controllers/Store/Admin/SettingsController.php:108' AS proof
        UNION ALL SELECT 'set_brand_wise_discount' AS old_permission, 'view_all_brands' AS new_permission, 'sidebar: brand discount link now follows its route (view_all_brands), set_product_discount needs product_edit' AS change_note, 'resources/js/lib/storeAdminMenu.ts:249' AS proof
        UNION ALL SELECT 'approve_seller' AS old_permission, 'edit_seller' AS new_permission, 'seller approval switch follows sellers.approved (edit_seller)' AS change_note, 'app/Http/Controllers/Store/Admin/SellerController.php:46' AS proof
        UNION ALL SELECT 'add_classified_package' AS old_permission, 'edit_classified_package' AS new_permission, 'classified package Add button follows customer_packages.create (edit_classified_package)' AS change_note, 'app/Http/Controllers/Store/Admin/CustomerPackageController.php:36' AS proof
        UNION ALL SELECT 'delete_order' AS old_permission, 'edit_trip' AS new_permission, 'trip orders bulk status menu now follows its route (edit_trip), Blade showed it under delete_order' AS change_note, 'app/Http/Controllers/Store/Admin/TripOrderController.php:49' AS proof
        UNION ALL SELECT 'add_color' AS old_permission, 'edit_color' AS new_permission, 'colors.store needs edit_color, the legacy Blade add form checked add_color' AS change_note, 'app/Http/Controllers/Store/Admin/ColorController.php:26' AS proof
        UNION ALL SELECT 'add_product_attribute' AS old_permission, 'edit_product_attribute' AS new_permission, 'attributes.store needs edit_product_attribute, the legacy Blade add form checked add_product_attribute' AS change_note, 'app/Http/Controllers/Store/Admin/AttributeController.php:29' AS proof
        UNION ALL SELECT 'add_product_attribute_values' AS old_permission, 'edit_product_attribute_value' AS new_permission, 'store-attribute-value needs edit_product_attribute_value, the legacy Blade add form checked add_product_attribute_values' AS change_note, 'app/Http/Controllers/Store/Admin/AttributeValueController.php:27' AS proof
        UNION ALL SELECT 'general_settings' AS old_permission, 'footer_setup' AS new_permission, 'retired business_settings.update let any of its gates permissions write footer keys, now only footer_setup' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29' AS proof
        UNION ALL SELECT 'general_settings' AS old_permission, 'website_appearance' AS new_permission, 'same, appearance keys' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29' AS proof
        UNION ALL SELECT 'edit_website_page' AS old_permission, 'footer_setup' AS new_permission, 'same, footer keys' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29' AS proof
        UNION ALL SELECT 'edit_website_page' AS old_permission, 'website_appearance' AS new_permission, 'same, appearance keys' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29' AS proof
) AS pr
JOIN wms_permissions po ON po.name = pr.old_permission AND po.guard_name = 'store'
JOIN wms_role_has_permissions rpo ON rpo.permission_id = po.id
JOIN wms_roles r ON r.id = rpo.role_id AND r.guard_name = 'store' AND r.name <> 'Super Admin'
WHERE NOT EXISTS (
    SELECT 1 FROM wms_role_has_permissions rpn
    JOIN wms_permissions pn ON pn.id = rpn.permission_id AND pn.guard_name = 'store'
    WHERE rpn.role_id = r.id AND pn.name = pr.new_permission
)
ORDER BY pr.old_permission, pr.new_permission, r.name
LIMIT 1000;


-- Q6b. Same question for users holding the old permission DIRECTLY
--      (wms_model_has_permissions), outside any role.
SELECT pr.old_permission, pr.new_permission, u.id AS user_id, u.user_type, u.email
FROM (
        SELECT 'view_all_customers' AS old_permission, 'edit_customer' AS new_permission, 'customer create/edit/update/notes/verify/password/important routes and representative create/store moved from view_all_customers (8e3f82b6) to edit_customer' AS change_note, 'app/Http/Controllers/Store/Admin/CustomerController.php:48' AS proof
        UNION ALL SELECT 'view_all_customers' AS old_permission, 'delete_customer' AS new_permission, 'sidebar: customer archive link now follows its route (delete_customer)' AS change_note, 'resources/js/lib/storeAdminMenu.ts:325' AS proof
        UNION ALL SELECT 'view_all_customers' AS old_permission, 'add_notification' AS new_permission, 'sidebar: notifications link now follows its route (add_notification)' AS change_note, 'resources/js/lib/storeAdminMenu.ts:331' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes: the any-of view_* list became view_order_details' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_delivery_status' AS new_permission, 'orders.update_delivery_status' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:39' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_payment_status' AS new_permission, 'orders.update_payment_status' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:40' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_tracking' AS new_permission, 'orders.update_tracking_code' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:41' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'update_order_notes' AS new_permission, 'orders.update_manager_notes' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:42' AS proof
        UNION ALL SELECT 'view_all_orders' AS old_permission, 'edit_order_price' AS new_permission, 'order.item.edit_price' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:43' AS proof
        UNION ALL SELECT 'view_inhouse_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes (in-house list holders)' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_seller_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes (seller list holders)' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_pickup_point_orders' AS old_permission, 'view_order_details' AS new_permission, 'order detail routes (pickup point list holders)' AS change_note, 'app/Http/Controllers/Store/Admin/OrderController.php:37' AS proof
        UNION ALL SELECT 'view_all_product_queries' AS old_permission, 'reply_to_product_queries' AS new_permission, 'product_query.reply' AS change_note, 'app/Http/Controllers/Store/Admin/ProductQueryController.php:28' AS proof
        UNION ALL SELECT 'view_all_support_tickets' AS old_permission, 'reply_to_support_tickets' AS new_permission, 'support_ticket.admin_store (ticket reply)' AS change_note, 'app/Http/Controllers/Store/Admin/SupportTicketController.php:27' AS proof
        UNION ALL SELECT 'send_newsletter' AS old_permission, 'smtp_settings' AS new_permission, 'test.smtp now needs smtp_settings AND send_newsletter' AS change_note, 'app/Http/Controllers/Store/Admin/SettingsController.php:108' AS proof
        UNION ALL SELECT 'set_brand_wise_discount' AS old_permission, 'view_all_brands' AS new_permission, 'sidebar: brand discount link now follows its route (view_all_brands), set_product_discount needs product_edit' AS change_note, 'resources/js/lib/storeAdminMenu.ts:249' AS proof
        UNION ALL SELECT 'approve_seller' AS old_permission, 'edit_seller' AS new_permission, 'seller approval switch follows sellers.approved (edit_seller)' AS change_note, 'app/Http/Controllers/Store/Admin/SellerController.php:46' AS proof
        UNION ALL SELECT 'add_classified_package' AS old_permission, 'edit_classified_package' AS new_permission, 'classified package Add button follows customer_packages.create (edit_classified_package)' AS change_note, 'app/Http/Controllers/Store/Admin/CustomerPackageController.php:36' AS proof
        UNION ALL SELECT 'delete_order' AS old_permission, 'edit_trip' AS new_permission, 'trip orders bulk status menu now follows its route (edit_trip), Blade showed it under delete_order' AS change_note, 'app/Http/Controllers/Store/Admin/TripOrderController.php:49' AS proof
        UNION ALL SELECT 'add_color' AS old_permission, 'edit_color' AS new_permission, 'colors.store needs edit_color, the legacy Blade add form checked add_color' AS change_note, 'app/Http/Controllers/Store/Admin/ColorController.php:26' AS proof
        UNION ALL SELECT 'add_product_attribute' AS old_permission, 'edit_product_attribute' AS new_permission, 'attributes.store needs edit_product_attribute, the legacy Blade add form checked add_product_attribute' AS change_note, 'app/Http/Controllers/Store/Admin/AttributeController.php:29' AS proof
        UNION ALL SELECT 'add_product_attribute_values' AS old_permission, 'edit_product_attribute_value' AS new_permission, 'store-attribute-value needs edit_product_attribute_value, the legacy Blade add form checked add_product_attribute_values' AS change_note, 'app/Http/Controllers/Store/Admin/AttributeValueController.php:27' AS proof
        UNION ALL SELECT 'general_settings' AS old_permission, 'footer_setup' AS new_permission, 'retired business_settings.update let any of its gates permissions write footer keys, now only footer_setup' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29' AS proof
        UNION ALL SELECT 'general_settings' AS old_permission, 'website_appearance' AS new_permission, 'same, appearance keys' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29' AS proof
        UNION ALL SELECT 'edit_website_page' AS old_permission, 'footer_setup' AS new_permission, 'same, footer keys' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteFooterController.php:29' AS proof
        UNION ALL SELECT 'edit_website_page' AS old_permission, 'website_appearance' AS new_permission, 'same, appearance keys' AS change_note, 'app/Http/Controllers/Store/Admin/WebsiteAppearanceController.php:29' AS proof
) AS pr
JOIN wms_permissions po ON po.name = pr.old_permission AND po.guard_name = 'store'
JOIN wms_model_has_permissions mpo ON mpo.permission_id = po.id AND mpo.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')
JOIN users u ON u.id = mpo.model_id
WHERE NOT EXISTS (
    SELECT 1 FROM wms_model_has_permissions mpn
    JOIN wms_permissions pn ON pn.id = mpn.permission_id AND pn.guard_name = 'store'
    WHERE mpn.model_id = u.id AND mpn.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser') AND pn.name = pr.new_permission
)
ORDER BY pr.old_permission, u.id
LIMIT 500;


-- Q7. Back-office store accounts (IsAdmin admits user_type admin, staff and
--     accountant) with NO store role and NO direct store permission: they pass
--     IsAdmin but every permission-gated route answers 403 (including the
--     post-login dashboard, which now needs admin_dashboard).
SELECT u.id, u.user_type, u.name, u.email, u.banned
FROM users u
WHERE u.user_type IN ('admin', 'staff', 'accountant')
  AND NOT EXISTS (
      SELECT 1 FROM wms_model_has_roles mr
      JOIN wms_roles r ON r.id = mr.role_id AND r.guard_name = 'store'
      WHERE mr.model_id = u.id AND mr.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser'))
  AND NOT EXISTS (
      SELECT 1 FROM wms_model_has_permissions mp
      JOIN wms_permissions p ON p.id = mp.permission_id AND p.guard_name = 'store'
      WHERE mp.model_id = u.id AND mp.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser'))
ORDER BY u.user_type, u.id
LIMIT 500;


-- Q8. Super Admin members (store guard). Gate::before grants them every
--     permission gate; RaizerSuperAdmin routes additionally need
--     user_type = 'admin' AND raizer_admin = 1 (app/Http/Middleware/RaizerSuperAdmin.php:27).
SELECT u.id, u.user_type, u.name, u.email, u.raizer_admin, u.banned
FROM wms_roles r
JOIN wms_model_has_roles mr ON mr.role_id = r.id AND mr.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')
JOIN users u ON u.id = mr.model_id
WHERE r.guard_name = 'store' AND r.name = 'Super Admin'
ORDER BY u.id
LIMIT 200;


-- Q9. Supporting checks (read-only).

-- Q9a. Store-guard role pivots whose model_type is NOT the store user class
--      (legacy import leftovers, e.g. App\Models\User). Expected: 0 rows.
--      (.ai/rules/legacy-albaraka-holland.md §6.7.1.)
SELECT mr.model_type, COUNT(*) AS pivots
FROM wms_model_has_roles mr
JOIN wms_roles r ON r.id = mr.role_id AND r.guard_name = 'store'
WHERE mr.model_type <> CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')
GROUP BY mr.model_type;

-- Q9b. Store-guard permission pivots with a foreign model_type. Expected: 0 rows.
SELECT mp.model_type, COUNT(*) AS pivots
FROM wms_model_has_permissions mp
JOIN wms_permissions p ON p.id = mp.permission_id AND p.guard_name = 'store'
WHERE mp.model_type <> CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')
GROUP BY mp.model_type;

-- Q9c. Role-permission pivots joining a store role to a non-store permission
--      or the reverse, seen from the store side. Expected: 0 rows.
SELECT r.name AS store_role, rp.permission_id
FROM wms_role_has_permissions rp
JOIN wms_roles r ON r.id = rp.role_id AND r.guard_name = 'store'
LEFT JOIN wms_permissions p ON p.id = rp.permission_id AND p.guard_name = 'store'
WHERE p.id IS NULL
LIMIT 200;

-- Q9d. staff rows whose role_id disagrees with the spatie pivot (the staff
--      screens write both; StaffController.php:85-88, :124-127).
SELECT s.id AS staff_id, s.user_id, s.role_id AS staff_role_id,
       (SELECT GROUP_CONCAT(mr.role_id ORDER BY mr.role_id)
          FROM wms_model_has_roles mr
          JOIN wms_roles r ON r.id = mr.role_id AND r.guard_name = 'store'
         WHERE mr.model_id = s.user_id AND mr.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser')) AS pivot_role_ids
FROM staff s
WHERE NOT EXISTS (
    SELECT 1 FROM wms_model_has_roles mr
    JOIN wms_roles r ON r.id = mr.role_id AND r.guard_name = 'store'
    WHERE mr.model_id = s.user_id AND mr.model_type = CONCAT('App', CHAR(92), 'Models', CHAR(92), 'StoreUser') AND mr.role_id = s.role_id)
ORDER BY s.id
LIMIT 500;

-- Q9e. The full role x permission matrix for the store guard (for the owner's
--      grant review; one row per grant).
SELECT r.name AS role, p.section, p.name AS permission
FROM wms_role_has_permissions rp
JOIN wms_roles r ON r.id = rp.role_id AND r.guard_name = 'store'
JOIN wms_permissions p ON p.id = rp.permission_id AND p.guard_name = 'store'
ORDER BY r.name, p.section, p.name
LIMIT 10000;

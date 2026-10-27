# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Requests/Concerns/FiltersCellStatusLogs.php, app/Http/Requests/{Admin,Api/V1}/FilterCellStatusLogsRequest.php, app/Models/CellStatusLog.php, app/Http/Controllers/{Admin,Api/V1}/CellStatusLogController.php, resources/js/pages/Admin/CellStatusLogs/** | .ai/rules/cell-log-filters.md |
| resources/js/pages/Admin/CellStatusLogs/**, resources/js/pages/Admin/Users/Show.vue, resources/js/lib/cellStatusLogDisplay.ts, resources/js/components/CellStatusLogRowCells.vue | .ai/rules/cell-log-display.md |
| app/Models/CellStatusLog.php, app/Observers/CellStatusLogObserver.php, config/cell_status_log_flags.php, tests/Feature/Observers/CellStatusLogObserverTest.php | .ai/rules/cell-log-flags.md |
| resources/js/pages/Admin/Rows/Show.vue, resources/js/components/CellSlot.vue, resources/js/components/CellHighlightFilters.vue, resources/js/lib/cellStatus.ts, resources/js/lib/cellHighlight.ts | .ai/rules/cell-highlights.md |
| resources/js/components/CellSlot.vue, resources/js/components/CellStateLegend.vue, resources/js/components/CellHighlightFilters.vue, resources/js/lib/cellStateColor.ts, resources/js/pages/Admin/CellStatusLogs/**, resources/js/components/CellStatusLogRowCells.vue | .ai/rules/cell-state-visuals.md |
| app/Http/Controllers/Admin/UserController.php, app/Http/Requests/Admin/ShowUserRequest.php, resources/js/pages/Admin/Users/Show.vue, resources/js/pages/Admin/CellVerificationRounds/Show.vue, resources/js/lib/cellVerificationReportDisplay.ts, resources/js/components/CellVerificationCorrectnessBadge.vue | .ai/rules/user-activity-history.md |
| app/Models/Product.php, app/Http/Requests/Concerns/FiltersByProductIds.php, app/Http/Controllers/Admin/ProductController.php, app/Http/Controllers/Api/V1/{ProductController,DashboardController}.php, resources/js/components/FilterProductSelect.vue | .ai/rules/product-filters.md |
| app/Http/Requests/Admin/StoreRowRequest.php, app/Http/Requests/Admin/UpdateRowRequest.php | .ai/rules/row-letter-normalization.md |
| app/Http/Controllers/Admin/RowController.php, app/Http/Requests/Admin/UpdateRowRequest.php | .ai/rules/row-resizing.md |
| resources/js/components/FilterDialog.vue, resources/js/components/FilterDialog.test.ts, resources/js/components/FilterMultiSelect.vue, resources/js/components/FilterMultiSelect.test.ts, resources/js/components/FilterProductSelect.vue, resources/js/components/FilterProductSelect.test.ts | .ai/rules/filter-dialogs.md |
| resources/js/pages/Admin/Products/Index.vue, app/Http/Controllers/Admin/ProductController.php | .ai/rules/admin-products.md |
| resources/js/pages/Admin/** | .ai/rules/admin.md |
| app/Providers/TelescopeServiceProvider.php, app/Providers/AppServiceProvider.php,bootstrap/app.php,routes/web.php,routes/api.php | .ai/rules/app-providers.md |
| bootstrap/app.php,app/Http/Controllers/LoginController.php,resources/js/pages/Auth/Login.vue,routes/web.php | .ai/rules/auth.md |
| bootstrap/app.php | .ai/rules/bootstrap.md |
| app/Http/Middleware/EnsureMinimumAppVersion.php,app/Console/Commands/SetMinimumAppVersionCommand.php,app/Models/MobileAppVersionRequirement.php | .ai/rules/commands-models.md |
| config/telescope.php,database/migrations/*telescope*, config/pulse.php,database/migrations/*pulse* | .ai/rules/config-migrations.md |
| config/backup.php,config/database.php, config/database.php, config/cache.php | .ai/rules/config.md |
| .github/**,.claude/scripts/pre_push_check.py,.claude/scripts/install_playwright.py,.claude/hooks/session-start.sh | .ai/rules/ci.md |
| app/Http/Controllers/** | .ai/rules/controllers.md |
| resources/js/pages/Admin/Dashboard/Index.vue | .ai/rules/dashboard.md |
| tests/e2e/** | .ai/rules/e2e.md |
| app/Enums/** | .ai/rules/enums.md |
| app/Exceptions/** | .ai/rules/exceptions.md |
| composer.json,composer.lock,package.json,package-lock.json,.github/dependabot.yml | .ai/rules/dependencies.md |
| database/factories/**, database/factories/PalletFactory.php | .ai/rules/factories.md |
| resources/js/**, resources/views/**, app/Http/Requests/**, app/Http/Controllers/**, tests/** | .ai/rules/form-required-fields.md |
| resources/js/components/ResourceFormPage.vue, resources/js/components/UserFormFields.vue, resources/js/components/DataTable.vue | .ai/rules/js-components.md |
| resources/js/** | .ai/rules/js.md |
| lang/** | .ai/rules/lang.md |
| composer.json,composer.lock,package.json,bootstrap/app.php,bootstrap/providers.php,config/app.php,config/auth.php,config/cache.php,config/services.php,config/filesystems.php,config/permission.php,config/session.php,config/store.php,config/secure-headers.php,routes/web.php,routes/api.php,routes/console.php,routes/store/**,app/Models/User.php,app/Models/Product.php,app/Models/Upload.php,app/Models/PersonalAccessToken.php,app/Models/Store/**,app/Http/Controllers/Store/**,app/Http/Controllers/Controller.php,app/Http/Controllers/WelcomeController.php,app/Providers/AppServiceProvider.php,app/Console/Commands/SyncProductsCommand.php,database/migrations/**,database/schema/**,public/**,resources/views/**,lang/**,tests/Feature/RootRedirectTest.php,tests/Feature/SharedDatabaseTableNamesTest.php,app/Models/StoreUser.php,app/Models/Role.php,app/Models/Permission.php,app/Providers/AuthServiceProvider.php,config/sanctum.php,routes/shop-*.php,tests/Feature/StoreAuthGuardTest.php,tests/Feature/AuthGuardIsolationTest.php | .ai/rules/legacy-albaraka-holland.md |
| resources/js/layouts/** | .ai/rules/layouts.md |
| resources/views/shop/backend/**,resources/js/pages/Store/**,resources/js/components/StoreUpload*,resources/js/components/StoreFileManagerDialog*,resources/js/components/StoreProfile*,app/Services/MultipartUploadService.php,resources/js/layouts/StoreAdminLayout.vue,resources/js/lib/storeAdminMenu.ts,resources/js/lib/storeAccess.ts,resources/js/components/StoreAdminNavLink.vue,resources/js/components/StoreRichTextField.vue,resources/js/components/StoreBlogFormFields.vue,resources/js/components/StoreNewsletterFormFields.vue,resources/js/components/StoreNewsletterRecipients.vue,resources/js/components/StoreTicketReplyForm.vue,resources/js/components/StoreWebsite*,resources/js/components/StoreProduct*,resources/js/lib/storeProductForm.ts,resources/js/components/StoreTripStatusDialog.vue,resources/js/components/StoreTripFormFields.vue,resources/js/components/StoreTripMap.vue,resources/js/components/StoreLeafletMap.vue,resources/js/components/StoreVisitMap.vue,resources/js/lib/storeTrip.ts,resources/js/lib/storeSupportTicket.ts,app/Services/Store/**,resources/js/components/StoreSeller*,resources/js/components/StoreStaffFormFields.vue,resources/js/components/StoreRoleFormFields.vue,resources/js/components/StoreLanguageFormFields.vue,resources/js/components/StoreBooleanField.vue,resources/js/components/StoreGoogleMapFormFields.vue,resources/js/components/StoreDashboardChart.vue,app/Http/Controllers/Store/**,app/Http/Requests/Store/**,app/Http/Resources/Store/**,app/Http/Middleware/HandleInertiaRequests.php,app/Http/Middleware/HandleStoreBladePages.php,app/Http/Middleware/SendSecureHeaders.php,app/Http/Middleware/SendNoIndexHeader.php,app/Http/Controllers/RobotsController.php,app/Http/Controllers/SitemapController.php,app/Services/Sitemap.php,app/Support/Seo.php,app/Support/StructuredData.php,resources/views/shop/frontend/partials/seo_meta.blade.php,resources/views/shop/frontend/partials/json_ld.blade.php,routes/shop-admin.php,tests/Feature/Store/**,tests/Feature/Storefront*,tests/e2e/store-*.spec.ts | .ai/rules/store-backoffice-migration.md |
| app/Http/Requests/Seller/*Product*Request.php,app/Http/Requests/Concerns/ValidatesSellerProductFields.php,app/Http/Controllers/Seller/DigitalProductController.php,resources/views/shop/seller/product/digitalproducts/**,public/assets/js/seller-digital-product-form.js | .ai/rules/store-backoffice-migration.md |
| app/Support/ProductColumnOwnership.php,app/Models/ProductBuilder.php,app/Services/Store/*ProductWriter.php,app/Http/Requests/Seller/*Product*Request.php,app/Services/ProductService.php,app/Models/ProductsImport.php,app/Http/Controllers/Seller/*ProductController.php,database/migrations/**,config/database.php,config/permission.php,config/session.php,config/cache.php,config/queue.php,config/auth.php,app/Models/PersonalAccessToken.php,app/Models/Product.php,app/Providers/AppServiceProvider.php,resources/js/lib/productName.ts,resources/js/components/ProductOptionLabel.vue,app/Console/Commands/SyncProductsCommand.php,config/store.php | .ai/rules/shared-database.md |
| app/Http/Middleware/**, app/Http/Middleware/BlockMaliciousRequests.php,config/waf.php, app/Http/Middleware/RestrictToAllowedIps.php,config/telescope.php,config/pulse.php | .ai/rules/middleware.md |
| database/migrations/**, database/migrations/2026_08_08_154136_create_products_table.php | .ai/rules/migrations.md |
| app/Models/*.php, app/Models/CellStatusLog.php, app/Models/Row.php, app/Models/Pallet.php | .ai/rules/models.md |
| app/Observers/** | .ai/rules/observers.md |
| resources/js/pages/** | .ai/rules/pages.md |
| app/Http/Requests/Admin/FilterProductsRequest.php,resources/js/pages/Admin/Products/Index.vue | .ai/rules/products.md |
| config/health.php,database/migrations/*health*,app/Providers/HealthServiceProvider.php | .ai/rules/providers.md |
| app/Http/Requests/Admin/{FilterProductsRequest,FilterMatchingPalletsRequest}.php | .ai/rules/requests-admin.md |
| app/Http/Requests/Concerns/NormalizesBooleanFilters.php, app/Http/Requests/Concerns/NormalizesExpiredFilter.php, app/Http/Requests/Concerns/FiltersCellStatusLogs.php | .ai/rules/requests-concerns.md |
| app/Http/Requests/** | .ai/rules/requests.md |
| app/Http/Resources/**, app/Http/Resources/RowResource.php | .ai/rules/resources.md |
| routes/web.php, routes/api.php, routes/console.php, routes/shop-*.php | .ai/rules/routes.md |
| app/Http/Controllers/Admin/RowController.php,resources/js/pages/Admin/Rows/Index.vue,resources/js/components/ActionErrorBanner.vue | .ai/rules/rows-js-components.md |
| database/seeders/** | .ai/rules/seeders.md |
| tests/** | .ai/rules/tests.md |
| resources/js/types/admin.ts | .ai/rules/types.md |
| app/Http/Controllers/Api/V1/PalletController.php | .ai/rules/v1.md |
| resources/views/** | .ai/rules/views.md |
| resources/js/warehouse-map/**,resources/css/warehouse-map/**,resources/js/pages/Admin/WarehouseMap/**,app/Http/Controllers/Admin/WarehouseMapController.php,tests/e2e/warehouse-map.spec.ts | .ai/rules/warehouse-map.md |

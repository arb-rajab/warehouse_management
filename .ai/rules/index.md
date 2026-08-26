# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Requests/Concerns/FiltersCellStatusLogs.php,app/Models/CellStatusLog.php,resources/js/pages/Admin/CellStatusLogs/Index.vue | .ai/rules/admin-cell-status-logs.md |
| app/Http/Controllers/Admin/RowController.php,app/Http/Requests/UpdateRowRequest.php | .ai/rules/admin-http-requests.md |
| resources/js/pages/Admin/Products/Index.vue | .ai/rules/admin-products.md |
| resources/js/pages/Admin/** | .ai/rules/admin.md |
| app/Providers/TelescopeServiceProvider.php, app/Providers/AppServiceProvider.php,bootstrap/app.php,routes/web.php,routes/api.php | .ai/rules/app-providers.md |
| bootstrap/app.php,app/Http/Controllers/LoginController.php,resources/js/pages/Auth/Login.vue,routes/web.php | .ai/rules/auth.md |
| bootstrap/app.php | .ai/rules/bootstrap.md |
| resources/js/pages/Admin/CellStatusLogs/** | .ai/rules/cell-status-logs.md |
| resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellMap3D.vue | .ai/rules/cells-js-components.md |
| resources/js/pages/Admin/Cells/Index.vue | .ai/rules/cells.md |
| app/Http/Middleware/EnsureMinimumAppVersion.php,app/Console/Commands/SetMinimumAppVersionCommand.php,app/Models/MobileAppVersionRequirement.php | .ai/rules/commands-models.md |
| resources/js/components/CellSlot.vue,resources/js/components/CellMap3D.vue,resources/js/lib/cellStateColor.ts | .ai/rules/components-js-components-js-lib.md |
| resources/js/pages/Admin/Rows/Show.vue,resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellHighlightFilters.vue,resources/js/lib/cellHighlight.ts,resources/js/lib/cellStatus.ts | .ai/rules/components-js-lib.md |
| resources/js/components/FilterDialog.vue,resources/js/components/FilterDialog.test.ts, resources/js/components/FilterMultiSelect.vue,resources/js/components/FilterMultiSelect.test.ts | .ai/rules/components.md |
| app/Http/Requests/Concerns/FiltersCellStatusLogs.php,app/Models/CellStatusLog.php | .ai/rules/concerns-models.md |
| app/Models/Product.php,app/Http/Requests/Concerns/FiltersByProductIds.php | .ai/rules/concerns.md |
| config/telescope.php,database/migrations/*telescope*, config/pulse.php,database/migrations/*pulse* | .ai/rules/config-migrations.md |
| config/backup.php,config/database.php, config/database.php, config/cache.php | .ai/rules/config.md |
| resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/cellHighlight.ts,resources/js/lib/cellStatus.ts,app/Http/Controllers/Admin/CellController.php | .ai/rules/controllers-admin.md |
| app/Http/Controllers/** | .ai/rules/controllers.md |
| resources/js/pages/Admin/Dashboard/Index.vue | .ai/rules/dashboard.md |
| tests/e2e/** | .ai/rules/e2e.md |
| app/Enums/** | .ai/rules/enums.md |
| app/Exceptions/** | .ai/rules/exceptions.md |
| database/factories/**, database/factories/PalletFactory.php | .ai/rules/factories.md |
| app/Models/Product.php,app/Http/Requests/Concerns/FiltersByProductIds.php,app/Http/Controllers/Admin/ProductController.php | .ai/rules/http-controllers-admin.md |
| app/Http/Requests/StoreRowRequest.php,app/Http/Requests/UpdateRowRequest.php | .ai/rules/http-requests.md |
| resources/js/components/CellMap3D.vue,resources/js/lib/mapWalker.ts | .ai/rules/js-components-js-lib.md |
| resources/js/components/CellSlot.vue, resources/js/components/ResourceFormPage.vue, resources/js/components/UserFormFields.vue, resources/js/components/CellMap3D.test.ts, resources/js/components/CellMap3D.vue, resources/js/components/DataTable.vue | .ai/rules/js-components.md |
| resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/mapViewport.ts | .ai/rules/js-lib.md |
| resources/js/lib/cellStateColor.ts,resources/js/components/CellMap3D.vue,resources/js/components/CellHighlightFilters.vue,resources/js/pages/Admin/CellStatusLogs/Index.vue | .ai/rules/js-pages-admin-cell-status-logs.md |
| resources/js/pages/Admin/Cells/Index.vue,resources/js/lib/mapViewport.ts,app/Http/Controllers/Admin/CellController.php,resources/js/types/admin.ts | .ai/rules/js-types.md |
| resources/js/** | .ai/rules/js.md |
| lang/** | .ai/rules/lang.md |
| resources/js/layouts/** | .ai/rules/layouts.md |
| resources/js/pages/Admin/Rows/Show.vue,resources/js/pages/Admin/Cells/Index.vue,resources/js/components/CellSlot.vue,resources/js/components/CellHighlightFilters.vue,resources/js/lib/cellStatus.ts,resources/js/lib/cellHighlight.ts | .ai/rules/lib.md |
| app/Http/Middleware/**, app/Http/Middleware/BlockMaliciousRequests.php,config/waf.php, app/Http/Middleware/RestrictToAllowedIps.php,config/telescope.php,config/pulse.php | .ai/rules/middleware.md |
| database/migrations/**, database/migrations/2026_08_08_154136_create_products_table.php | .ai/rules/migrations.md |
| app/Models/CellStatusLog.php,app/Observers/CellStatusLogObserver.php,config/cell_status_log_flags.php | .ai/rules/models-observers.md |
| app/Models/*.php, app/Models/CellStatusLog.php, app/Models/Row.php, app/Models/Pallet.php | .ai/rules/models.md |
| app/Observers/** | .ai/rules/observers.md |
| resources/js/pages/Admin/CellStatusLogs/Index.vue,resources/js/pages/Admin/CellStatusLogs/Index.test.ts | .ai/rules/pages-admin-cell-status-logs.md |
| resources/js/pages/** | .ai/rules/pages.md |
| app/Http/Requests/Admin/FilterProductsRequest.php,resources/js/pages/Admin/Products/Index.vue | .ai/rules/products.md |
| config/health.php,database/migrations/*health*,app/Providers/HealthServiceProvider.php | .ai/rules/providers.md |
| app/Http/Requests/Admin/{FilterProductsRequest,ShowCellMapRequest}.php | .ai/rules/requests-admin.md |
| app/Http/Requests/Concerns/NormalizesBooleanFilters.php, app/Http/Requests/Concerns/NormalizesExpiredFilter.php, app/Http/Requests/Concerns/FiltersCellStatusLogs.php | .ai/rules/requests-concerns.md |
| app/Http/Requests/** | .ai/rules/requests.md |
| app/Http/Resources/**, app/Http/Resources/RowResource.php | .ai/rules/resources.md |
| routes/web.php, routes/api.php, routes/console.php | .ai/rules/routes.md |
| app/Http/Controllers/Admin/RowController.php,resources/js/pages/Admin/Rows/Index.vue,resources/js/components/ActionErrorBanner.vue | .ai/rules/rows-js-components.md |
| database/seeders/** | .ai/rules/seeders.md |
| tests/** | .ai/rules/tests.md |
| resources/js/types/admin.ts | .ai/rules/types.md |
| resources/js/pages/Admin/CellStatusLogs/Index.vue,resources/js/pages/Admin/Users/Show.vue,resources/js/lib/cellStatusLogDisplay.ts | .ai/rules/users-js-lib.md |
| resources/views/** | .ai/rules/views.md |

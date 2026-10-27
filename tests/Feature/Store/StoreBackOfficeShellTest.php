<?php

use Illuminate\Support\Facades\File;

test('the retired admin shells are absent and no executable source references them', function () {
    foreach (['layouts/app', 'inc/admin_nav', 'inc/admin_sidenav'] as $shell) {
        expect(resource_path('views/shop/backend/'.$shell.'.blade.php'))->not->toBeFile();
    }

    expect(resource_path('views/shop/backend/inc'))->not->toBeDirectory();

    $references = [];
    foreach (['app', 'routes', 'resources', 'config', 'tests', 'public/assets/js'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if ($file->getRealPath() === __FILE__ || ! in_array($file->getExtension(), ['php', 'js', 'ts', 'vue'], true)) {
                continue;
            }

            // Match dot/slash view names regardless of directive, quoting or renderer.
            if (preg_match('~shop[./]backend[./](?:layouts[./]app|inc[./]admin_(?:nav|sidenav))\b~i', $file->getContents())) {
                $references[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($references)->toBe([]);
});

test('retained store Blade templates still serve their protected consumers', function () {
    $sources = iterator_to_array(sourceFilesUnder(['resources/views']));
    $controllerSources = iterator_to_array(sourceFilesUnder(['app/Http/Controllers']));

    $plainLayout = 'resources/views/shop/backend/layouts/layout.blade.php';
    $blankLayout = 'resources/views/shop/backend/layouts/blank.blade.php';

    $plainLayoutConsumers = array_keys(array_filter(
        $sources,
        fn (string $source): bool => str_contains($source, "@extends('shop.backend.layouts.layout')"),
    ));
    $invoiceConsumers = array_keys(array_filter(
        $controllerSources,
        fn (string $source): bool => str_contains(strtolower($source), "pdf::loadview('shop.backend.invoices.invoice'"),
    ));
    $brandDownloadConsumers = array_keys(array_filter(
        $controllerSources,
        fn (string $source): bool => str_contains(strtolower($source), "pdf::loadview('shop.backend.downloads.brand'"),
    ));
    $categoryDownloadConsumers = array_keys(array_filter(
        $controllerSources,
        fn (string $source): bool => str_contains(strtolower($source), "pdf::loadview('shop.backend.downloads.category'"),
    ));
    $userDownloadConsumers = array_keys(array_filter(
        $controllerSources,
        fn (string $source): bool => str_contains(strtolower($source), "pdf::loadview('shop.backend.downloads.user'"),
    ));

    sort($plainLayoutConsumers);
    sort($invoiceConsumers);
    sort($brandDownloadConsumers);
    sort($categoryDownloadConsumers);

    expect($sources)->toHaveKeys([
        $plainLayout,
        'resources/views/app.blade.php',
        'resources/views/shop/backend/invoices/invoice.blade.php',
        'resources/views/shop/backend/downloads/brand.blade.php',
        'resources/views/shop/backend/downloads/category.blade.php',
        'resources/views/shop/backend/downloads/user.blade.php',
        'resources/views/shop/backend/product/products/sku_combinations.blade.php',
        'resources/views/shop/backend/product/products/sku_combinations_edit.blade.php',
    ])
        ->and($plainLayoutConsumers)->toBe([
            'resources/views/shop/errors/503.blade.php',
            'resources/views/shop/frontend/deliveryboy_login.blade.php',
            'resources/views/shop/frontend/seller_login.blade.php',
        ])
        ->and($invoiceConsumers)->toBe([
            'app/Http/Controllers/InvoiceController.php',
            'app/Http/Controllers/Seller/InvoiceController.php',
        ])
        ->and($brandDownloadConsumers)->toBe([
            'app/Http/Controllers/Seller/ProductBulkUploadController.php',
            'app/Http/Controllers/Store/Admin/ProductBulkUploadController.php',
        ])
        ->and($categoryDownloadConsumers)->toBe([
            'app/Http/Controllers/Seller/ProductBulkUploadController.php',
            'app/Http/Controllers/Store/Admin/ProductBulkUploadController.php',
        ])
        ->and($userDownloadConsumers)->toContain('app/Http/Controllers/Store/Admin/ProductBulkUploadController.php')
        // The blank shell (the removed installer's card layout) had no
        // consumer and is gone (.ai/archive/store-backoffice/changes-20261014.md §23).
        ->and($sources)->not->toHaveKey($blankLayout)
        ->and(array_keys(array_filter([...$sources, ...$controllerSources], fn (string $source): bool => str_contains($source, 'shop.backend.layouts.blank'))))->toBe([])
        // The server status page (a controller-less Route::view) was deleted
        // on the owner's decision (.ai/archive/store-backoffice/changes-20261017.md §29).
        ->and($sources)->not->toHaveKey('resources/views/shop/backend/system/server_status.blade.php')
        ->and(file_get_contents(base_path('routes/shop-admin.php')))->not->toContain('server_status')
        ->and(config('store.blade_csp.script-src.unsafe-inline'))->toBeTrue();
});

test('the server status page is gone: its route is unregistered and a super admin gets a 404', function () {
    expect(Route::has('system_server'))->toBeFalse()
        ->and(collect(Route::getRoutes()->getRoutes())->map->uri()->filter(fn (string $uri): bool => str_contains($uri, 'server-status')))->toBeEmpty()
        ->and(base_path('resources/views/shop/backend/system'))->not->toBeDirectory();

    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));

    $this->get('/temp/admin/system/server-status')->assertNotFound();
});

<?php

use App\Http\Controllers\Admin\ProductController as WarehouseProductController;
use App\Http\Controllers\ProductController;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

dataset('retired root product handlers', [
    'subcategory' => ['/temp/admin/products/get_products_by_subcategory'],
    'search' => ['/temp/admin/products/search'],
]);

test('retired root product handlers reject every caller without changing catalogue data', function (string $uri, string $caller) {
    $this->withoutVite();
    match ($caller) {
        'guest' => null,
        'customer' => actingAsStoreUser(createStoreUser('customer')),
        'seller' => actingAsStoreUser(createStoreUser('seller')),
        'unprivileged staff' => actingAsStoreUser(createStoreStaff()),
        'product list staff' => actingAsStoreUser(createStoreStaff('show_all_products', 'show_in_house_products', 'show_seller_products')),
        'super admin' => actingAsStoreUser(createStoreAdmin()),
    };
    Product::factory()->create(['name' => 'Private store catalogue']);
    Product::factory()->feedOrigin()->create(['name' => 'Private feed catalogue']);
    $snapshot = DB::table('products')->orderBy('id')->get()->toArray();

    $response = $this->postJson($uri, ['search' => 'Private', 'q' => 'Private', 'user_id' => 1, 'subcategory_id' => 1]);
    expect($response->getContent())->not->toBe('');
    $response->assertMethodNotAllowed()
        ->assertDontSee('Private store catalogue')
        ->assertDontSee('Private feed catalogue');

    expect(DB::table('products')->orderBy('id')->get()->toArray())->toEqual($snapshot);
})->with('retired root product handlers')->with([
    'guest', 'customer', 'seller', 'unprivileged staff', 'product list staff', 'super admin',
]);

test('root product helper routes and dead methods are absent while warehouse search keeps its name', function () {
    expect(Route::has('products.get_products_by_subcategory'))->toBeFalse()
        ->and(Route::has('products.search'))->toBeFalse()
        ->and(method_exists(ProductController::class, 'get_products_by_subcategory'))->toBeFalse()
        ->and(method_exists(ProductController::class, 'search'))->toBeFalse();

    $routes = collect(Route::getRoutes()->getRoutes());
    expect($routes->whereIn('uri', [
        'temp/admin/products/get_products_by_subcategory', 'temp/admin/products/search',
    ]))->toHaveCount(0);

    $warehouseSearch = Route::getRoutes()->getByName('admin.products.search');
    expect($warehouseSearch)->not->toBeNull()
        ->and($warehouseSearch->uri())->toBe('admin/products/search')
        ->and($warehouseSearch->methods())->toBe(['GET', 'HEAD'])
        ->and($warehouseSearch->getActionName())->toBe(WarehouseProductController::class.'@search');

    $names = $routes->map(fn ($route) => $route->getName())->filter();
    expect($names->duplicates()->all())->toBe([]);
});

test('application sources contain no consumers of the retired root product helpers', function () {
    $references = [];
    foreach (['app', 'config', 'resources/views', 'resources/js', 'public/assets'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if (! in_array($file->getExtension(), ['php', 'js', 'ts', 'vue'], true)
                || str_starts_with($file->getRelativePathname(), 'actions/')
                || str_starts_with($file->getRelativePathname(), 'routes/')
                || str_starts_with($file->getRelativePathname(), 'wayfinder/')) {
                continue;
            }
            $source = $file->getContents();
            if (preg_match('~get_products_by_subcategory|[\'\"]products\.search[\'\"]|/temp/admin/products/search~', $source)
                || preg_match('~import\s+\{[^}]*\bsearch\b[^}]*\}\s+from\s+[\'\"]@/actions/App/Http/Controllers/ProductController[\'\"]~s', $source)) {
                $references[] = $directory.'/'.$file->getRelativePathname();
            }
        }
    }

    expect($references)->toBe([]);
});

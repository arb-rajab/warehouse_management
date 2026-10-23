<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

test('the bulk import page can be rendered with a narrow download prop', function () {
    actingAsStoreUser(createStoreStaff('product_bulk_import'));
    $this->get('/temp/admin/product-bulk-upload/index')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/BulkUpload/Index')->where('exampleUrl', static_asset('download/product_bulk_demo.xlsx')));
});

test('bulk import writes pricing stocks uploads and sanitized content with the existing CSV contract', function () {
    $owner = createStoreAdmin();
    actingAsStoreUser(createStoreStaff('product_bulk_import'));
    $row = catalogueImportRow();
    $this->post('/temp/admin/bulk-product-upload', ['bulk_file' => catalogueImportFile([$row])])->assertSessionHasNoErrors()->assertRedirect(route('product_bulk_upload.index'));
    expect(session()->has('flash_notification'))->toBeFalse();
    $product = Product::query()->where('name', 'Imported dates')->firstOrFail();
    expect($product->user_id)->toBe($owner->id)
        ->and($product->getAttribute('added_by'))->toBe('admin')
        ->and((float) $product->getAttribute('unit_price'))->toBe(2.5)
        ->and($product->getAttribute('description'))->toBe('<p>Fresh dates</p>')
        ->and($product->getAttribute('tags'))->toBe('dates,fresh')
        ->and($product->getAttribute('unit'))->toBe('box')
        ->and($product->getAttribute('meta_title'))->toBe('Date boxes')
        ->and($product->getAttribute('meta_description'))->toBe('Fresh food')
        ->and($product->getAttribute('slug'))->toStartWith('imported-dates-')
        ->and($product->getAttribute('choice_options'))->toBe('[]')
        ->and($product->getAttribute('colors'))->toBe('[]');
    $this->assertDatabaseHas('product_stocks', ['product_id' => $product->id, 'qty' => 12, 'price' => 2.5, 'sku' => 'DATE-12', 'variant' => '']);
    $this->assertDatabaseHas('uploads', ['id' => $product->thumbnail_img, 'external_link' => 'https://example.com/date.jpg']);
    $this->assertDatabaseHas('uploads', ['id' => $product->getAttribute('photos'), 'external_link' => 'https://example.com/gallery.jpg']);
});

test('invalid import rows and unsafe image URLs reject the whole file without writes', function (array $overrides) {
    createStoreAdmin();
    actingAsStoreUser(createStoreStaff('product_bulk_import'));
    $count = Product::count();
    $uploads = DB::table('uploads')->count();
    $stocks = DB::table('product_stocks')->count();
    $row = catalogueImportRow();
    $this->post('/temp/admin/bulk-product-upload', ['bulk_file' => catalogueImportFile([$row, [...$row, ...$overrides]])])->assertSessionHasErrors('bulk_file');
    expect(Product::count())->toBe($count)->and(DB::table('uploads')->count())->toBe($uploads)->and(DB::table('product_stocks')->count())->toBe($stocks);
})->with([
    [['unit_price' => -1]], [['current_stock' => -1]], [['category_id' => 999999]],
    [['thumbnail_img' => 'javascript:alert(1)']], [['photos' => 'https://example.com/a.jpg,javascript:alert(1)']],
]);

test('bulk import validates a required spreadsheet and maximum file size', function () {
    actingAsStoreUser(createStoreStaff('product_bulk_import'));
    $count = Product::count();
    $this->post('/temp/admin/bulk-product-upload')->assertSessionHasErrors('bulk_file');
    $this->post('/temp/admin/bulk-product-upload', ['bulk_file' => UploadedFile::fake()->create('huge.xlsx', 10241)])->assertSessionHasErrors('bulk_file');
    $this->post('/temp/admin/bulk-product-upload', ['bulk_file' => UploadedFile::fake()->create('script.php', 1)])->assertSessionHasErrors('bulk_file');
    expect(Product::count())->toBe($count);
});

test('bulk import admin endpoints refuse unauthorized callers', function (string $caller, string $uri) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get($uri), $caller);
})->with('refused store admin callers')->with([
    '/temp/admin/product-bulk-upload/index', '/temp/admin/product-bulk-export',
    '/temp/admin/bulk-upload/download/category', '/temp/admin/bulk-upload/download/brand', '/temp/admin/bulk-upload/download/seller',
]);

test('bulk import refuses unauthorized writes without products or uploads', function (string $caller) {
    $row = catalogueImportRow();
    $count = Product::count();
    $uploads = DB::table('uploads')->count();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/bulk-product-upload', ['bulk_file' => catalogueImportFile([$row])]), $caller);
    expect(Product::count())->toBe($count)->and(DB::table('uploads')->count())->toBe($uploads);
})->with('refused store admin callers');

test('authorized export keeps its spreadsheet download contract', function () {
    actingAsStoreUser(createStoreStaff('product_bulk_export'));
    Excel::fake();
    $this->get('/temp/admin/product-bulk-export')->assertOk();
    Excel::assertDownloaded('products.xlsx');
});

test('reference PDFs remain server rendered and preserve translated labels', function (string $resource, string $view, string $filename) {
    actingAsStoreUser(createStoreStaff('product_bulk_import'));
    if ($resource === 'category') {
        $category = Category::forceCreate(['name' => 'Food']);
        Category::forceCreate(['name' => 'Other food']);
        DB::table('category_translations')->insert(['category_id' => $category->id, 'lang' => 'en', 'name' => 'Translated category']);
        $expectedLabel = 'Translated category';
    } elseif ($resource === 'brand') {
        $brand = Brand::forceCreate(['name' => 'Fresh']);
        Brand::forceCreate(['name' => 'Other brand']);
        DB::table('brand_translations')->insert(['brand_id' => $brand->id, 'lang' => 'en', 'name' => 'Translated brand']);
        $expectedLabel = 'Translated brand';
    } else {
        createStoreUser('seller', 'seller-one@example.com');
        createStoreUser('seller', 'seller-two@example.com');
        $expectedLabel = 'seller-one@example.com';
    }
    $pdf = Mockery::mock();
    $pdf->shouldReceive('download')->once()->with($filename)->andReturn(response('PDF contents', 200, ['Content-Type' => 'application/pdf']));
    PDF::shouldReceive('loadView')->once()->withArgs(function (string $actualView, array $data) use ($view, $expectedLabel): bool {
        expect($actualView)->toBe($view)->and($data)->toHaveCount(1)
            ->and(array_values($data)[0])->toHaveCount(2)
            ->and(view($actualView, $data)->render())->toContain($expectedLabel);

        return true;
    })->andReturn($pdf);
    $this->get('/temp/admin/bulk-upload/download/'.$resource)->assertOk()->assertHeader('Content-Type', 'application/pdf');
})->with([
    ['category', 'shop.backend.downloads.category', 'category.pdf'],
    ['brand', 'shop.backend.downloads.brand', 'brands.pdf'],
    ['seller', 'shop.backend.downloads.user', 'user.pdf'],
]);

test('bulk import requires the existing administrator owner before any product stock or upload write', function () {
    actingAsStoreUser(createStoreStaff('product_bulk_import'));
    $row = catalogueImportRow();
    $this->post('/temp/admin/bulk-product-upload', ['bulk_file' => catalogueImportFile([$row])])
        ->assertSessionHasErrors(['bulk_file' => __('messages.store_product_import_admin_required')]);
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('product_stocks', 0);
    $this->assertDatabaseCount('uploads', 0);
});

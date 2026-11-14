<?php

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Setting;
use App\Services\ProductBarcodeLabels;
use Illuminate\Support\Facades\DB;

/**
 * Loads the product the way the storefront does: the barcodes relation and
 * the legacy `barcode` column.
 */
function productWithBarcodes(Product $product): Product
{
    return Product::query()->with('barcodes:id,product_id,barcode')->findOrFail($product->id);
}

test('merged lists the barcodes, oldest first, then the legacy column, once each, converted and without blanks', function () {
    $product = Product::factory()->create(['name' => 'Olive oil']);
    DB::table('products')->where('id', $product->id)->update(['barcode' => ' ٨٧١٢٣٤٥٦٧٨٩٠٦ ']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'WMS-ADDED']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '5901234123457']);
    // The same barcode as the one above once its Arabic digits are converted.
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '٥٩٠١٢٣٤١٢٣٤٥٧']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'STORE-ONLY']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '   ']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => 'متجر']);
    // Noise: another product's barcodes never appear.
    $other = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $other->id, 'barcode' => 'OTHER-PRODUCT']);

    expect((new ProductBarcodeLabels)->merged(productWithBarcodes($product))->all())->toBe([
        ['value' => 'WMS-ADDED', 'printable' => true],
        ['value' => '5901234123457', 'printable' => true],
        ['value' => 'STORE-ONLY', 'printable' => true],
        ['value' => 'متجر', 'printable' => false],
        ['value' => '8712345678906', 'printable' => true],
    ]);
});

test('a legacy column value already among the barcodes is listed once', function () {
    $product = Product::factory()->create();
    DB::table('products')->where('id', $product->id)->update(['barcode' => '8712345678906']);
    ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '8712345678906']);

    expect((new ProductBarcodeLabels)->merged(productWithBarcodes($product))->all())->toBe([
        ['value' => '8712345678906', 'printable' => true],
    ]);
});

test('merged is empty for a product with no barcodes and no legacy column value', function () {
    $product = Product::factory()->create();

    expect((new ProductBarcodeLabels)->merged(productWithBarcodes($product))->all())->toBe([]);
});

test('the label is the WMS label: the same SVG the WMS barcode preview serves, at the configured size', function () {
    actingAsAdmin();
    Setting::current()->update(['qr_code_width' => 600, 'qr_code_height' => 700]);
    $product = Product::factory()->create(['name' => 'Olive oil']);
    $barcode = ProductBarcode::factory()->create(['product_id' => $product->id, 'barcode' => '8712345678906']);
    $labels = new ProductBarcodeLabels;
    $svg = $labels->barcodeSvg($product, '8712345678906');

    expect($svg)->toBe($this->get("/admin/products/{$product->id}/barcodes/{$barcode->id}/preview")->getContent())
        ->and($svg)->toContain('width="600"')->toContain('>8712345678906</text>')->toContain('>Olive oil</text>')
        ->and($labels->productQrLabelImage($product, 600, 700))->toBe($labels->barcodeSvg($product, (string) $product->id))
        ->and($labels->dataUri($product, '8712345678906'))->toBe('data:image/svg+xml;base64,'.base64_encode($svg));
});

test('a value Code 128 cannot encode is not printable and has no data URI', function () {
    $product = Product::factory()->create();
    $labels = new ProductBarcodeLabels;

    expect($labels->isPrintable('EVOO-1 L'))->toBeTrue()
        ->and($labels->isPrintable(''))->toBeFalse()
        ->and($labels->isPrintable('٨٧١'))->toBeFalse()
        ->and($labels->isPrintable("A\tB"))->toBeFalse()
        ->and($labels->dataUri($product, 'متجر'))->toBeNull()
        ->and(fn () => $labels->barcodeSvg($product, 'متجر'))->toThrow(InvalidArgumentException::class);
});

test('the label escapes the value and the name it prints', function () {
    $product = Product::factory()->create(['name' => '<b>Oil</b> & "co"']);
    $svg = (new ProductBarcodeLabels)->barcodeSvg($product, '<A&B>');

    expect($svg)->not->toContain('<b>')->not->toContain('<A&B>')->toContain('&lt;A&amp;B&gt;')->toContain('&lt;b&gt;Oil&lt;/b&gt;');
});

test('the etag changes with the value, the name and the label size, and normalize converts Arabic digits and trims', function () {
    $product = Product::factory()->create(['name' => 'Olive oil']);
    $labels = new ProductBarcodeLabels;
    $etag = $labels->etag($product, '123');

    expect($labels->etag($product, '124'))->not->toBe($etag)
        ->and($labels->etag(tap($product)->forceFill(['name' => 'Other']), '123'))->not->toBe($etag)
        ->and((new ProductBarcodeLabels)->etag($product, '123'))->toBe($labels->etag($product, '123'))
        ->and($labels->normalize(' ١٢٣-AB '))->toBe('123-AB');
    Setting::current()->update(['qr_code_width' => 400]);
    expect((new ProductBarcodeLabels)->etag($product, '123'))->not->toBe($labels->etag($product, '123'));
});

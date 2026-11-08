<?php

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Rules\NewProductBarcode;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * NewProductBarcode: a value added to the one barcode table must be new
 * across every product and not a product id; a value the holder already
 * holds passes. The messages are the shared ones.
 *
 * @return array<string, list<string>>
 */
function newBarcodeErrors(mixed $value, ?Product $holder = null): array
{
    return Validator::make(['barcode' => $value], ['barcode' => [new NewProductBarcode($holder)]])->errors()->toArray();
}

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->other = Product::factory()->create();
    ProductBarcode::factory()->create(['product_id' => $this->other->id, 'barcode' => 'HELD-ELSEWHERE']);
    ProductBarcode::factory()->create(['product_id' => $this->product->id, 'barcode' => 'MINE']);
});

test('a value no product holds passes', function () {
    expect(newBarcodeErrors('BRAND-NEW'))->toBe([])
        ->and(newBarcodeErrors('BRAND-NEW', $this->product))->toBe([]);
});

test('a value another product holds is taken, whether the WMS or the store added it', function () {
    // A row the store form or the import wrote, straight into the table.
    DB::table('product_barcodes')->insert(['product_id' => $this->other->id, 'barcode' => 'STORE-ONLY']);

    expect(newBarcodeErrors('HELD-ELSEWHERE'))->toBe(['barcode' => [__('validation.unique', ['attribute' => 'barcode'])]])
        ->and(newBarcodeErrors('HELD-ELSEWHERE', $this->product))->toBe(['barcode' => [__('validation.unique', ['attribute' => 'barcode'])]])
        ->and(newBarcodeErrors('STORE-ONLY', $this->product))->toHaveKey('barcode');
});

test('a value the holder already holds passes, even one another product shares or equal to its own id', function () {
    ProductBarcode::factory()->create(['product_id' => $this->product->id, 'barcode' => 'HELD-ELSEWHERE']);
    // The import's own-id row.
    ProductBarcode::factory()->create(['product_id' => $this->product->id, 'barcode' => (string) $this->product->id]);

    expect(newBarcodeErrors('MINE', $this->product))->toBe([])
        ->and(newBarcodeErrors('HELD-ELSEWHERE', $this->product))->toBe([])
        ->and(newBarcodeErrors((string) $this->product->id, $this->product))->toBe([]);
});

test('without a holder, a value the product already has is taken too', function () {
    expect(newBarcodeErrors('MINE'))->toHaveKey('barcode');
});

test('an existing product\'s canonical id is refused, other digit strings are not', function () {
    $message = ['barcode' => [__('messages.barcode_is_product_id')]];

    expect(newBarcodeErrors((string) $this->other->id))->toBe($message)
        ->and(newBarcodeErrors((string) $this->other->id, $this->product))->toBe($message)
        // The product's own id is refused unless it already holds it as a barcode.
        ->and(newBarcodeErrors((string) $this->product->id, $this->product))->toBe($message)
        ->and(newBarcodeErrors('0'.$this->other->id))->toBe([])
        ->and(newBarcodeErrors($this->other->id.'.0'))->toBe([])
        ->and(newBarcodeErrors('999999999'))->toBe([]);
});

test('values are compared trimmed, and blanks and non-strings are left to the other rules', function () {
    ProductBarcode::factory()->create(['product_id' => $this->product->id, 'barcode' => ' PADDED ']);

    expect(newBarcodeErrors('  HELD-ELSEWHERE  '))->toHaveKey('barcode')
        ->and(newBarcodeErrors('PADDED', $this->product))->toBe([])
        ->and(newBarcodeErrors('   '))->toBe([])
        ->and(newBarcodeErrors(null))->toBe([])
        ->and(newBarcodeErrors(['x']))->toBe([]);
});

test('an integer value is checked like its string', function () {
    expect(newBarcodeErrors($this->other->id))->toBe(['barcode' => [__('messages.barcode_is_product_id')]]);
});

test('locked() runs the callback under the shared barcode lock and returns its result', function () {
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->with(5, Mockery::type(Closure::class))->andReturnUsing(fn (int $seconds, Closure $callback) => $callback());
    Cache::shouldReceive('lock')->once()->with('product-barcodes', 10)->andReturn($lock);

    expect(NewProductBarcode::locked(fn () => 'written'))->toBe('written');
});

test('while another writer holds the lock, no second writer gets it', function () {
    $held = Cache::lock(NewProductBarcode::LOCK, 10);
    expect($held->get())->toBeTrue();

    try {
        expect(Cache::lock(NewProductBarcode::LOCK, 10)->get())->toBeFalse();
    } finally {
        $held->release();
    }

    expect(NewProductBarcode::locked(fn () => 'after release'))->toBe('after release');
});

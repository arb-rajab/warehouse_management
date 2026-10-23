<?php

use App\Models\Product;
use Illuminate\Support\Facades\Http;

test('the owner-approved classification changes only missing origin markers and is idempotent', function () {
    $this->freezeSecond();
    $classifiedAt = now()->toDateTimeString();
    $products = Product::factory()->count(2)->sequence(
        ['id' => 100212, 'published' => false],
        ['published' => true],
    )->create();
    $existing = Product::factory()->feedOrigin()->create();
    $existingBefore = $existing->fresh()->getRawOriginal();
    $before = $products->mapWithKeys(fn (Product $product): array => [$product->id => $product->fresh()->getRawOriginal()]);
    $migration = require database_path('migrations/2026_10_23_125430_classify_existing_products_as_otajer_managed.php');

    $migration->up();

    foreach ($products as $product) {
        expect($product->fresh()->getRawOriginal())->toBe([
            ...$before[$product->id], 'synced_at' => $classifiedAt,
        ]);
    }
    expect($existing->fresh()->getRawOriginal())->toBe($existingBefore);

    $this->travel(1)->hour();
    $migration->up();
    $migration->down();

    foreach ($products as $product) {
        expect($product->fresh()->getRawOriginal('synced_at'))->toBe($classifiedAt);
    }
    expect($existing->fresh()->getRawOriginal())->toBe($existingBefore);
});

test('existing products resume feed updates while products created after classification stay protected', function () {
    $inactive = Product::factory()->create(['id' => 100212, 'published' => false]);
    $active = Product::factory()->create(['published' => true]);
    $migration = require database_path('migrations/2026_10_23_125430_classify_existing_products_as_otajer_managed.php');
    $migration->up();
    $local = Product::factory()->create(['published' => false]);
    $localBefore = $local->fresh()->getRawOriginal();
    config(['store.products_sync_url' => 'https://stores.otajer.com/test-feed']);
    Http::fake([
        'stores.otajer.com/*' => Http::response(['Result' => 'OK', 'Products' => [
            ['Mat_ID' => $inactive->id, 'enName' => 'SABAH KIKKERERWTEN GLAS 340 G', 'Active' => 1],
            ['Mat_ID' => $active->id, 'enName' => 'Now inactive', 'Active' => 0],
            ['Mat_ID' => $local->id, 'enName' => 'ID collision', 'Active' => 1],
        ]]),
    ]);

    $this->artisan('products:sync')->assertSuccessful();

    expect($inactive->fresh()->published)->toBeTrue()
        ->and($active->fresh()->published)->toBeFalse()
        ->and($local->fresh()->getRawOriginal())->toBe($localBefore);
});

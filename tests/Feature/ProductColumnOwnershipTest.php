<?php

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductBuilder;
use App\Models\Upload;
use App\Services\ProductFieldSourceService;
use App\Support\ProductColumnOwnership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

test('store product write paths cannot bypass the ownership builder', function () {
    $parser = (new ParserFactory)->createForHostVersion();
    $finder = new NodeFinder;
    $violations = [];
    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php' || $file->getRealPath() === app_path('Console/Commands/SyncProductsCommand.php')) {
            continue;
        }
        $nodes = $parser->parse($file->getContents());
        foreach ($finder->findInstanceOf($nodes, Node\Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || ! in_array($call->name->name, ['update', 'upsert', 'insert', 'insertGetId', 'insertOrIgnore', 'updateOrInsert', 'increment', 'decrement', 'incrementEach', 'decrementEach'], true)) {
                continue;
            }
            $root = $call->var;
            while ($root instanceof Node\Expr\MethodCall || $root instanceof Node\Expr\StaticCall) {
                $method = $root->name instanceof Node\Identifier ? $root->name->name : '';
                $argument = $root->args[0]->value ?? null;
                if (in_array($method, ['getQuery', 'toBase'], true)
                    || (in_array($method, ['table', 'from'], true) && $argument instanceof Node\Scalar\String_ && $argument->value === 'products')) {
                    $violations[] = $file->getRelativePathname().':'.$call->getStartLine();
                }
                $root = $root instanceof Node\Expr\MethodCall ? $root->var : null;
            }
        }
        foreach ($finder->findInstanceOf($nodes, Node\Expr\StaticCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && in_array($call->name->name, ['statement', 'unprepared', 'update', 'insert'], true)) {
                foreach ($finder->findInstanceOf($call->args, Node\Scalar\String_::class) as $sql) {
                    if (preg_match('/\b(?:UPDATE|INSERT\s+INTO|REPLACE\s+INTO)\s+[`"]?products\b/i', $sql->value)) {
                        $violations[] = $file->getRelativePathname().':'.$call->getStartLine();
                    }
                }
            }
        }
    }
    expect($violations)->toBe([]);
});

test('all store model writes use the guarded product builder', function () {
    expect(Product::query())->toBeInstanceOf(ProductBuilder::class)
        ->and(ProductColumnOwnership::syncColumns())->toBe([
            'name', 'ar_name', 'published', 'collection_id',
            'serial', 'unit_price', 'unit', 'api_unitId', 'api_unit_name', 'tax', 'description', 'unit_equal',
        ]);
});

test('store assignments cannot change feed-owned columns even from a partial or stale model', function (string $column, mixed $value) {
    $product = Product::factory()->create();
    $stale = Product::query()->select(['id', $column])->findOrFail($product->id);
    $product->getConnection()->table('products')->where('id', $product->id)->update(['synced_at' => now()]);
    $before = $product->fresh()->getRawOriginal();

    expect(fn () => $stale->forceFill([$column => $value])->saveQuietly())->toThrow(ValidationException::class);
    expect($product->fresh()->getRawOriginal())->toBe($before);
})->with([
    'name' => ['name', 'Changed'], 'ar_name' => ['ar_name', 'جديد'], 'published' => ['published', false],
    'serial' => ['serial', 'S-9'], 'unit_price' => ['unit_price', 42], 'unit' => ['unit', 'case'], 'api_unitId' => ['api_unitId', '7'],
    'api_unit_name' => ['api_unit_name', 'Case'], 'tax' => ['tax', 9], 'description' => ['description', 'Changed text'], 'unit_equal' => ['unit_equal', 12],
]);

test('bulk model writes refuse a mixed-origin set atomically', function () {
    $feed = Product::factory()->feedOrigin()->create(['name' => 'Feed']);
    $store = Product::factory()->create(['name' => 'Store']);

    expect(fn () => Product::query()->whereKey([$feed->id, $store->id])->update(['name' => 'Changed', 'unit_price' => 42]))
        ->toThrow(ValidationException::class);
    expect($feed->fresh()->name)->toBe('Feed')->and($store->fresh()->name)->toBe('Store');
});

test('the database write guard also refuses raw-attribute and quiet-save bypasses', function () {
    $product = Product::factory()->feedOrigin()->create();
    $name = $product->name;
    $product->setRawAttributes([...$product->getAttributes(), 'name' => 'Bypass']);

    expect(fn () => $product->saveQuietly())->toThrow(ValidationException::class);
    expect($product->fresh()->name)->toBe($name);
});

test('counter updates cannot bypass feed ownership', function (string $method, array $arguments) {
    $product = Product::factory()->feedOrigin()->create();
    expect(fn () => Product::query()->whereKey($product->id)->{$method}(...$arguments))->toThrow(ValidationException::class);
    expect($product->fresh()->published)->toBeTrue();
})->with([
    ['increment', ['published']], ['decrement', ['published']],
    ['incrementEach', [['published' => 1]]], ['decrementEach', [['published' => 1]]],
]);

test('store columns remain editable on feed rows and every column is editable on store rows', function () {
    $feed = Product::factory()->feedOrigin()->create();
    $store = Product::factory()->create();
    $feed->forceFill(['weight' => 12.50, 'tags' => 'store,tags', 'approved' => 0])->save();
    $store->forceFill([
        'name' => 'Own name', 'ar_name' => 'اسمي', 'published' => false, 'unit_price' => 7.25, 'description' => 'Own text', 'unit' => 'box', 'tax' => 5,
    ])->save();

    expect((float) $feed->fresh()->getAttribute('weight'))->toBe(12.50)
        ->and($feed->fresh()->getAttribute('tags'))->toBe('store,tags')
        ->and((float) $store->fresh()->getAttribute('unit_price'))->toBe(7.25)
        ->and($store->fresh()->description)->toBe('Own text')
        ->and($store->fresh()->name)->toBe('Own name')->and($store->fresh()->published)->toBeFalse()
        ->and($store->fresh()->getAttribute('synced_at'))->toBeNull();
});

test('duplication creates a store-origin row even for a quiet replica of a feed product', function () {
    $source = Product::factory()->feedOrigin()->create();
    $copy = $source->replicate();
    $copy->saveQuietly();
    $copy->name = 'Store copy';
    $copy->save();

    expect($copy->fresh()->getAttribute('synced_at'))->toBeNull()
        ->and($copy->fresh()->name)->toBe('Store copy')
        ->and($source->fresh()->getAttribute('synced_at'))->not->toBeNull();
});

test('store updates cannot reclassify products or use unguarded upserts', function () {
    $product = Product::factory()->feedOrigin()->create();
    expect(fn () => Product::query()->whereKey($product->id)->update(['synced_at' => null]))->toThrow(ValidationException::class);
    expect(fn () => Product::query()->upsert([['id' => $product->id, 'name' => 'Bypass']], ['id']))->toThrow(ValidationException::class);
    expect($product->fresh()->getAttribute('synced_at'))->not->toBeNull();
});

test('the existing seller physical and digital writers refuse feed names before changing stocks', function (bool $digital) {
    config(['app.default_language' => 'en']);
    $seller = createStoreUser('seller');
    actingAsStoreUser($seller);
    $product = createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller', 'digital' => (int) $digital]);
    $product->getConnection()->table('products')->where('id', $product->id)->update(['synced_at' => now()]);
    $before = $product->fresh()->getRawOriginal();
    $stocks = $product->stocks()->get()->toJson();
    $input = [
        'lang' => 'en', 'name' => 'Changed', 'slug' => 'changed', 'category_id' => 1, 'unit' => 'box',
        'min_qty' => 1, 'unit_price' => 20, 'tags' => [null], 'date_range' => null, 'meta_title' => null,
        'meta_description' => null, 'meta_img' => null, 'thumbnail_img' => null, 'description' => 'Text',
        'button' => 'publish',
    ];

    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    if (! $digital) {
        $category = Category::forceCreate(['name' => 'Physical', 'slug' => 'physical', 'digital' => 0]);
        unset($input['button']);
        $input += ['discount' => 1, 'discount_type' => 'amount', 'current_stock' => 4, 'stock_visibility_state' => 'quantity'];
        $input['category_id'] = $category->id;
    }
    if ($digital) {
        $category = Category::forceCreate(['name' => 'Digital', 'slug' => 'digital', 'digital' => 1, 'parent_id' => 0]);
        $file = Upload::factory()->create(['user_id' => $seller->id, 'type' => 'document']);
        unset($input['unit'], $input['min_qty'], $input['date_range'], $input['button']);
        $input = [...$input, 'category_id' => $category->id, 'purchase_price' => 0, 'discount' => 0, 'discount_type' => 'amount', 'file' => $file->id];
    }
    $route = $digital ? 'seller.digitalproducts.update' : 'seller.products.update';
    $this->post(route($route, $product->id), $input)->assertSessionHasErrors('name');

    expect($product->fresh()->getRawOriginal())->toBe($before)
        ->and($product->stocks()->get()->toJson())->toBe($stocks);
})->with(['physical' => false, 'digital' => true]);

test('an admin-owned synced column is the store\'s to write even on a feed-origin row', function (string $column, mixed $value) {
    app(ProductFieldSourceService::class)->update([$column => 'admin']);
    $feed = Product::factory()->feedOrigin()->create();

    $feed->forceFill([$column => $value])->save();

    expect($feed->fresh()->getAttribute($column))->toEqual($value)
        ->and($feed->fresh()->getAttribute('synced_at'))->not->toBeNull();
})->with([
    'name' => ['name', 'Admin name'], 'ar_name' => ['ar_name', 'اسم الإدارة'], 'published' => ['published', false],
    'serial' => ['serial', 'S-9'], 'unit_price' => ['unit_price', 42], 'unit' => ['unit', 'case'], 'api_unitId' => ['api_unitId', '7'],
    'api_unit_name' => ['api_unit_name', 'Case'], 'tax' => ['tax', 9], 'description' => ['description', 'Admin text'], 'unit_equal' => ['unit_equal', 12],
]);

test('a numeric feed column re-assigned the number it holds, however spelled, is no change on a feed row', function (string $column, mixed $stored, mixed $posted) {
    $feed = Product::factory()->feedOrigin()->create([$column => $stored]);
    $noise = Product::factory()->feedOrigin()->create([$column => $stored]);
    $before = $feed->fresh()->getRawOriginal($column);

    $feed->forceFill([$column => $posted, 'weight' => 3])->save();

    expect($feed->fresh()->getRawOriginal($column))->toBe($before)
        ->and((float) $feed->fresh()->getAttribute('weight'))->toBe(3.0)
        ->and($noise->fresh()->getRawOriginal($column))->toBe($before);
})->with([
    'unit_price 12.5 as 12.50' => ['unit_price', 12.5, '12.50'],
    'unit_price 12.50 as 12.5' => ['unit_price', '12.50', 12.5],
    'tax 5 as 5.00' => ['tax', 5, '5.00'],
]);

test('a different number on a feed row is still refused after the same-number allowance', function () {
    $feed = Product::factory()->feedOrigin()->create(['unit_price' => 12.5]);

    expect(fn () => $feed->forceFill(['unit_price' => '12.51'])->save())->toThrow(ValidationException::class);
    expect((float) $feed->fresh()->getAttribute('unit_price'))->toBe(12.5);
});

test('the synced price, unit, tax and description are refused to bulk writes on a feed row while Otajer-owned', function () {
    $feed = Product::factory()->feedOrigin()->create();
    $store = Product::factory()->create();
    $before = $feed->fresh();

    foreach (['unit_price' => 5, 'tax' => 5, 'unit' => 'x', 'description' => 'y', 'serial' => 'z', 'unit_equal' => 3, 'api_unitId' => '1', 'api_unit_name' => 'n'] as $column => $value) {
        expect(fn () => Product::query()->whereKey($feed->id)->update([$column => $value]), $column)->toThrow(ValidationException::class);
    }
    // A store-origin row takes the same write, and a feed row an unrelated column.
    Product::query()->whereKey($store->id)->update(['unit_price' => 5, 'description' => 'y']);
    Product::query()->whereKey($feed->id)->update(['weight' => 2]);

    expect($feed->fresh())->unit_price->toEqual($before->unit_price)->tax->toEqual($before->tax)->description->toBe($before->description)
        ->and((float) $feed->fresh()->getAttribute('weight'))->toBe(2.0)
        ->and((float) $store->fresh()->getAttribute('unit_price'))->toBe(5.0);
});

test('making one field admin-owned leaves the other Otajer-owned fields guarded', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin']);
    $feed = Product::factory()->feedOrigin()->create(['ar_name' => 'قديم', 'published' => true]);

    expect(fn () => $feed->forceFill(['ar_name' => 'جديد'])->save())->toThrow(ValidationException::class);
    expect(fn () => Product::query()->whereKey($feed->id)->update(['published' => false]))->toThrow(ValidationException::class);
    expect($feed->fresh())->ar_name->toBe('قديم')->published->toBeTrue();
});

test('a bulk write of an admin-owned column passes on a mixed-origin set', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin']);
    $feed = Product::factory()->feedOrigin()->create(['name' => 'Feed']);
    $store = Product::factory()->create(['name' => 'Store']);
    $noise = Product::factory()->feedOrigin()->create(['name' => 'Noise']);

    Product::query()->whereKey([$feed->id, $store->id])->update(['name' => 'Renamed']);

    expect($feed->fresh()->name)->toBe('Renamed')->and($store->fresh()->name)->toBe('Renamed')
        ->and($noise->fresh()->name)->toBe('Noise');
});

test('an unreadable increment column stays refused whatever the field owners are', function () {
    app(ProductFieldSourceService::class)->update(['name' => 'admin', 'ar_name' => 'admin', 'published' => 'admin']);
    $product = Product::factory()->feedOrigin()->create();

    expect(fn () => Product::query()->whereKey($product->id)->increment(DB::raw('num_of_sale')))->toThrow(ValidationException::class);
});

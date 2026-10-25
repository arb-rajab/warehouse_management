<?php

use Illuminate\Support\Facades\File;

/**
 * This inventory includes guarded and commented builders, so re-enabling old
 * markup cannot silently reintroduce direct slug URL generation. The explicit
 * file:line list is a ratchet: remove an entry when migrating that call.
 *
 * @return list<int>
 */
function rawStorefrontRouteLines(string $source): array
{
    preg_match_all('/\b(?:route|to_route)\s*\(\s*([\'"])(?:product|products\.category|products\.brand)\1\s*,|\bpublicProduct\.url\s*\(\s*row\.slug\s*\)/', $source, $matches, PREG_OFFSET_CAPTURE);

    return array_map(fn (array $match): int => substr_count(substr($source, 0, $match[1]), "\n") + 1, $matches[0]);
}

test('storefront route builders use the shared helper except for the exact D15 remainder', function () {
    $actual = [];
    foreach (['app', 'resources', 'public/assets/js'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            if (! in_array($file->getExtension(), ['php', 'js', 'ts', 'vue'], true)) {
                continue;
            }
            foreach (rawStorefrontRouteLines($file->getContents()) as $line) {
                $actual[] = $directory.'/'.$file->getRelativePathname().':'.$line;
            }
        }
    }
    $remaining = [];
    expect($remaining)->toBe([], 'D15 is complete: the storefront builder allowlist must stay empty.');
    sort($actual);
    sort($remaining);

    expect($actual)->toBe($remaining, 'New direct builder, or fixed call still in the D15 remainder. Migrate with StorefrontUrl and shrink this list.');
});

test('the route guard detects multiline PHP, array parameters, resources and client-side builders', function () {
    expect(rawStorefrontRouteLines(<<<'SOURCE'
route('product', $product->slug)
route(
    "products.category",
    ['category_slug' => $category->slug]
)
URL::route('products.brand', $brand->getAttribute('slug'))
route('product', ['slug' => $slug])
publicProduct.url(row.slug)
StorefrontUrl::to($product)
route('product.edit', $product->id)
$this->route('product')
SOURCE))->toBe([1, 2, 6, 7, 8]);
});

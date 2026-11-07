import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { expectStrictPolicy, php, watchCsp } from './support/editor';
import { seedStoreFixtures, storedProduct } from './support/storeFixtures';

/**
 * A product's barcodes are drawn as the WMS barcode label on the back office
 * edit form (an image route under the strict CSP) and on the storefront
 * product page (inline data URIs under the Blade CSP). Pest covers the
 * payloads and the SVG; this covers that a real browser loads and draws them.
 */

const name = 'Browser barcode oil';

/** A simple published product with one store and one WMS barcode. */
function seedBarcodeProduct(): void {
    seedStoreFixtures(String.raw`
        foreach (App\Models\Product::where('name', ${php(name)})->pluck('id') as $id) {
            foreach (['product_translations', 'product_stocks', 'product_barcodes', 'wms_product_barcodes'] as $table) {
                Illuminate\Support\Facades\DB::table($table)->where('product_id', $id)->delete();
            }
            App\Models\Product::whereKey($id)->delete();
        }
        App\Models\Category::where('slug', 'browser-barcode-pantry')->delete();
        $category = App\Models\Category::forceCreate(['name' => 'Browser barcode pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'browser-barcode-pantry', 'digital' => 0]);
        $owner = App\Models\StoreUser::query()->firstOrNew(['email' => 'browser-product-owner@example.com']);
        $owner->forceFill(['name' => 'Browser product owner', 'user_type' => 'admin', 'password' => bcrypt(Illuminate\Support\Str::random(16))])->save();
        $product = App\Models\Product::forceCreate([
            'name' => ${php(name)}, 'ar_name' => '', 'slug' => 'browser-barcode-oil', 'added_by' => 'admin', 'user_id' => $owner->id, 'digital' => 0,
            'category_id' => $category->id, 'unit' => 'bottle', 'unit_price' => 12.5, 'min_qty' => 1, 'max_qty' => 10, 'discount' => 0,
            'discount_type' => 'amount', 'tags' => '', 'stock_visibility_state' => 'quantity', 'published' => 1, 'approved' => 1,
            'variant_product' => 0, 'colors' => '[]', 'choice_options' => '[]', 'attributes' => '[]',
        ]);
        Illuminate\Support\Facades\DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => '', 'price' => 12.5, 'sku' => 'BR-BC', 'qty' => 4]);
        Illuminate\Support\Facades\DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'en', 'name' => ${php(name)}, 'unit' => 'bottle', 'description' => '<p>Pressed</p>']);
        App\Models\StoreProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '8712345678906']);
        App\Models\ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => 'BROWSER-WMS-1']);
    `);
}

async function expectDrawn(image: Locator): Promise<void> {
    await expect(image).toBeVisible();
    expect(
        await image.evaluate(
            (element) => (element as HTMLImageElement).naturalWidth,
        ),
    ).toBeGreaterThan(0);
}

async function violations(page: Page): Promise<string[]> {
    return page.evaluate(
        () =>
            (window as unknown as { cspViolations?: string[] }).cspViolations ??
            [],
    );
}

test('the back office edit form and the storefront page draw the barcodes as barcode images', async ({
    page,
}) => {
    seedBarcodeProduct();
    const id = storedProduct(name, '$product->id') as number;
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    const external: string[] = [];
    page.on('request', (request) => {
        if (
            !new URL(request.url()).hostname.match(/^(localhost|127\.0\.0\.1)$/)
        ) {
            external.push(request.url());
        }
    });

    const image = page.waitForResponse((response) =>
        /\/temp\/admin\/products\/\d+\/store-barcodes\/\d+\/image$/.test(
            new URL(response.url()).pathname,
        ),
    );
    await expectStrictPolicy(page, `/temp/admin/products/admin/${id}/edit`);
    const response = await image;
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('image/svg+xml');
    await expectDrawn(
        page.locator('#product_barcodes img[alt="8712345678906"]'),
    );

    // A new value says the image comes with the save, and has none yet.
    await page
        .getByRole('button', { name: 'Add barcode', exact: true })
        .click();
    await page
        .getByLabel('Product barcodes 2', { exact: true })
        .fill('5901234123457');
    await expect(
        page.getByText('The barcode image appears once the product is saved.'),
    ).toBeVisible();
    await expect(page.locator('#product_barcodes img')).toHaveCount(1);
    expect(await violations(page)).toEqual([]);

    await page.goto('/temp/product/browser-barcode-oil');

    for (const value of ['BROWSER-WMS-1', '8712345678906']) {
        const drawn = page.locator(`img[alt="${value}"]`);
        await expectDrawn(drawn);
        expect(await drawn.getAttribute('src')).toMatch(
            /^data:image\/svg\+xml;base64,/,
        );
    }

    expect(await violations(page)).toEqual([]);
    expect(external.filter((url) => url.includes('barcode'))).toEqual([]);
    expect(reports.filter((report) => /barcode|img-src/i.test(report))).toEqual(
        [],
    );
});

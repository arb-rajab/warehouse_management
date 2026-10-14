import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import {
    documentViolations,
    expectStrictPolicy,
    php,
    watchCsp,
} from './support/editor';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
    storedProduct,
} from './support/storeFixtures';

/**
 * The physical product edit form is Inertia, on the shared editor and the
 * structured variant editor (.ai/rules/store-backoffice-migration.md §23),
 * under the strict CSP. Pest covers the writes; this covers what only the
 * browser shows: the real form posting the stored rows back unchanged, and
 * the language tabs as Inertia visits.
 */

const name = 'Browser edit oil';
const stocks =
    "Illuminate\\Support\\Facades\\DB::table('product_stocks')->where('product_id', $product->id)->orderBy('id')->get()->map(fn ($s) => [$s->id, $s->variant, (float) $s->price, $s->sku, (int) $s->qty])->all()";

/**
 * A variant product with two stored stock rows and an Arabic translation, an
 * attribute with its two values and a physical category; nothing left from
 * an earlier run.
 */
function seedVariantProduct(): void {
    seedStoreFixtures(String.raw`
        foreach (App\Models\Product::where('name', ${php(name)})->pluck('id') as $id) {
            foreach (['product_translations', 'product_stocks', 'product_taxes', 'product_variations', 'product_barcodes', 'flash_deal_products', 'wms_product_settings'] as $table) {
                Illuminate\Support\Facades\DB::table($table)->where('product_id', $id)->delete();
            }
            App\Models\Product::whereKey($id)->delete();
        }
        App\Models\Category::where('slug', 'browser-edit-pantry')->delete();
        $category = App\Models\Category::forceCreate(['name' => 'Browser edit pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'browser-edit-pantry', 'digital' => 0]);
        $size = Illuminate\Support\Facades\DB::table('attributes')->where('name', 'Browser edit size')->value('id')
            ?? Illuminate\Support\Facades\DB::table('attributes')->insertGetId(['name' => 'Browser edit size']);
        Illuminate\Support\Facades\DB::table('attribute_values')->where('attribute_id', $size)->delete();
        Illuminate\Support\Facades\DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'Small'], ['attribute_id' => $size, 'value' => 'Large']]);
        $product = App\Models\Product::forceCreate([
            'name' => ${php(name)}, 'ar_name' => '', 'slug' => 'browser-edit-oil', 'added_by' => 'admin', 'digital' => 0, 'category_id' => $category->id,
            'unit' => 'bottle', 'unit_price' => 12.5, 'min_qty' => 1, 'max_qty' => 10, 'discount' => 0, 'discount_type' => 'amount', 'tags' => '',
            'stock_visibility_state' => 'quantity', 'published' => 1, 'approved' => 1, 'variant_product' => 1, 'colors' => '[]',
            'choice_options' => json_encode([['attribute_id' => (string) $size, 'values' => ['Small', 'Large']]]), 'attributes' => json_encode([(string) $size]),
        ]);
        Illuminate\Support\Facades\DB::table('product_stocks')->insert([
            ['product_id' => $product->id, 'variant' => 'Small', 'price' => 13.5, 'sku' => 'BR-S', 'qty' => 4],
            ['product_id' => $product->id, 'variant' => 'Large', 'price' => 15.25, 'sku' => null, 'qty' => 0],
        ]);
        Illuminate\Support\Facades\DB::table('product_translations')->insert([
            ['product_id' => $product->id, 'lang' => 'en', 'name' => ${php(name)}, 'unit' => 'bottle', 'description' => '<p>Pressed</p>'],
            ['product_id' => $product->id, 'lang' => 'ar', 'name' => 'زيت المتصفح', 'unit' => 'زجاجة', 'description' => '<p>معصور</p>'],
        ]);
    `);
}

test('the stored variant rows post back unchanged, and a language tab edits its own translation', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedVariantProduct();
    const id = storedProduct(name, '$product->id') as number;
    const before = storedProduct(name, stocks) as unknown[][];
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, `/temp/admin/products/admin/${id}/edit`);
    await expect(
        page.getByRole('heading', { name: 'Edit product', exact: true }),
    ).toBeVisible();
    await expect(page.locator('tbody th[scope="row"] span')).toHaveText([
        'Small',
        'Large',
    ]);
    await expect(page.locator('input[name="variants[0][price]"]')).toHaveValue(
        '13.5',
    );
    await expect(page.locator('input[name="variants[0][sku]"]')).toHaveValue(
        'BR-S',
    );
    await page.locator('input[name="variants[1][qty]"]').fill('3');

    const saved = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === `/temp/admin/products/update/${id}` &&
            r.request().method() === 'POST',
    );
    await page
        .getByRole('button', { name: 'Update product', exact: true })
        .click();
    expect([302, 303]).toContain((await saved).status());
    await expect(page).toHaveURL(
        new RegExp(`/temp/admin/products/admin/${id}/edit\\?lang=en$`),
    );
    // The same rows, ids included; only the typed quantity changed.
    expect(storedProduct(name, stocks)).toEqual([
        before[0],
        [before[1][0], 'Large', 15.25, null, 3],
    ]);

    const tab = page.waitForResponse(
        (r) =>
            new URL(r.url()).search === '?lang=ar' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await page.getByRole('link', { name: 'Arabic', exact: true }).click();
    expect((await tab).status()).toBe(200);
    await expect(page.locator('#name')).toHaveValue('زيت المتصفح');
    await page.locator('#name').fill('زيت المتصفح البكر');
    await page
        .getByRole('button', { name: 'Update product', exact: true })
        .click();
    await expect(page).toHaveURL(
        new RegExp(`/temp/admin/products/admin/${id}/edit\\?lang=ar$`),
    );
    const names =
        "[$product->name, $product->product_translations()->where('lang', 'ar')->value('name')]";
    expect(storedProduct(name, names)).toEqual([name, 'زيت المتصفح البكر']);

    expect(await documentViolations(page)).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

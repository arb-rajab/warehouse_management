import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

const pdf =
    '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n';

test('PDF upload and native delete confirmation coexist with the public download', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/offers/index');
    await page.locator('#catalog').evaluate((input, bytes) => {
        const transfer = new DataTransfer();
        transfer.items.add(
            new File([bytes], 'Offers.pdf', { type: 'application/pdf' }),
        );
        (input as HTMLInputElement).files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, pdf);
    await page
        .getByRole('button', { name: 'Upload catalogue', exact: true })
        .click();
    await expect(
        page.getByRole('link', { name: 'Download PDF', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#catalog')).toHaveCount(0);
    const download = await page.request.get('/temp/offers/download');
    expect(download.ok()).toBe(true);
    expect(download.headers()['x-content-type-options']).toBe('nosniff');
    expect(new TextDecoder().decode(await download.body())).toBe(pdf);

    page.once('dialog', (dialog) => dialog.dismiss());
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(
        page.getByRole('link', { name: 'Download PDF', exact: true }),
    ).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.locator('#catalog')).toBeVisible();
    await expect(
        page.getByText('No catalogue uploaded.', { exact: true }),
    ).toBeVisible();
});

test('order configuration renders hostile search results as text', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    const name = '<img src=x onerror="window.catalogueXss=true">';
    const matId = 'MAT-\"><img src=x onerror="window.catalogueXss=true">';
    await page.route('**/temp/admin/product/search?*', (route) =>
        route.fulfill({ json: [{ id: 1, name, mat_id: matId }] }),
    );
    await page.goto('/temp/admin/order-configuration');
    await page.locator('#default_specific_product').fill('MAT-');
    const result = page.getByRole('button', {
        name: `${name} (${matId})`,
        exact: true,
    });
    await expect(result).toHaveText(`${name} (${matId})`);
    await expect(result.locator('img')).toHaveCount(0);
    await result.click();
    await expect(page.locator('#default_specific_product')).toHaveValue(matId);
    await expect(
        page.getByText(`Selected product: ${name}`, { exact: true }),
    ).toBeVisible();
    expect(await page.evaluate(() => 'catalogueXss' in window)).toBe(false);
});

test('product attribute choice JSON creates literal values and titles without injected elements', async ({
    page,
}) => {
    // The choice script's last consumer, the Blade edit form, is now the
    // Inertia edit form (.ai/rules/store-backoffice-migration.md §23): the
    // attribute title comes from the server and the values from the JSON
    // endpoint, both rendered as text.
    const value = '"><img src=x onerror="window.catalogueXss=true">';
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('product_translations')->where('product_id', 990001)->delete();
        Illuminate\Support\Facades\DB::table('product_stocks')->where('product_id', 990001)->delete();
        App\Models\Product::whereKey(990001)->delete();
        App\Models\Product::forceCreate([
            'id'=>990001,'name'=>'Browser choice oil','ar_name'=>'','slug'=>'browser-choice-oil','added_by'=>'admin','digital'=>0,
            'unit'=>'bottle','unit_price'=>3,'colors'=>'[]','attributes'=>'[]','choice_options'=>'[]','tags'=>'',
        ]);
        Illuminate\Support\Facades\DB::table('attributes')->where('name', 'like', '%catalogueXss%')->delete();
        Illuminate\Support\Facades\DB::table('attributes')->insert(['name' => ${php(value)}]);
    `);
    await loginAsStoreAdmin(page);
    await page.route('**/temp/admin/products/add-more-choice-option', (route) =>
        route.fulfill({ json: [{ id: 1, value }] }),
    );
    await page.goto('/temp/admin/products/admin/990001/edit?lang=en');
    await page.getByLabel(value, { exact: true }).check();

    const choice = page.locator('fieldset[data-attribute]');
    await expect(choice.locator('legend')).toHaveText(`Values of ${value}`);
    const option = choice.getByLabel(value, { exact: true });
    await expect(option).toHaveAttribute('value', value);
    await expect(option).toHaveAttribute('name', 'choice_options[0][values][]');
    await expect(choice.locator('img')).toHaveCount(0);
    expect(await page.evaluate(() => 'catalogueXss' in window)).toBe(false);
});

import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { documentViolations, watchCsp } from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('classified publishing and native delete confirmation preserve the list under strict CSP', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'classified_product'], ['value' => '1']);
        $product = App\Models\CustomerProduct::query()->where('slug', 'browser-classified')->first() ?? new App\Models\CustomerProduct;
        $product->forceFill(['slug' => 'browser-classified', 'name' => 'Browser classified bicycle', 'added_by' => 'customer', 'status' => 1, 'published' => 0])->save();
        App\Models\CustomerProductTranslation::query()->updateOrCreate(['customer_product_id' => $product->id, 'lang' => 'en'], ['name' => 'Browser classified bicycle']);
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/staffs');
    const reports = await watchCsp(page);
    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
    });
    await navigation
        .getByRole('button', { name: 'Customers', exact: true })
        .click();
    const visit = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname ===
                '/temp/admin/classified_products' &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await navigation
        .getByRole('link', { name: 'Classified products', exact: true })
        .click();
    expect((await visit).headers()['x-inertia']).toBe('true');
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Classified products', exact: true }),
    ).toBeVisible();
    const response = await page.goto(
        '/temp/admin/classified_products?per_page=10',
    );
    expect(response?.headers()['content-security-policy']).toContain(
        "script-src 'self';",
    );
    const row = page.getByRole('row', { name: /Browser classified bicycle/ });
    const toggle = row.getByRole('switch');
    await expect(toggle).not.toBeChecked();
    await toggle.click();
    await expect(toggle).toBeChecked();
    await toggle.click();
    await expect(toggle).not.toBeChecked();
    await expect(page).toHaveURL(/per_page=10/);
    const links = row.locator('a[target="_blank"]');
    await expect(links).toHaveCount(2);
    await expect(links.first()).toHaveAttribute('rel', 'noopener');
    const remove = row.getByRole('button', { name: 'Delete', exact: true });
    page.once('dialog', (dialog) => dialog.dismiss());
    await remove.click();
    await expect(row).toBeVisible();
    seedStoreFixtures(String.raw`
        $product = App\Models\CustomerProduct::query()->where('slug', 'browser-classified')->firstOrFail();
        if ($product->published != 0 || $product->status != 1) { throw new RuntimeException('Classified flags changed unexpectedly'); }
    `);
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(row.getByRole('switch')).toHaveAttribute(
        'aria-checked',
        'false',
    );
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'حذف', exact: true }).click();
    await expect(row).toHaveCount(0);
    await expect(page).toHaveURL(/per_page=10/);
    seedStoreFixtures(String.raw`
        if (App\Models\CustomerProduct::query()->where('slug', 'browser-classified')->exists()) { throw new RuntimeException('Classified delete did not persist'); }
    `);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);
});

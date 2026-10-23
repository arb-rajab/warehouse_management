import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { colorsOf, documentViolations, watchCsp } from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('seller product native confirmation works in the Inertia list under RTL dark mode and strict CSP', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        foreach (['vendor_system_activation', 'product_approve_by_admin'] as $type) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => $type], ['value' => '1']);
        }
        $seller = App\Models\StoreUser::query()->where('email', 'browser-seller@example.com')->firstOrFail();
        $product = App\Models\Product::forceCreate([
            'name' => 'Browser seller confirmation', 'ar_name' => '', 'slug' => 'browser-seller-confirmation',
            'added_by' => 'seller', 'user_id' => $seller->id, 'digital' => 0, 'published' => 0,
            'for_sale' => 0, 'featured' => 0, 'approved' => 0, 'unit_price' => 5,
        ]);
        Illuminate\Support\Facades\DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => '', 'qty' => 3, 'price' => 5]);
    `);
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    const menu = page.getByRole('navigation', { name: 'Store admin menu' });
    await menu.getByRole('button', { name: 'Products', exact: true }).click();
    const visit = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname ===
                '/temp/admin/products/seller' &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await menu
        .getByRole('link', { name: 'Seller products', exact: true })
        .click();
    expect((await visit).headers()['x-inertia']).toBe('true');
    const response = await page.goto(
        '/temp/admin/products/seller?search=Browser+seller+confirmation&per_page=10',
    );
    expect(response?.headers()['content-security-policy']).toContain(
        "script-src 'self';",
    );
    const row = page.getByRole('row', { name: /Browser seller confirmation/ });
    await expect(row).toBeVisible();
    await row
        .getByRole('button', { name: 'Enable Approved', exact: true })
        .click();
    await expect(
        row.getByRole('button', { name: 'Disable Approved', exact: true }),
    ).toBeVisible();
    await expect(page).toHaveURL(/per_page=10/);
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    seedStoreFixtures(String.raw`
        $product = App\Models\Product::query()->where('slug', 'browser-seller-confirmation')->firstOrFail();
        if ($product->approved != 1 || $product->published != 0) { throw new RuntimeException('Approval changed another flag'); }
    `);
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    const colors = await colorsOf(page, '#approved');
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'حذف', exact: true }).click();
    await expect(row).toHaveCount(0);
    await expect(page).toHaveURL(/per_page=10/);
    seedStoreFixtures(String.raw`
        if (App\Models\Product::query()->where('slug', 'browser-seller-confirmation')->exists()) { throw new RuntimeException('Seller deletion did not persist'); }
    `);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);
});

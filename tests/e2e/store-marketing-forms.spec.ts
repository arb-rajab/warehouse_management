import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('category deletion asks for native confirmation and retains the row when dismissed', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/blog-category/create');
    await page.locator('#category_name').fill('Marketing deletion candidate');
    await page.getByRole('button', { name: 'Create category' }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/blog-category$/);
    const row = page.getByRole('row', { name: /Marketing deletion candidate/ });
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
});

test('coupon native dates use dark controls and retain state after server validation', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    const code = 'marketing-e2e-duplicate';
    const fillCoupon = async () => {
        await page.locator('#code').fill(code);
        await page.locator('#discount').fill('12');
        await page.locator('#start_date').fill('2026-10-09');
        await page.locator('#end_date').fill('2026-10-30');
        await page.locator('#min_buy').fill('30');
        await page.locator('#max_discount').fill('20');
    };
    await page.goto('/temp/admin/coupon/create');
    await expect(page.locator('#start_date')).toHaveCSS('color-scheme', 'dark');
    await expect(page.locator('#end_date')).toHaveCSS('color-scheme', 'dark');
    await fillCoupon();
    await page.getByRole('button', { name: 'Create coupon' }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/coupon$/);
    await page.goto('/temp/admin/coupon/create');
    await fillCoupon();
    await page.getByRole('button', { name: 'Create coupon' }).click();
    await expect(
        page.getByText('The code has already been taken.'),
    ).toBeVisible();
    await expect(page.locator('#code')).toHaveValue(code);
    await expect(page.locator('#discount')).toHaveValue('12');
    await expect(page.locator('#end_date')).toHaveValue('2026-10-30');
    await expect(page.locator('#min_buy')).toHaveValue('30');
});

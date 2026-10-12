import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { switchLocale } from './support/nav';
import { ensureStoreRtlLanguage } from './support/storeFixtures';

test('reference pagination arrows follow English and Arabic direction', async ({
    page,
}) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1920, height: 1080 });
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/tax?per_page=10');

    // Populate a second page through the public admin form, without database access.
    for (let index = 0; index < 11; index++) {
        await page.locator('#name').fill(`Pagination tax ${index}`);
        await page
            .getByRole('button', { name: 'Add tax', exact: true })
            .click();
        await expect(page.locator('#name')).toHaveValue('');
    }

    await page.goto('/temp/admin/tax?per_page=10');
    const arrow = page.locator('main nav svg.lucide-chevron-right').first();
    await expect(arrow).toBeVisible();
    await expect(arrow).toHaveCSS('rotate', 'none');
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(arrow).toHaveCSS('rotate', '180deg');
    await expect(
        page.getByRole('heading', { name: 'الضرائب', exact: true }),
    ).toBeVisible();
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(arrow).toHaveCSS('rotate', 'none');
});

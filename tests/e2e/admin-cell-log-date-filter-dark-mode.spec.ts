import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the cell log date filters use a dark native picker in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });

    await loginAsAdmin(page);
    await page.goto('/admin/cell-logs');
    await page.getByRole('button', { name: 'Filters' }).click();

    const dateFrom = page.locator('#filter-date-from');
    const dateTo = page.locator('#filter-date-to');
    const expirationDateFrom = page.locator('#filter-expiration-date-from');
    const expirationDateTo = page.locator('#filter-expiration-date-to');

    await expect(dateFrom).toHaveCSS('color-scheme', 'dark');
    await expect(dateTo).toHaveCSS('color-scheme', 'dark');
    await expect(expirationDateFrom).toHaveCSS('color-scheme', 'dark');
    await expect(expirationDateTo).toHaveCSS('color-scheme', 'dark');
});

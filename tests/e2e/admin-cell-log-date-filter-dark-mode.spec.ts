import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the cell log date filters use a dark native picker in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });

    await loginAsAdmin(page);
    await page.goto('/admin/cell-logs');

    const dateFrom = page.locator('#filter-date-from');
    const dateTo = page.locator('#filter-date-to');

    await expect(dateFrom).toHaveCSS('color-scheme', 'dark');
    await expect(dateTo).toHaveCSS('color-scheme', 'dark');
});

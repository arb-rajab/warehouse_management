import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the cell log transfer arrow flips direction in RTL layouts', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await page.goto('/admin/cell-logs?action=transferred_out');

    const arrow = page.locator('tbody tr svg.lucide-arrow-right').first();
    await expect(arrow).toBeVisible();
    await expect(arrow).toHaveCSS('rotate', '180deg');
});

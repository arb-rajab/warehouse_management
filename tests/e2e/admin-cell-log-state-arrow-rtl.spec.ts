import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { openAccountMenu } from './support/nav';

test('the cell log state-change arrow flips direction in RTL layouts', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await openAccountMenu(page);
    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await page.goto('/admin/cell-logs?action[]=stored');

    const arrow = page.locator('tbody tr').first().getByText('→');
    await expect(arrow).toBeVisible();
    await expect(arrow).toHaveCSS('rotate', '180deg');
});

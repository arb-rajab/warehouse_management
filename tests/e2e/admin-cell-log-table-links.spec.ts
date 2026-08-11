import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the cell log table links the cell and done-by cells to their own admin pages', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/cell-logs');

    const firstRow = page.locator('tbody tr').first();

    const cellLink = firstRow.locator('td').nth(0).getByRole('link').first();
    await expect(cellLink).toHaveAttribute(
        'href',
        /^\/admin\/rows\/[A-Za-z0-9]+$/,
    );

    const doneByLink = firstRow.getByRole('link', { name: 'Test User' });
    await expect(doneByLink).toHaveAttribute(
        'href',
        /^\/admin\/users\/\d+\/edit$/,
    );

    await doneByLink.click();
    await expect(page).toHaveURL(/\/admin\/users\/\d+\/edit$/);
    await expect(page.locator('#name')).toHaveValue('Test User');
});

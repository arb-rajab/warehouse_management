import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the cell log table links the cell and done-by cells to their own admin pages', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/cell-logs');

    // Not necessarily the topmost (most recent) row: CellStatusLogSeeder's
    // suspicious-activity scenarios (e.g. seedRapidActions) can land a more
    // recent timestamp than the bulk-seeded logs, and those are attributed
    // to their own demo mobile users, not "Test User" — so find a row done
    // by "Test User" rather than assuming the first row is one.
    const firstRow = page
        .locator('tbody tr')
        .filter({ has: page.getByRole('link', { name: 'Test User' }) })
        .first();

    const cellLink = firstRow.locator('td').nth(0).getByRole('link').first();
    await expect(cellLink).toHaveAttribute(
        'href',
        /^\/admin\/rows\/[A-Za-z0-9]+$/,
    );

    const doneByLink = firstRow.getByRole('link', { name: 'Test User' });
    await expect(doneByLink).toHaveAttribute('href', /^\/admin\/users\/\d+$/);

    await doneByLink.click();
    await expect(page).toHaveURL(/\/admin\/users\/\d+$/);
    await expect(page.locator('h1')).toContainText('Test User');
});

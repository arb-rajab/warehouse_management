import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the row cell grid shows a header for each flat level and each cell', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZY');
    await page.locator('#cells_count').fill('2');
    await page.locator('#flats_count').fill('3');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page).toHaveURL(/\/admin\/rows\/ZY$/);

    const flatHeaders = page.getByTestId('flat-header');
    await expect(flatHeaders).toHaveText(['Flat 3', 'Flat 2', 'Flat 1']);

    const cellHeaders = page.getByTestId('cell-header');
    await expect(cellHeaders).toHaveText(['Column 1', 'Column 2']);
});

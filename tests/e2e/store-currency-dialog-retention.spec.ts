import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('currency dialogs keep typed input after server validation and reset on reopen', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/currency');
    const add = page.getByRole('link', { name: 'Add currency', exact: true });
    await add.click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.locator('#name')).toBeFocused();
    await dialog.locator('#name').fill('Dialog Euro');
    await dialog.locator('#symbol').fill('€');
    await dialog.locator('#code').fill('EUR');
    await dialog.locator('#exchange_rate').fill('1.25');
    // Let the browser send a value that only backend validation should reject.
    await dialog.locator('#exchange_rate').evaluate((input) => {
        input.removeAttribute('min');
        input.removeAttribute('step');
    });
    await dialog.locator('#exchange_rate').fill('0');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        dialog.getByText('The exchange rate field must be greater than 0.'),
    ).toHaveCount(2);
    await expect(dialog.locator('#name')).toHaveValue('Dialog Euro');
    await expect(dialog.locator('#exchange_rate')).toHaveValue('0');
    await dialog.locator('#exchange_rate').fill('1.25');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    const row = page.getByRole('row', { name: /Dialog Euro/ });
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(dialog.locator('#name')).toHaveValue('Dialog Euro');
    await dialog.locator('#name').fill('Dialog Euro Edited');
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(
        page.getByRole('row', { name: /Dialog Euro Edited/ }),
    ).toBeVisible();
    await add.click();
    await expect(dialog.locator('#name')).toHaveValue('');
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(add).toBeFocused();
});

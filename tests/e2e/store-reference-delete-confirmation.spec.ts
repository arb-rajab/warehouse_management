import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('dismissing a tax deletion keeps it and accepting removes it', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/tax');
    await page.locator('#name').fill('Reference Delete Candidate');
    await page.getByRole('button', { name: 'Add tax', exact: true }).click();
    const row = page.getByRole('row', { name: /Reference Delete Candidate/ });
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
});

import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('dismissing the delete confirmation keeps the row, accepting it deletes it', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZY');
    await page.locator('#cells_count').fill('1');
    await page.locator('#flats_count').fill('1');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page).toHaveURL(/\/admin\/rows\/ZY$/);

    await page.goto('/admin/rows');
    const row = page.getByRole('row', { name: /^ZY/ });
    const deleteButton = row.getByRole('button', { name: 'Delete' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await deleteButton.click();
    await expect(row).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await expect(page.getByRole('row', { name: /^ZY/ })).toHaveCount(0);
});

test('dismissing the delete confirmation keeps the user, accepting it deletes it', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/users/create');
    await page.locator('#name').fill('Delete Candidate');
    await page.locator('#email').fill('delete-candidate@example.com');
    await page.locator('#password').fill('Password123!');
    await page.locator('#password_confirmation').fill('Password123!');
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page).toHaveURL(/\/admin\/users$/);

    const row = page.getByRole('row', { name: /Delete Candidate/ });
    const deleteButton = row.getByRole('button', { name: 'Delete' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await deleteButton.click();
    await expect(row).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await expect(
        page.getByRole('row', { name: /Delete Candidate/ }),
    ).toHaveCount(0);
});

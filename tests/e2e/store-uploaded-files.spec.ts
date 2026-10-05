import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

/**
 * The uploaded-files library (.ai/rules/store-backoffice-migration.md §2.4):
 * a real multipart upload from the upload page (a 1x1 PNG fixture), then the native delete
 * confirmation jsdom cannot drive.
 */
test('an uploaded file reaches the library, and a dismissed delete keeps it while an accepted one removes it', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);

    await page.goto('/temp/admin/uploaded-files/create');
    await page
        .locator('input[type="file"]')
        .setInputFiles('tests/e2e/fixtures/e2e-pixel.png');
    await expect(
        page.getByRole('listitem').filter({ hasText: 'e2e-pixel.png' }),
    ).toContainText('Uploaded');

    await page.getByRole('link', { name: 'Back to uploaded files' }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/uploaded-files$/);

    const tile = page
        .getByRole('listitem')
        .filter({ hasText: 'e2e-pixel.png' });
    const deleteButton = tile.getByRole('button', { name: 'Delete' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await deleteButton.click();
    await expect(tile).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await expect(
        page.getByRole('listitem').filter({ hasText: 'e2e-pixel.png' }),
    ).toHaveCount(0);
});

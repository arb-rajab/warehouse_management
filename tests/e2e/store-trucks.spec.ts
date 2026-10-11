import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

/** Real browser navigation, server-validation retention and the native PDF download. */
test('truck forms retain invalid edits and the list downloads a printable QR PDF', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    const name = `Truck Browser ${Date.now()}`;
    const plate = `QR-${Date.now()}`;
    await page.goto('/temp/admin/trips/trucks/create');
    await page.locator('#name').fill(name);
    await page.locator('#license_plate').fill(plate);
    await page.locator('#description').fill('Browser-created truck');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/trucks\/index$/);
    const row = page.getByRole('row', { name: new RegExp(name) });
    await expect(row).toBeVisible();
    const downloadPromise = page.waitForEvent('download');
    await row.getByRole('link', { name: 'Print QR', exact: true }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/^truck-\d+-qr\.pdf$/);
    expect(await download.failure()).toBeNull();
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/trucks\/\d+\/edit$/);
    await expect(page.locator('#description')).toHaveValue(
        'Browser-created truck',
    );
    await page.getByRole('link', { name: 'Cancel', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/trucks\/index$/);
    await page.goto('/temp/admin/trips/trucks/create');
    await page.locator('#name').fill('Duplicate plate candidate');
    await page.locator('#license_plate').fill(plate);
    await page
        .locator('#description')
        .fill('Keep this text after server validation');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByText('The license plate has already been taken.'),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/trucks\/create$/);
    await expect(page.locator('#name')).toHaveValue(
        'Duplicate plate candidate',
    );
    await expect(page.locator('#description')).toHaveValue(
        'Keep this text after server validation',
    );
});

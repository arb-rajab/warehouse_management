import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

/** Driver navigation and native confirmation alongside the remaining Blade pages. */
test('drivers navigate between Inertia and Blade and confirm deletion', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    const name = `Driver Browser ${Date.now()}`;
    // Countries are intentionally unseeded in the e2e database. The existing
    // driver's stored country stays selectable on edit even when inactive.
    const status = await page.evaluate(async (driverName) => {
        const csrf = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        const response = await fetch('/temp/admin/trips/drivers/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                name: driverName,
                email: `driver-browser-${Date.now()}@example.com`,
                phone: '0612345678',
                country: 'Netherlands',
                password: 'Password123!',
                password_confirmation: 'Password123!',
            }),
        });

        return response.status;
    }, name);
    expect(status).toBe(200);
    await page.goto('/temp/admin/trips/drivers/index');
    await expect(
        page.getByRole('heading', { name: 'Drivers', exact: true }),
    ).toBeVisible();
    const row = page.getByRole('row', { name: new RegExp(name) });
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/drivers\/\d+\/edit$/);
    await expect(page.locator('#name')).toHaveValue(name);
    await expect(page.locator('#country')).toHaveValue('Netherlands');
    await expect(page.locator('#email')).toHaveAttribute('readonly');
    await page.getByRole('link', { name: 'Cancel', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/drivers\/index$/);
    const menu = page.getByRole('navigation', { name: 'Store admin menu' });
    await menu
        .getByRole('button', { name: 'Representatives', exact: true })
        .click();
    await menu
        .getByRole('link', { name: 'Representative visits', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/representatives\/visits\/index$/);
    await expect(
        page.getByRole('heading', { name: 'All Visits', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#app')).toHaveCount(0);
    await page.goto('/temp/admin/trips/drivers/index');
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('row', { name: new RegExp(name) })
        .getByRole('button', { name: 'Delete', exact: true })
        .click();
    await expect(page.getByRole('row', { name: new RegExp(name) })).toHaveCount(
        0,
    );
});

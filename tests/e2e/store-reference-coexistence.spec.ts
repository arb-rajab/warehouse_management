import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('shipping configuration visits converted carriers through Inertia and reference pages still boot Inertia', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/shipping_configuration');
    await expect(
        page.getByRole('heading', {
            name: 'Shipping configuration',
            exact: true,
        }),
    ).toBeVisible();
    const carrierLoad = page.waitForResponse(
        (response) =>
            response.url().endsWith('/temp/admin/carriers') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await page
        .getByRole('link', { name: 'Configure shipping carriers', exact: true })
        .click();
    expect((await carrierLoad).ok()).toBe(true);
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Shipping carriers', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/tax');
    await expect(
        page.getByRole('heading', { name: 'Taxes', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#app')).toHaveCount(1);
});

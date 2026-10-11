import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('shipping configuration loads the Blade carrier page in full and reference pages still boot Inertia', async ({
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
            response.request().isNavigationRequest(),
    );
    await page
        .getByRole('link', { name: 'Configure shipping carriers', exact: true })
        .click();
    expect((await carrierLoad).ok()).toBe(true);
    await expect(page.locator('#app')).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'All Carriers', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/tax');
    await expect(
        page.getByRole('heading', { name: 'Taxes', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#app')).toHaveCount(1);
});

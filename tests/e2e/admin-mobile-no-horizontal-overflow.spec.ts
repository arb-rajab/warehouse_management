import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test.use({ viewport: { width: 375, height: 800 } });

for (const path of ['/admin/products', '/admin/users', '/admin/rows']) {
    test(`${path} does not scroll sideways on a phone`, async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(path);

        await expect(page.locator('main#main')).toBeVisible();

        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );

        expect(overflow).toBeLessThanOrEqual(0);
    });
}

test('every quick filter on the products page is visible on a phone', async ({
    page,
}) => {
    await loginAsAdmin(page);
    await page.goto('/admin/products');

    for (const id of [
        'products-quick-search',
        'products-quick-inactive',
        'products-quick-hide-inactive',
        'products-quick-low-stock',
    ]) {
        await expect(page.locator(`#${id}`)).toBeVisible();
    }
});

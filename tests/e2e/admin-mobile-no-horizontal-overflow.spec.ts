import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test.use({ viewport: { width: 375, height: 800 } });

for (const path of [
    '/admin',
    '/admin/products',
    '/admin/users',
    '/admin/rows',
    '/admin/warehouses',
]) {
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

test('the phone sidebar is a drawer from the header: current page marked, closes on the backdrop and after a visit', async ({
    page,
}) => {
    await loginAsAdmin(page);
    await page.goto('/admin/rows');

    const sidebar = page.locator('#admin-sidebar');
    const openMenu = page.getByRole('button', { name: 'Open menu' });

    await expect(sidebar).toBeHidden();
    await openMenu.click();
    await expect(sidebar).toBeVisible();
    await expect(openMenu).toHaveAttribute('aria-expanded', 'true');
    await expect(
        sidebar.getByRole('link', { name: 'Rows', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    // The drawer covers part of the page but never widens it.
    const overflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);

    await page
        .getByTestId('sidebar-backdrop')
        .click({ position: { x: 360, y: 400 } });
    await expect(sidebar).toBeHidden();
    await expect(openMenu).toBeFocused();

    await openMenu.click();
    await sidebar.getByRole('link', { name: 'Users', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/users$/);
    await expect(sidebar).toBeHidden();
});

import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

/**
 * The sidebar's named landmark: "Help" (header "?" link and sidebar item) and
 * "Dashboard" (link and page heading) also appear outside it.
 */
function menu(page: Page) {
    return page.getByRole('navigation', { name: 'Warehouse menu' });
}

test('the sidebar highlights the current section and not the others', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await expect(
        menu(page).getByRole('link', { name: 'Dashboard' }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
        menu(page).getByRole('link', { name: 'Cell Log' }),
    ).not.toHaveAttribute('aria-current', 'page');
    await expect(
        menu(page).getByRole('link', { name: 'Users' }),
    ).not.toHaveAttribute('aria-current', 'page');

    await menu(page).getByRole('link', { name: 'Users' }).click();
    await expect(page).toHaveURL(/\/admin\/users$/);

    await expect(
        menu(page).getByRole('link', { name: 'Users' }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
        menu(page).getByRole('link', { name: 'Rows' }),
    ).not.toHaveAttribute('aria-current', 'page');
    await expect(menu(page).locator('[aria-current="page"]')).toHaveCount(1);
});

test('the Warehouses link is highlighted on its own pages, not on the warehouse map', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await menu(page)
        .getByRole('link', { name: 'Warehouses', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/warehouses$/);
    await expect(
        menu(page).getByRole('link', { name: 'Warehouses', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    await page.goto('/admin/warehouses/create');
    await expect(
        menu(page).getByRole('link', { name: 'Warehouses', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    await menu(page).getByRole('link', { name: 'Warehouse map' }).click();
    await expect(page).toHaveURL(/\/admin\/warehouse-map/);
    await expect(
        menu(page).getByRole('link', { name: 'Warehouses', exact: true }),
    ).not.toHaveAttribute('aria-current', 'page');
    await expect(
        menu(page).getByRole('link', { name: 'Warehouse map' }),
    ).toHaveAttribute('aria-current', 'page');
});

test('Pallets, Settings and Help are sidebar destinations with their own highlight', async ({
    page,
}) => {
    await loginAsAdmin(page);

    for (const [name, url] of [
        ['Pallets', /\/admin\/pallets$/],
        ['Settings', /\/admin\/settings$/],
        ['Help', /\/admin\/help$/],
    ] as const) {
        await menu(page).getByRole('link', { name, exact: true }).click();
        await expect(page).toHaveURL(url);
        await expect(
            menu(page).getByRole('link', { name, exact: true }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(menu(page).locator('[aria-current="page"]')).toHaveCount(
            1,
        );
    }

    // A dashboard stat link adds a query string; Pallets still lights up.
    await page.goto('/admin/pallets?state=empty');
    await expect(
        menu(page).getByRole('link', { name: 'Pallets', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
});

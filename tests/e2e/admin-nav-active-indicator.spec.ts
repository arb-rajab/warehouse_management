import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the app bar highlights the current section and not the others', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await expect(page.getByRole('link', { name: 'Dashboard' })).toHaveAttribute(
        'aria-current',
        'page',
    );
    await expect(
        page.getByRole('link', { name: 'Cell Log' }),
    ).not.toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('link', { name: 'Users' })).not.toHaveAttribute(
        'aria-current',
        'page',
    );

    await page.getByRole('link', { name: 'Users' }).click();
    await expect(page).toHaveURL(/\/admin\/users$/);

    await expect(page.getByRole('link', { name: 'Users' })).toHaveAttribute(
        'aria-current',
        'page',
    );
    await expect(page.getByRole('link', { name: 'Rows' })).not.toHaveAttribute(
        'aria-current',
        'page',
    );
});

test('the Warehouses link is highlighted on its own pages, not on the warehouse map', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.getByRole('link', { name: 'Warehouses', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/warehouses$/);
    await expect(
        page.getByRole('link', { name: 'Warehouses', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    await page.goto('/admin/warehouses/create');
    await expect(
        page.getByRole('link', { name: 'Warehouses', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    await page.getByRole('link', { name: 'Warehouse map' }).click();
    await expect(
        page.getByRole('link', { name: 'Warehouses', exact: true }),
    ).not.toHaveAttribute('aria-current', 'page');
});

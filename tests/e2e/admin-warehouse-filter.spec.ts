import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import {
    createRow,
    createWarehouse,
    deleteRow,
    warehouseId,
} from './support/warehouses';

/** Searches the warehouse filter behind `triggerId` and ticks the matching warehouse. */
async function pickWarehouse(
    page: Page,
    triggerId: string,
    name: string,
): Promise<void> {
    await page.locator(`#${triggerId}`).click();
    await page.getByPlaceholder('Search warehouses…').fill(name);
    await page.getByRole('option', { name }).getByRole('checkbox').check();
}

test('a row letter two warehouses share is told apart, filtered and linked by its warehouse', async ({
    page,
}) => {
    await loginAsAdmin(page);
    await createWarehouse(page, 'E2E Depot');
    await createRow(page, 'Default', 'XQ');
    await createRow(page, 'E2E Depot', 'XQ');
    await expect(page).toHaveURL(/\/admin\/rows\/XQ\?warehouse_id=\d+$/);
    const depotId = await warehouseId(page, 'E2E Depot');
    const defaultId = await warehouseId(page, 'Default');

    // The warehouse filter narrows the rows list to the chosen warehouse.
    await page.goto('/admin/rows');
    await pickWarehouse(page, 'rows-warehouse', 'E2E Depot');
    await expect(page).toHaveURL(/warehouse_ids/);
    await expect(page.locator('#rows-warehouse')).toHaveText('E2E Depot');
    const shared = page.getByRole('row', { name: /^XQ/ });
    await expect(shared).toHaveCount(1);
    await expect(shared).toContainText('E2E Depot');

    // Its links name the warehouse, so the right one of the two XQ rows opens.
    await shared.getByRole('link', { name: 'View' }).click();
    await expect(page).toHaveURL(
        new RegExp(`/admin/rows/XQ\\?warehouse_id=${depotId}$`),
    );
    await expect(page.locator('h1')).toHaveText('Row XQ');
    await expect(page.locator('dl')).toContainText('E2E Depot');

    // Row dropdowns carry the warehouse for the shared letter only.
    await page.goto('/admin/cell-logs');
    await page.getByRole('button', { name: 'Filters' }).click();
    await page.locator('#filter-row').click();
    await expect(
        page.getByRole('option', { name: 'XQ - Default' }),
    ).toBeVisible();
    await expect(
        page.getByRole('option', { name: 'XQ - E2E Depot' }),
    ).toBeVisible();

    await deleteRow(page, depotId, 'XQ');
    await deleteRow(page, defaultId, 'XQ');
    await page.goto('/admin/warehouses');
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('row', { name: /E2E Depot/ })
        .getByRole('button', { name: 'Delete' })
        .click();
    await expect(page.getByRole('row', { name: /E2E Depot/ })).toHaveCount(0);
});

test('the dashboard warehouse filter reloads the stats and keeps its label after a reload', async ({
    page,
}) => {
    await loginAsAdmin(page);
    await createWarehouse(page, 'E2E Empty Depot');

    await page.goto('/admin');
    await expect(page.locator('#dashboard-warehouse')).toHaveText('All');
    await pickWarehouse(page, 'dashboard-warehouse', 'E2E Empty Depot');

    await expect(page).toHaveURL(/warehouse_ids/);
    await expect(page.locator('#dashboard-warehouse')).toHaveText(
        'E2E Empty Depot',
    );

    // The selected label is hydrated from the page props on a fresh load.
    await page.reload();
    await expect(page.locator('#dashboard-warehouse')).toHaveText(
        'E2E Empty Depot',
    );

    // A warehouse without rows holds nothing: the full-cells tile reads zero.
    // The tile's link is named label first, then value.
    await expect(page.getByRole('link', { name: /^Full 0$/ })).toBeVisible();

    // The tiles carry the filter to the pallets list, whose own filter echoes it.
    await page.getByRole('link', { name: /^Full 0$/ }).click();
    await expect(page).toHaveURL(/warehouse_ids/);
    await expect(page.locator('#pallets-warehouse')).toHaveText(
        'E2E Empty Depot',
    );

    await page.goto('/admin/warehouses');
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('row', { name: /E2E Empty Depot/ })
        .getByRole('button', { name: 'Delete' })
        .click();
    await expect(
        page.getByRole('row', { name: /E2E Empty Depot/ }),
    ).toHaveCount(0);
});

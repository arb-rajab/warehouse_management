import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/** Adds a warehouse through its form and waits for the list to confirm it. */
export async function createWarehouse(
    page: Page,
    name: string,
    code = '',
): Promise<void> {
    await page.goto('/admin/warehouses/create');
    await page
        .getByRole('textbox', { name: 'Name required', exact: true })
        .fill(name);

    if (code !== '') {
        await page
            .getByRole('textbox', { name: 'Code', exact: true })
            .fill(code);
    }

    await page.getByRole('button', { name: 'Create warehouse' }).click();
    await expect(page).toHaveURL(/\/admin\/warehouses$/);
}

/** The id of a warehouse, read from the warehouse select of the add-row form. */
export async function warehouseId(page: Page, name: string): Promise<string> {
    await page.goto('/admin/rows/create');
    const value = await page
        .locator('#warehouse_id option', { hasText: name })
        .getAttribute('value');

    if (value === null) {
        throw new Error(`No warehouse named "${name}" in the add-row form`);
    }

    return value;
}

/** Adds a one-cell row to the named warehouse and waits for its page. */
export async function createRow(
    page: Page,
    warehouse: string,
    letter: string,
): Promise<void> {
    await page.goto('/admin/rows/create');
    await page.locator('#warehouse_id').selectOption({ label: warehouse });
    await page.locator('#letter').fill(letter);
    await page.locator('#cells_count').fill('1');
    await page.locator('#flats_count').fill('1');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page.locator('h1')).toHaveText(`Row ${letter}`);
}

/** Deletes the row with this letter that the warehouse filter leaves on the list. */
export async function deleteRow(
    page: Page,
    warehouseIdValue: string,
    letter: string,
): Promise<void> {
    await page.goto(`/admin/rows?warehouse_ids[]=${warehouseIdValue}`);
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('row', { name: new RegExp(`^${letter}`) })
        .getByRole('button', { name: 'Delete' })
        .click();
    await expect(
        page.getByRole('row', { name: new RegExp(`^${letter}`) }),
    ).toHaveCount(0);
}

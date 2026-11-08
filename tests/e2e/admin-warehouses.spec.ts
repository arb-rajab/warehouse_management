import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { createRow, deleteRow, warehouseId } from './support/warehouses';

test('an admin adds, edits and deletes a warehouse; one that holds rows keeps its place', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/warehouses');
    await page.getByRole('link', { name: 'Add warehouse' }).click();
    await expect(page.getByText('Required fields')).toBeVisible();
    await page
        .getByRole('textbox', { name: 'Name required', exact: true })
        .fill('E2E Annex');
    await page.getByRole('textbox', { name: 'Code', exact: true }).fill('E2EA');
    await page.getByRole('button', { name: 'Create warehouse' }).click();

    await expect(page).toHaveURL(/\/admin\/warehouses$/);
    await expect(
        page.getByText('Warehouse has been created successfully'),
    ).toBeVisible();
    const row = page.getByRole('row', { name: /E2E Annex/ });
    await expect(row).toContainText('E2EA');

    // A duplicate name is refused by the server and the typed values stay.
    await page.getByRole('link', { name: 'Add warehouse' }).click();
    await page
        .getByRole('textbox', { name: 'Name required', exact: true })
        .fill('E2E Annex');
    await page.getByRole('textbox', { name: 'Code', exact: true }).fill('KEEP');
    await page.getByRole('button', { name: 'Create warehouse' }).click();
    await expect(
        page.getByText('The name has already been taken.'),
    ).toBeVisible();
    await expect(
        page.getByRole('textbox', { name: 'Code', exact: true }),
    ).toHaveValue('KEEP');

    // Editing renames it and flashes its own message.
    await page.goto('/admin/warehouses');
    await page
        .getByRole('row', { name: /E2E Annex/ })
        .getByRole('link', { name: 'Edit' })
        .click();
    await page
        .getByRole('textbox', { name: 'Name required', exact: true })
        .fill('E2E Annex North');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(
        page.getByText('Warehouse has been updated successfully'),
    ).toBeVisible();

    // With a row in it the warehouse cannot be deleted: the button is hidden.
    await createRow(page, 'E2E Annex North', 'YW');
    await expect(page.getByText('Annex North').first()).toBeVisible();
    await page.goto('/admin/warehouses');
    const withRows = page.getByRole('row', { name: /E2E Annex North/ });
    await expect(withRows.getByText('Has rows')).toBeVisible();
    await expect(withRows.getByRole('button', { name: 'Delete' })).toHaveCount(
        0,
    );

    await deleteRow(page, await warehouseId(page, 'E2E Annex North'), 'YW');

    // Empty again, it is deleted after a confirmation the admin can refuse.
    await page.goto('/admin/warehouses');
    const empty = page.getByRole('row', { name: /E2E Annex North/ });
    const deleteButton = empty.getByRole('button', { name: 'Delete' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await deleteButton.click();
    await expect(empty).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await expect(
        page.getByText('Warehouse has been deleted successfully'),
    ).toBeVisible();
    await expect(
        page.getByRole('row', { name: /E2E Annex North/ }),
    ).toHaveCount(0);
});

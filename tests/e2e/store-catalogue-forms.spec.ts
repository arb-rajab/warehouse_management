import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

for (const resource of ['attributes', 'colors']) {
    test(`${resource} cancel and accept native delete confirmations`, async ({
        page,
    }) => {
        await loginAsStoreAdmin(page);
        await page.goto(`/temp/admin/${resource}`);
        const name = `Delete ${resource} ${Date.now()}`.slice(0, 30);
        await page.locator('#name').fill(name);

        if (resource === 'colors') {
            await page.locator('#code').fill('#9b31c8');
        }

        await page.getByRole('button', { name: 'Save', exact: true }).click();
        const savedName =
            resource === 'colors' ? name.replaceAll(' ', '') : name;
        const row = page.getByRole('row', { name: new RegExp(savedName) });
        await expect(row).toBeVisible();
        page.once('dialog', (dialog) => dialog.dismiss());
        await row.getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(row).toBeVisible();
        page.once('dialog', (dialog) => dialog.accept());
        await row.getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(row).toHaveCount(0);
    });
}

test('attribute values cancel and accept native delete confirmations', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/attributes');
    const name = `Variant ${Date.now()}`;
    await page.locator('#name').fill(name);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const attribute = page.getByRole('row', { name: new RegExp(name) });
    await attribute.getByRole('link', { name: 'Attribute values' }).click();
    await page.locator('#value').fill('Large');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const row = page.getByRole('row', { name: /Large/ });
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    await page.goto('/temp/admin/attributes');
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('row', { name: new RegExp(name) })
        .getByRole('button', { name: 'Delete', exact: true })
        .click();
});

test('a rejected duplicate color preserves form inputs through the server round trip', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/colors');
    const name = `Color ${Date.now()}`;
    const code = '#2c917a';
    await page.locator('#name').fill(name);
    await page.locator('#code').fill(code);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByRole('row', { name: new RegExp(name.replaceAll(' ', '')) }),
    ).toBeVisible();
    await page.locator('#name').fill('Typed duplicate');
    await page.locator('#code').fill(code);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByText('The code has already been taken.'),
    ).toBeVisible();
    await expect(page.locator('#name')).toHaveValue('Typed duplicate');
    await expect(page.locator('#code')).toHaveValue(code);
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('row', { name: new RegExp(name.replaceAll(' ', '')) })
        .getByRole('button', { name: 'Delete', exact: true })
        .click();
});

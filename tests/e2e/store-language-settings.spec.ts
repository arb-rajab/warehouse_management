import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('language forms retain input after validation and confirm deletion in the browser', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/languages/create');
    await page.locator('#name').fill('French');
    await page.locator('#code').fill('f');
    expect(
        await page
            .locator('#code')
            .evaluate(
                (input: HTMLInputElement) => input.validity.patternMismatch,
            ),
    ).toBe(true);
    await page.locator('#code').fill('fr');
    await page.locator('#app_lang_code').fill('fr');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/languages$/);

    await page.goto('/temp/admin/languages/create');
    await page.locator('#name').fill('Keep typed name');
    await page.locator('#code').fill('fr');
    await page.locator('#app_lang_code').fill('fr');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('#name')).toHaveValue('Keep typed name');
    await expect(page.locator('#code')).toHaveValue('fr');
    await expect(
        page.getByText('The code has already been taken.'),
    ).toBeVisible();

    await page.goto('/temp/admin/languages');
    const row = page.getByRole('row', { name: /^French/ });
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
});

test('RTL settings and translation inputs coexist with Blade and retain Arabic layout', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/languages/create');
    await page.locator('#name').fill('Arabic');
    await page.locator('#code').fill('ar');
    await page.locator('#app_lang_code').fill('ar');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const row = page.getByRole('row', { name: /^Arabic/ });
    await row.locator('select').first().selectOption('1');
    await row
        .getByRole('button', { name: 'Save', exact: true })
        .first()
        .click();
    await expect(row.locator('select').first()).toHaveValue('1');
    await page.getByRole('button', { name: 'Arabic', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'اللغات', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/facebook-chat');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'دردشة فيسبوك', exact: true }),
    ).toBeVisible();
    await page.goto('/temp');
    await expect(page.locator('#app')).toHaveCount(0);
});

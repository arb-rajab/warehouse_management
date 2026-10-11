import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('header edits keep the picker, paired menu rows, and storefront rendering', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/website/header');
    await expect(
        page.getByRole('heading', { name: 'Website header' }),
    ).toBeVisible();
    await expect(
        page.locator('input[name="settings[header_logo]"]'),
    ).toHaveAttribute('type', 'hidden');
    const picker = page
        .locator('#header_logo')
        .locator('..')
        .getByRole('button', { name: 'Choose files', exact: true });
    await picker.click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(picker).toBeFocused();

    await page.locator('#helpline_number').fill('55512345');
    await page.locator('#header_stikcy').check();
    await page
        .getByRole('button', { name: 'Add menu row', exact: true })
        .click();
    const labels = page.locator('input[id^="menu-label-"]');
    const links = page.locator('input[id^="menu-link-"]');
    await labels.last().fill('Header migration test');
    await links.last().fill('/header-migration-test');
    const saved = page.waitForResponse(
        (response) =>
            response.url().endsWith('/temp/admin/website/header') &&
            response.request().method() === 'PUT',
    );
    await page
        .getByRole('button', { name: 'Save header', exact: true })
        .click();
    await saved;
    await expect(
        page.getByRole('button', { name: 'Save header', exact: true }),
    ).toBeEnabled();
    await page.reload();
    await expect(page.locator('#helpline_number')).toHaveValue('55512345');
    await expect(page.locator('#header_stikcy')).toBeChecked();
    await expect(labels.last()).toHaveValue('Header migration test');
    await expect(links.last()).toHaveValue('/header-migration-test');

    await page.goto('/temp');
    await expect(
        page
            .getByRole('link', { name: 'Header migration test', exact: true })
            .first(),
    ).toHaveAttribute('href', '/header-migration-test');
    await expect(page.locator('header.sticky-top')).toBeVisible();
});

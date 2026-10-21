import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('carrier tariff editing keeps zone prices and logo selection, and deletion requires confirmation', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('zones')->updateOrInsert(['name' => 'Carrier browser zone'], ['status' => 1]);
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: 'carrier-logo.png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('button', { name: 'carrier-logo', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/carriers/create');
    await page.locator('#carrier_name').fill('Browser tariff');
    await page.locator('#transit_time').fill('2–3 days');
    await page
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'carrier-logo', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    await expect(page.locator('input[name=logo]')).not.toHaveValue('');
    const logo = await page.locator('input[name=logo]').inputValue();
    await page.getByLabel('Carrier browser zone', { exact: true }).check();
    await page.locator('#delimiter2-0').fill('10');
    await page
        .getByRole('spinbutton', {
            name: 'Carrier browser zone — Price required',
            exact: true,
        })
        .fill('5.25');
    await page.getByRole('button', { name: 'Add range', exact: true }).click();
    await page.locator('#delimiter2-1').fill('20');
    await page
        .getByRole('spinbutton', {
            name: 'Carrier browser zone — Price required',
            exact: true,
        })
        .last()
        .fill('8.75');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/carriers$/);
    const row = page.getByRole('row', { name: /Browser tariff/ });
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page).toHaveURL(/\/carriers\/\d+\/edit$/);
    await expect(page.locator('input[name=logo]')).toHaveValue(logo);
    const prices = page.getByRole('spinbutton', {
        name: 'Carrier browser zone — Price required',
        exact: true,
    });
    await expect(prices.first()).toHaveValue('5.25');
    await expect(prices.last()).toHaveValue('8.75');
    await page.locator('#billing_type').selectOption('price_based');
    await page
        .getByRole('button', { name: 'Remove range', exact: true })
        .last()
        .click();
    await page.locator('#delimiter2-0').fill('30');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/carriers$/);
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.locator('#billing_type')).toHaveValue('price_based');
    await expect(page.locator('input[name="delimiter2[0]"]')).toHaveValue('30');
    await expect(
        page.getByRole('spinbutton', {
            name: 'Carrier browser zone — Price required',
            exact: true,
        }),
    ).toHaveCount(1);
    await expect(
        page.getByRole('spinbutton', {
            name: 'Carrier browser zone — Price required',
            exact: true,
        }),
    ).toHaveValue('5.25');
    await page.goto('/temp/admin/carriers');
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
});

test('carrier fields support Arabic RTL, native tariff validation and dark input contrast', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/carriers/create');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'إضافة شركة شحن', exact: true }),
    ).toBeVisible();
    await page.locator('#delimiter1-0').fill('1');
    await page.locator('#delimiter2-0').fill('0');
    await expect
        .poll(() =>
            page
                .locator('#delimiter2-0')
                .evaluate((input: HTMLInputElement) => input.validationMessage),
        )
        .toBe('يجب أن يكون الحد الأعلى أكبر من الحد الأدنى.');
    const colors = await page.locator('#carrier_name').evaluate((input) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d', { willReadFrequently: true })!;
        const rgb = (color: string) => {
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
        };
        const style = getComputedStyle(input);

        return {
            foreground: rgb(style.color),
            background: rgb(style.backgroundColor),
        };
    });
    expect(Math.max(...colors.background)).toBeLessThan(60);
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
});

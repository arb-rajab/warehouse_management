import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('customer package CRUD keeps canonical logos, pricing, translations and native delete confirmation', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'classified_product'], ['value' => '1']);
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: 'package-logo.png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('button', { name: 'package-logo', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/customer_packages/create');
    await page.locator('#name').fill('Browser customer package');
    await page.locator('#amount').fill('12.50');
    await page.locator('#product_upload').fill('7');
    await page
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'package-logo', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    const logo = await page.locator('input[name=logo]').inputValue();
    expect(logo).not.toBe('');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/customer_packages$/);
    const row = page.getByRole('row', { name: /Browser customer package/ });
    await row
        .getByRole('link', { name: 'Edit customer package', exact: true })
        .click();
    await expect(page.locator('#amount')).toHaveValue('12.5');
    await expect(page.locator('#product_upload')).toHaveValue('7');
    await expect(page.locator('input[name=logo]')).toHaveValue(logo);
    await page
        .getByRole('navigation', { name: 'Translation language', exact: true })
        .getByRole('link', { name: 'Arabic', exact: true })
        .click();
    await expect(page.locator('input[name=lang]')).toHaveValue('ar');
    await page.locator('#name').fill('باقة المتصفح');
    await page.locator('#amount').fill('0');
    await page.locator('#product_upload').fill('10');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/lang=ar/);
    await expect(page.locator('#name')).toHaveValue('باقة المتصفح');
    await expect(page.locator('#amount')).toHaveValue('0');
    await expect(page.locator('#product_upload')).toHaveValue('10');
    await page
        .getByRole('navigation', { name: 'Translation language', exact: true })
        .getByRole('link', { name: 'English', exact: true })
        .click();
    await expect(page.locator('#name')).toHaveValue('Browser customer package');
    await page.goto('/temp/admin/customer_packages');
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
        exact: true,
    });
    const customers = navigation.getByRole('button', {
        name: 'Customers',
        exact: true,
    });

    if ((await customers.getAttribute('aria-expanded')) !== 'true') {
        await customers.click();
    }

    const response = page.waitForResponse(
        (response) =>
            response.url().endsWith('/temp/admin/customer_packages') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await navigation
        .getByRole('link', { name: 'Classified packages', exact: true })
        .click();
    expect((await response).ok()).toBe(true);
    await expect(page.locator('#app')).toHaveCount(1);
});

test('customer package form supports Arabic RTL dark contrast native validation and refused write retention', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/customer_packages/create');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'إضافة باقة عميل', exact: true }),
    ).toBeVisible();
    await page.locator('#name').fill('Retained draft');
    await page.locator('#amount').fill('-1');
    await page.locator('#product_upload').fill('1.5');
    expect(
        await page
            .locator('#amount')
            .evaluate((input: HTMLInputElement) => input.checkValidity()),
    ).toBe(false);
    expect(
        await page
            .locator('#product_upload')
            .evaluate((input: HTMLInputElement) => input.checkValidity()),
    ).toBe(false);
    await page.locator('#amount').fill('25.75');
    await page.locator('#product_upload').fill('8');
    await page.route('**/temp/admin/customer_packages', async (route) => {
        if (route.request().method() === 'POST') {
            const data = route.request().postDataJSON();
            await route.continue({
                postData: JSON.stringify({ ...data, product_upload: -1 }),
            });
        } else {
            await route.continue();
        }
    });
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    await expect(
        page.locator('#product_upload').locator('..').locator('p'),
    ).toBeVisible();
    await expect(
        page.locator('#product_upload').locator('..').locator('p'),
    ).toContainText('0');
    await expect(page.locator('#name')).toHaveValue('Retained draft');
    await expect(page.locator('#amount')).toHaveValue('25.75');
    await expect(page.locator('#product_upload')).toHaveValue('8');
    await page.unroute('**/temp/admin/customer_packages');
    const colors = await page.locator('#amount').evaluate((input) => {
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
    seedStoreFixtures(String.raw`
        if (App\Models\CustomerPackage::where('name', 'Retained draft')->exists()) { throw new RuntimeException('Refused package write changed data'); }
    `);
});

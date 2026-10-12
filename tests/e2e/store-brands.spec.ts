import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('brand forms round-trip the logo, SEO fields, slug, translations and native delete confirmation', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: 'brand-logo.png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('button', { name: 'brand-logo', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/brands');
    await expect(
        page.getByRole('heading', { name: 'Add new brand', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#order_level')).toHaveCount(0);
    await page.locator('#name').fill('Browser brand');
    await page.locator('#meta_title').fill('Browser SEO title');
    await page.locator('#meta_description').fill('Browser SEO description');
    await page
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'brand-logo', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    const logo = await page.locator('input[name=logo]').inputValue();
    expect(logo).not.toBe('');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const row = page.getByRole('row', { name: /Browser brand/ });
    await expect(row).toBeVisible();
    await expect(row.getByRole('img', { name: 'Logo (120×80)' })).toBeVisible();
    await expect(page.locator('#name')).toHaveValue('');
    await expect(page.locator('input[name=logo]')).toHaveValue('');
    await row.getByRole('link', { name: 'Edit brand', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/brands\/edit\/\d+\?lang=en$/);
    await expect(page.locator('#name')).toHaveValue('Browser brand');
    await expect(page.locator('input[name=logo]')).toHaveValue(logo);
    await expect(page.locator('#meta_title')).toHaveValue('Browser SEO title');
    await expect(page.locator('#meta_description')).toHaveValue(
        'Browser SEO description',
    );
    await expect(page.locator('#slug')).toHaveValue(/^Browser-brand-\w{5}$/);
    await page.locator('#slug').fill('Browser-Brand');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('#slug')).toHaveAttribute(
        'value',
        'browser-brand',
    );
    await page.reload();
    await expect(page.locator('#slug')).toHaveValue('browser-brand');
    await page
        .getByRole('navigation', { name: 'Translation language', exact: true })
        .getByRole('link', { name: 'Arabic', exact: true })
        .click();
    await expect(page.locator('input[name=lang]')).toHaveValue('ar');
    await page.locator('#name').fill('علامة المتصفح');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/lang=ar/);
    await expect(page.locator('#name')).toHaveValue('علامة المتصفح');
    await page
        .getByRole('navigation', { name: 'Translation language', exact: true })
        .getByRole('link', { name: 'English', exact: true })
        .click();
    await expect(page.locator('#name')).toHaveValue('Browser brand');
    await page.goto('/temp/admin/brands');
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    seedStoreFixtures(String.raw`
        if(App\Models\Brand::where('name','Browser brand')->exists()) {throw new RuntimeException('Brand was not deleted');}
        if(App\Models\BrandTranslation::where('name','علامة المتصفح')->exists()) {throw new RuntimeException('Brand translation was not deleted');}
    `);
});

test('brand form keeps Arabic RTL, dark contrast and drafts after an authoritative refusal', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/brands');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', {
            name: 'إضافة علامة تجارية جديدة',
            exact: true,
        }),
    ).toBeVisible();
    await page.locator('#name').fill('x'.repeat(60));
    await expect(page.locator('#name')).toHaveValue('x'.repeat(50));
    await page.locator('#name').fill('Refused brand draft');
    await page.locator('#meta_description').fill('Retained SEO draft');
    await page.route('**/temp/admin/brands', async (route) => {
        if (route.request().method() === 'POST') {
            const data = route.request().postDataJSON();
            await route.continue({
                postData: JSON.stringify({ ...data, logo: 999999 }),
            });
        } else {
            await route.continue();
        }
    });
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    await expect(
        page.locator('input[name=logo]').locator('..').locator('p'),
    ).toBeVisible();
    await expect(page.locator('#name')).toHaveValue('Refused brand draft');
    await expect(page.locator('#meta_description')).toHaveValue(
        'Retained SEO draft',
    );
    await page.unroute('**/temp/admin/brands');
    const colors = await page.locator('#meta_description').evaluate((input) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d', { willReadFrequently: true })!;
        const rgb = (color: string) => {
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return Array.from(context.getImageData(0, 0, 1, 1).data).slice(
                0,
                3,
            ) as [number, number, number];
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
        if(App\Models\Brand::where('name','Refused brand draft')->exists()) {throw new RuntimeException('Refused brand write changed data');}
    `);
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(
        page.getByRole('heading', { name: 'Add new brand', exact: true }),
    ).toBeVisible();
});

test('brand sidebar uses Inertia while the public brand page retains Blade', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        App\Models\Brand::query()->where('slug', 'public-browser-brand')->delete();
        App\Models\Brand::forceCreate(['name' => 'Public browser brand', 'slug' => 'public-browser-brand']);
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/staffs');
    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
        exact: true,
    });
    const products = navigation.getByRole('button', {
        name: 'Products',
        exact: true,
    });

    if ((await products.getAttribute('aria-expanded')) !== 'true') {
        await products.click();
    }

    const response = page.waitForResponse(
        (response) =>
            response.url().endsWith('/temp/admin/brands') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await navigation.getByRole('link', { name: 'Brands', exact: true }).click();
    expect((await response).ok()).toBe(true);
    await expect(
        page.getByRole('heading', { name: 'Brands', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('row', { name: /Public browser brand/ }),
    ).toBeVisible();
    await page.goto('/temp/brands');
    await expect(page.locator('#app')).toHaveCount(0);
    await expect(page.getByText('Public browser brand').first()).toBeVisible();
});

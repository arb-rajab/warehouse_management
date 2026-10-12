import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('category forms round-trip hierarchy attributes uploads SEO translations and native delete confirmation', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        App\Models\Category::forceCreate(['name'=>'Browser parent','slug'=>'browser-parent','parent_id'=>0,'level'=>0]);
        App\Models\Attribute::forceCreate(['name'=>'Browser attribute']);
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type'=>'category_wise_commission'],['value'=>'1']);
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: 'category-banner.png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('button', { name: 'category-banner', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/categories/create');
    await page.locator('#name').fill('Browser category');
    await page.locator('#parent_id').selectOption({ label: 'Browser parent' });
    await page.locator('#order_level').fill('1000');
    await page.locator('#digital').selectOption('1');
    await page.locator('#commision_rate').fill('2.50');
    await page
        .locator('#filtering_attributes')
        .selectOption({ label: 'Browser attribute' });
    await page.locator('#slug').fill('browser-category');
    await page.locator('#meta_title').fill('Browser SEO title');
    await page.locator('#meta_description').fill('Browser SEO description');

    for (let index = 0; index < 3; index++) {
        await page
            .getByRole('button', { name: 'Choose files', exact: true })
            .nth(index)
            .click();
        const dialog = page.getByRole('dialog', { name: 'Choose files' });
        await dialog
            .getByRole('button', { name: 'category-banner', exact: true })
            .click();
        await dialog
            .getByRole('button', { name: 'Use selected files' })
            .click();
    }

    const image = await page.locator('input[name=banner]').inputValue();
    expect(image).not.toBe('');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/categories$/);
    const row = page.getByRole('row', { name: /Browser category/ });
    await row
        .getByRole('link', { name: 'Browser parent', exact: true })
        .click();
    await expect(page.locator('#name')).toHaveValue('Browser parent');
    await page.goto('/temp/admin/categories');
    await row.getByRole('link', { name: 'Edit category', exact: true }).click();
    await expect(page.locator('#digital')).toHaveValue('1');
    await expect(page.locator('#order_level')).toHaveValue('1000');
    await expect(page.locator('#meta_title')).toHaveValue('Browser SEO title');
    await expect(page.locator('#meta_description')).toHaveValue(
        'Browser SEO description',
    );
    await expect(page.locator('#slug')).toHaveValue('browser-category');

    for (const field of ['banner', 'icon', 'cover_image']) {
        await expect(page.locator(`input[name=${field}]`)).toHaveValue(image);
    }

    await page
        .getByRole('navigation', { name: 'Translation language', exact: true })
        .getByRole('link', { name: 'Arabic', exact: true })
        .click();
    await expect(page.locator('input[name=lang]')).toHaveValue('ar');
    await page.locator('#name').fill('تصنيف المتصفح');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/lang=ar/);
    await expect(page.locator('#name')).toHaveValue('تصنيف المتصفح');
    await page
        .getByRole('navigation', { name: 'Translation language', exact: true })
        .getByRole('link', { name: 'English', exact: true })
        .click();
    await expect(page.locator('#name')).toHaveValue('Browser category');
    await page.goto('/temp/admin/categories');
    await row
        .getByRole('button', { name: 'Enable Featured', exact: true })
        .click();
    await expect(
        row.getByRole('button', { name: 'Disable Featured', exact: true }),
    ).toBeVisible();
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    seedStoreFixtures(String.raw`
        if(App\Models\Category::where('name','Browser category')->exists()) {throw new RuntimeException('Category was not deleted');}
        if(!App\Models\Category::where('name','Browser parent')->exists()) {throw new RuntimeException('Parent was deleted');}
    `);
});

test('category form keeps English Arabic RTL dark contrast and drafts after authoritative refusal', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/categories/create');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'إضافة تصنيف', exact: true }),
    ).toBeVisible();
    await page.locator('#name').fill('Refused category draft');
    await page.locator('#order_level').fill('1.5');
    expect(
        await page
            .locator('#order_level')
            .evaluate((input: HTMLInputElement) => input.checkValidity()),
    ).toBe(false);
    await page.locator('#order_level').fill('12');
    await page.locator('#meta_description').fill('Retained SEO draft');
    await page.route('**/temp/admin/categories', async (route) => {
        if (route.request().method() === 'POST') {
            const data = route.request().postDataJSON();
            await route.continue({
                postData: JSON.stringify({ ...data, parent_id: 999999 }),
            });
        } else {
            await route.continue();
        }
    });
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    await expect(
        page.locator('#parent_id').locator('..').locator('p'),
    ).toBeVisible();
    await expect(page.locator('#name')).toHaveValue('Refused category draft');
    await expect(page.locator('#order_level')).toHaveValue('12');
    await expect(page.locator('#meta_description')).toHaveValue(
        'Retained SEO draft',
    );
    await page.unroute('**/temp/admin/categories');
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
        if(App\Models\Category::where('name','Refused category draft')->exists()) {throw new RuntimeException('Refused category write changed data');}
    `);
    await page.goto('/temp/admin/categories/create');
    await expect(
        page.getByRole('heading', { name: 'إضافة تصنيف', exact: true }),
    ).toBeVisible();
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(
        page.getByRole('heading', { name: 'Add category', exact: true }),
    ).toBeVisible();
});

test('category sidebar uses Inertia while public category pages retain Blade', async ({
    page,
}) => {
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
            response.url().endsWith('/temp/admin/categories') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await navigation
        .getByRole('link', { name: 'Categories', exact: true })
        .click();
    expect((await response).ok()).toBe(true);
    await expect(
        page.getByRole('heading', { name: 'Categories', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/categories');
    await expect(page.locator('#app')).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'All Categories', exact: true }),
    ).toBeVisible();
});

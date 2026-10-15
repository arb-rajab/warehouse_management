import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import { seedStoreFixtures } from './support/storeFixtures';

for (const path of ['commission-log', 'wallet-history']) {
    test(`the ${path} report date filters use dark native pickers`, async ({
        page,
    }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await loginAsStoreAdmin(page);
        await page.goto(`/temp/admin/${path}`);
        await page
            .getByRole('button', { name: 'Filters', exact: true })
            .click();
        await expect(page.locator('#report-date-from')).toHaveCSS(
            'color-scheme',
            'dark',
        );
        await expect(page.locator('#report-date-to')).toHaveCSS(
            'color-scheme',
            'dark',
        );
        const { color, backgroundColor } = await page
            .locator('#report-date-from')
            .evaluate((element) => {
                const style = window.getComputedStyle(element);
                const toRgb = (cssColor: string) => {
                    const canvas = document.createElement('canvas');
                    canvas.width = 1;
                    canvas.height = 1;
                    const context = canvas.getContext(
                        '2d',
                    ) as CanvasRenderingContext2D;
                    context.fillStyle = cssColor;
                    context.fillRect(0, 0, 1, 1);

                    return Array.from(
                        context.getImageData(0, 0, 1, 1).data.slice(0, 3),
                    );
                };

                return {
                    color: toRgb(style.color),
                    backgroundColor: toRgb(style.backgroundColor),
                };
            });
        expect(contrastRatio(color, backgroundColor)).toBeGreaterThanOrEqual(
            4.5,
        );
        await page.locator('#report-date-from').fill('2026-10-01');
        await page.locator('#report-date-to').fill('2026-10-08');
        await expect(page.locator('#report-within-days')).toBeDisabled();
        await page
            .getByRole('button', { name: 'Apply filters', exact: true })
            .click();
        await expect(page).toHaveURL(/date_from=2026-10-01/);
        await expect(page).toHaveURL(/date_to=2026-10-08/);
        await page
            .getByRole('button', { name: 'Filters', exact: true })
            .click();
        await expect(page.locator('#report-date-from')).toHaveValue(
            '2026-10-01',
        );
        await expect(page.locator('#report-date-to')).toHaveValue('2026-10-08');
    });
}

test('report filters and table follow the Arabic RTL layout', async ({
    page,
}) => {
    test.setTimeout(60_000);
    await loginAsStoreAdmin(page);
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('languages')->updateOrInsert(['code' => 'ar'], ['name' => 'Arabic', 'rtl' => 1]);
    `);
    await page.goto('/temp/admin/stock_report');
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', {
            name: 'تقرير مخزون المنتجات',
            exact: true,
        }),
    ).toBeVisible();
    await expect(page.locator('table')).toHaveCSS('text-align', 'start');
    await page.getByRole('button', { name: 'الفلاتر', exact: true }).click();
    await expect(page.locator('#report-selection')).toBeVisible();
    await expect(page.locator('#report-selection')).toHaveCSS(
        'text-align',
        'start',
    );
    await page
        .getByRole('button', { name: 'إغلاق الفلاتر', exact: true })
        .click();
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
});

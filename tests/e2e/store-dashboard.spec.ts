import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    documentViolations,
    expectStrictPolicy,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('real dashboard charts render under enforced CSP in light dark and Arabic RTL', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        // Keep unrelated Faker thumbnails local under the unchanged strict img policy.
        App\Models\Product::query()->update(['thumbnail_img' => null]);
        $category = App\Models\Category::forceCreate(['name' => 'Dashboard oils', 'slug' => 'dashboard-oils', 'parent_id' => 0, 'level' => 0]);
        App\Models\CategoryTranslation::create(['category_id' => $category->id, 'lang' => 'ar', 'name' => 'زيوت لوحة التحكم']);
        $product = App\Models\Product::forceCreate(['name' => 'Dashboard olive oil', 'ar_name' => 'زيت زيتون', 'slug' => 'dashboard-olive-oil', 'published' => 1, 'approved' => 1, 'auction_product' => 0, 'added_by' => 'admin', 'from_api' => 0, 'category_id' => $category->id, 'unit_price' => 20, 'rating' => 4.5, 'num_of_sale' => 12]);
        App\Models\ProductTranslation::create(['product_id' => $product->id, 'lang' => 'ar', 'name' => 'زيت زيتون لوحة التحكم']);
        App\Models\ProductStock::forceCreate(['product_id' => $product->id, 'variant' => '', 'price' => 20, 'qty' => 25]);
        $setting = new App\Models\BusinessSetting;
        $setting->type = 'total_visits';
        $setting->value = '{"SY":20,"NL":10}';
        $setting->save();
        Illuminate\Support\Facades\Cache::forget('business_settings');
        Illuminate\Support\Facades\Cache::forget(App\Http\Controllers\Store\Admin\DashboardController::GRAPH_CACHE_KEY);
    `);
    await page.emulateMedia({ colorScheme: 'light' });
    await loginAsStoreAdmin(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin');
    const canvases = page.locator('main canvas');
    await expect(canvases).toHaveCount(5);

    for (const canvas of await canvases.all()) {
        await expect(canvas).toBeVisible();
        await expect(canvas.locator('..')).toHaveAttribute('dir', 'ltr');
        await expect
            .poll(() =>
                canvas.evaluate((element: HTMLCanvasElement) => {
                    const pixels = element
                        .getContext('2d')!
                        .getImageData(0, 0, element.width, element.height).data;

                    return pixels.filter(
                        (value, index) => index % 4 === 3 && value > 0,
                    ).length;
                }),
            )
            .toBeGreaterThan(100);
        expect(await canvas.getAttribute('aria-describedby')).toBeTruthy();
    }

    await expect(page.locator('main table')).toHaveCount(5);
    await expect(
        page.locator('article a[href$="/product/dashboard-olive-oil"]'),
    ).toBeVisible();
    await page.goto('/temp/admin/staffs');
    await expect(page).toHaveURL(/\/admin\/staffs/);
    await page.evaluate(() => {
        (
            window as unknown as { dashboardNavigationMarker: boolean }
        ).dashboardNavigationMarker = true;
    });
    await page.locator('nav a[href$="/temp/admin"]').first().click();
    await expect(page).toHaveURL(/\/temp\/admin$/);
    expect(
        await page.evaluate(
            () =>
                (window as unknown as { dashboardNavigationMarker?: boolean })
                    .dashboardNavigationMarker,
        ),
    ).toBe(true);
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'زيوت لوحة التحكم' }),
    ).toHaveCount(0);
    await expect(
        page
            .locator('main table')
            .filter({ hasText: 'زيوت لوحة التحكم' })
            .first(),
    ).toContainText('12');

    for (const canvas of await canvases.all()) {
        await expect(canvas.locator('..')).toHaveAttribute('dir', 'ltr');
    }

    await page.emulateMedia({ colorScheme: 'dark' });
    const chart = canvases.first();
    await expect
        .poll(() =>
            chart.evaluate((canvas: HTMLCanvasElement) => {
                const pixels = canvas
                    .getContext('2d')!
                    .getImageData(0, 0, canvas.width, canvas.height).data;
                let text = 0;

                for (let i = 0; i < pixels.length; i += 4) {
                    if (
                        pixels[i] === 229 &&
                        pixels[i + 1] === 231 &&
                        pixels[i + 2] === 235 &&
                        pixels[i + 3] === 255
                    ) {
                        text++;
                    }
                }

                return text;
            }),
        )
        .toBeGreaterThan(0);
    const background = await chart
        .locator('..')
        .locator('..')
        .evaluate((element) => {
            const sample = document.createElement('canvas').getContext('2d')!;
            sample.fillStyle = getComputedStyle(element).backgroundColor;
            sample.fillRect(0, 0, 1, 1);

            return [...sample.getImageData(0, 0, 1, 1).data].slice(0, 3) as [
                number,
                number,
                number,
            ];
        });
    expect(contrastRatio([229, 231, 235], background)).toBeGreaterThan(4.5);
    expect(contrastRatio([96, 165, 250], background)).toBeGreaterThan(3);
    expect(errors).toEqual([]);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);
});

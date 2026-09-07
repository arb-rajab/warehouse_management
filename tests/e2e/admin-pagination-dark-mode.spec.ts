import type { Locator } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';

async function elementContrast(locator: Locator): Promise<number> {
    const { color, backgroundColor } = await locator.evaluate((el) => {
        const style = window.getComputedStyle(el);

        const toRgb = (cssColor: string) => {
            const canvas = document.createElement('canvas');
            canvas.width = 1;
            canvas.height = 1;
            const ctx = canvas.getContext('2d') as CanvasRenderingContext2D;
            ctx.fillStyle = cssColor;
            ctx.fillRect(0, 0, 1, 1);

            return Array.from(ctx.getImageData(0, 0, 1, 1).data.slice(0, 3));
        };

        return {
            color: toRgb(style.color),
            backgroundColor: toRgb(style.backgroundColor),
        };
    });

    return contrastRatio(color, backgroundColor);
}

/**
 * Pagination.vue's active-page link and per-page selector each carry their
 * own explicit dark: color pairs (see admin-pagination-chevron-rtl.spec.ts
 * for the RTL half of this component's coverage) — this checks the dark
 * variants are actually readable, not just present.
 */
test('the pagination active-page link and per-page selector have readable contrast in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsAdmin(page);

    await page.goto('/admin/cell-logs');

    const activeLink = page.locator('nav .bg-gray-900').first();
    await expect(activeLink).toBeVisible();
    expect(await elementContrast(activeLink)).toBeGreaterThanOrEqual(4.5);

    const perPageSelect = page.locator('select');
    await expect(perPageSelect).toBeVisible();
    expect(await elementContrast(perPageSelect)).toBeGreaterThanOrEqual(4.5);
});

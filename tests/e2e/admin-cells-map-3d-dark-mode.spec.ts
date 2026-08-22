import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';

/**
 * The 3D scene's own background/floor colors are fixed hex values that never
 * adapt to the page's dark mode (see CellMap3D.vue's setupScene) — so the
 * controls legend needs its own opaque, theme-aware card backdrop rather
 * than floating bare text over the (always light) 3D canvas. This regressed
 * once already: light-gray text with no backdrop was unreadable against the
 * light floor once dark mode made the text itself light-colored too.
 */
test('the 3D map controls legend has readable contrast against its own backdrop in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsAdmin(page);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZV');
    await page.locator('#cells_count').fill('1');
    await page.locator('#flats_count').fill('1');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page).toHaveURL(/\/admin\/rows\/ZV$/);

    await page.goto('/admin/cells');
    await page.getByTitle('3D view').click();

    const legend = page.getByTestId('map-3d-controls-legend');
    await expect(legend).toBeVisible();

    const { color, backgroundColor } = await legend.evaluate((el) => {
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

    expect(contrastRatio(color, backgroundColor)).toBeGreaterThanOrEqual(4.5);
});

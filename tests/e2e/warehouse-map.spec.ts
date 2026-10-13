import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

/**
 * The warehouse map's browser-only behaviour: the Three.js scene, pointer and
 * keyboard handling, and the layout at desktop and phone sizes. The data is
 * whatever the seeded warehouse holds (the search word is read from it).
 */
async function aSearchableWord(page: Page): Promise<string> {
    const response = await page.request.get('/admin/warehouse-map/rows');
    const rows = (await response.json()) as Array<{
        cells: Array<{ pallet: { product_name: string } | null }>;
    }>;
    const name = rows.flatMap((row) => row.cells).find((cell) => cell.pallet)
        ?.pallet?.product_name;

    expect(name, 'the seeded warehouse holds a pallet').toBeTruthy();

    return (name as string).split(/\s+/)[0].toLowerCase();
}

test.describe('desktop', () => {
    test.use({ viewport: { width: 1920, height: 1080 } });

    test('loads, searches, switches to 3D, walks an aisle in guided mode and steps with W', async ({
        page,
    }) => {
        // the 3D scene renders with software WebGL on CI runners
        test.slow();

        const pageErrors: Error[] = [];
        page.on('pageerror', (error) => pageErrors.push(error));

        await loginAsAdmin(page);

        const word = await aSearchableWord(page);

        await page.goto('/admin/warehouse-map');

        const map = page.locator('.wms');

        await expect(map).toHaveAttribute('data-view', '2d');
        await expect(map).toHaveAttribute('data-input', 'mouse');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        // search: matches are highlighted, the rest dimmed, the result pill appears
        await page.locator('.wms-search__input').fill(word);
        await expect(page.locator('.wms-result-pill.is-shown')).toBeVisible();
        await expect(page.locator('.wms-tile.is-match').first()).toBeVisible();

        // 3D keeps the filter and shows the camera bar with a count on the aisles
        await page.locator('.wms-seg button[data-view="3d"]').click();
        await expect(map).toHaveAttribute('data-view', '3d');
        await expect(page.locator('canvas.wms-canvas')).toBeVisible();
        await expect(
            page.locator('.wms-camera-bar .wms-chip__count').first(),
        ).toBeVisible();
        await expect(page.locator('.wms-result-pill.is-shown')).toBeVisible();

        // no on-screen steering on a desktop, key hints instead
        await expect(page.locator('.wms-pad')).toBeHidden();
        await expect(page.locator('.wms-lift')).toBeHidden();
        await expect(page.locator('.wms-keys')).toBeVisible();

        // walk in: the first aisle chip, then guided mode (G) and a step (W)
        await page.locator('.wms-camera-bar .wms-chip').nth(1).click();
        await expect(map).toHaveAttribute('data-mode', 'walk', {
            timeout: 15_000,
        });
        await expect(page.locator('.wms-crosshair')).toBeVisible();

        await page.keyboard.press('g');
        await expect(map).toHaveAttribute('data-nav', 'guided');

        const caption = page.locator('.wms-minimap__caption');
        const before = await caption.textContent();

        for (let i = 0; i < 6; i++) {
            await page.keyboard.press('w');
            await page.waitForTimeout(450);
        }

        await expect
            .poll(() => caption.textContent(), { timeout: 5_000 })
            .not.toBe(before);

        // back to the overview, then to the plan with the filter intact
        await page.keyboard.press('o');
        await expect(map).toHaveAttribute('data-mode', 'overview', {
            timeout: 15_000,
        });
        await page.locator('.wms-seg button[data-view="2d"]').click();
        await expect(map).toHaveAttribute('data-view', '2d');
        await expect(page.locator('.wms-tile.is-match').first()).toBeVisible();

        expect(pageErrors).toEqual([]);
    });

    test('opens a rack from the plan, copies a deep link and follows it into 3D', async ({
        page,
        context,
    }) => {
        test.slow();
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');

        await page.locator('.wms-tile').first().click();
        await expect(
            page.locator('.wms-dialog .wms-shelf').first(),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Copy link' }).click();

        const link = await page.evaluate(() => navigator.clipboard.readText());

        expect(link).toMatch(/#slot=[A-Z]+\d+-\d+$/);

        await page.keyboard.press('Escape');
        await expect(page.locator('.wms-backdrop.is-open')).toHaveCount(0);

        await page.goto(link);
        await expect(page.locator('.wms')).toHaveAttribute('data-view', '3d', {
            timeout: 20_000,
        });
    });

    test('mirrors the whole page in Arabic', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await page.locator('.wms-icon-btn--lang').click();

        await expect(page.locator('.wms')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('.wms-brand__title')).toHaveText(
            'خريطة المستودع',
        );

        const top = await page.locator('.wms-brand').boundingBox();
        const actions = await page.locator('.wms-actions').boundingBox();

        // in right-to-left the brand sits at the right and the actions at the left
        expect((top?.x ?? 0) > (actions?.x ?? 0)).toBe(true);
    });
});

test.describe('phone', () => {
    test.use({
        viewport: { width: 390, height: 844 },
        hasTouch: true,
        isMobile: true,
    });

    test('keeps the top bar on one row, floors at the bottom, and no key hints', async ({
        page,
    }) => {
        test.slow();
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');

        const map = page.locator('.wms');

        await expect(map).toHaveAttribute('data-input', 'touch');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        const bar = await page.locator('.wms-topbar').boundingBox();

        expect(bar?.height ?? 999).toBeLessThan(70);

        await page.locator('.wms-seg button[data-view="3d"]').click();
        await expect(map).toHaveAttribute('data-view', '3d');

        const floors = await page.locator('.wms-floors').boundingBox();

        expect(floors?.y ?? 0).toBeGreaterThan(844 / 2);
        await expect(page.locator('.wms-keys')).toBeHidden();
    });

    test('walks with touch controls: joystick area, lift buttons and the guided pad', async ({
        page,
    }) => {
        test.slow();
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await page.locator('.wms-seg button[data-view="3d"]').click();
        await page.locator('.wms-camera-bar .wms-chip').nth(1).tap();

        const map = page.locator('.wms');

        await expect(map).toHaveAttribute('data-mode', 'walk', {
            timeout: 15_000,
        });
        await expect(page.locator('.wms-lift')).toBeVisible();
        await expect(page.locator('.wms-pad')).toBeHidden();

        await page
            .locator('.wms-walkbar .wms-chip', { hasText: 'Guided' })
            .tap();
        await expect(map).toHaveAttribute('data-nav', 'guided');
        await expect(page.locator('.wms-pad')).toBeVisible();
        await expect(page.locator('.wms-lift')).toBeHidden();
        await expect(page.locator('.wms-pad__btn')).toHaveCount(8);
    });
});

import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import {
    createRow,
    createWarehouse,
    deleteRow,
    warehouseId,
} from './support/warehouses';

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
        await expect(map).toHaveAttribute('data-captured', 'true');
        await expect(page.locator('canvas.wms-canvas')).toBeFocused();

        // data-mode already reads "walk" while the camera is still flying in,
        // and keys are dropped (not queued) until it lands: press G until it
        // takes, which a dropped press cannot undo
        await expect(async () => {
            await page.keyboard.press('g');
            await expect(map).toHaveAttribute('data-nav', 'guided', {
                timeout: 500,
            });
        }).toPass({ timeout: 15_000 });
        await expect(map).toHaveAttribute('data-captured', 'false');

        const caption = page.locator('.wms-minimap__caption');
        const before = await caption.textContent();

        // Guided movement ignores keys during a glide. Keep stepping until
        // the rendered location changes, including on software-rendered CI.
        await expect
            .poll(
                async () => {
                    await page.keyboard.press('w');

                    return caption.textContent();
                },
                { timeout: 15_000, intervals: [500] },
            )
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

    test('filters by several products from the picker, and opens a products link', async ({
        page,
    }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        const trigger = page.locator('.wms-products__trigger');
        const panel = page.locator('.wms-products__panel');
        const options = page.locator('.wms-products__option');

        await trigger.click();
        await expect(panel).toBeVisible();
        await expect(page.locator('.wms-products__search')).toBeFocused();
        expect(
            await options.count(),
            'the seeded warehouse holds two products',
        ).toBeGreaterThanOrEqual(2);

        const ids = await page
            .locator('.wms-products__check')
            .evaluateAll((boxes) =>
                boxes
                    .slice(0, 2)
                    .map((box) => (box as HTMLElement).dataset.productId),
            );

        // by id: a checked product moves up into the pinned section
        const box = (id: string | undefined) =>
            page.locator(`.wms-products__check[data-product-id="${id}"]`);

        await box(ids[0]).check();
        await expect(page.locator('.wms-result-pill.is-shown')).toBeVisible();
        await box(ids[1]).check();
        await expect(trigger).toHaveText('2 products');
        await expect(page.locator('.wms-products__head')).toBeVisible();
        await expect(trigger).toHaveClass(/is-on/);
        await expect(page.locator('.wms-tile.is-match').first()).toBeVisible();

        // the checklist stays inside the window
        const bounds = await panel.boundingBox();

        expect(bounds!.x).toBeGreaterThanOrEqual(0);
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(1920);

        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await expect(trigger).toBeFocused();

        await page.goto(`/admin/warehouse-map#products=${ids.join(',')}`);
        await expect(page.locator('.wms-result-pill.is-shown')).toBeVisible();
        await expect(trigger).toHaveClass(/is-on/);
    });

    test('switches warehouses from the toolbar, keeps the search and the choice in the URL, and Back returns', async ({
        page,
    }) => {
        test.slow();
        await loginAsAdmin(page);
        await createWarehouse(page, 'E2E Map Depot');
        await createRow(page, 'E2E Map Depot', 'ZM');
        const depotId = await warehouseId(page, 'E2E Map Depot');

        try {
            await page.goto('/admin/warehouse-map');
            await expect(page.locator('.wms-tile').first()).toBeVisible();

            const trigger = page.locator('.wms-warehouses__trigger');
            const search = page.locator('.wms-search__input');
            const depot = page.getByRole('menuitemcheckbox', {
                name: 'E2E Map Depot',
            });

            // the switcher in the map is the only warehouse control
            await expect(trigger).toHaveText('All warehouses');
            await expect(
                page.locator('[data-testid="warehouse-map-bar"]'),
            ).toBeHidden();

            await search.fill('zz');
            await trigger.click();
            await expect(page.locator('.wms-warehouses__panel')).toBeVisible();
            await expect(
                page.getByRole('menuitemcheckbox', { name: 'All warehouses' }),
            ).toHaveAttribute('aria-checked', 'true');
            await depot.click();
            await expect(depot).toHaveAttribute('aria-checked', 'true');
            await page.locator('.wms-warehouses__apply').click();

            await expect(page).toHaveURL(
                // Inertia serializes the array with an index (warehouse_ids[0]=6);
                // the server accepts that and the empty-bracket form alike
                new RegExp(`warehouse_ids(%5B\\d*%5D|\\[\\d*\\])=${depotId}`),
            );
            await expect(trigger).toHaveText('E2E Map Depot');
            await expect(page.locator('.wms-tile')).toHaveCount(1);
            await expect(
                page.locator('.wms-tile[data-row="ZM"]'),
            ).toBeVisible();
            // loaded in place: the search is still there
            await expect(search).toHaveValue('zz');
            // the touch hint shares the class, so pick the status line
            await expect(
                page.locator('.wms-hint:not(.wms-hint--touch)'),
            ).toContainText('E2E Map Depot');

            await page.reload();
            await expect(trigger).toHaveText('E2E Map Depot');
            await expect(page.locator('.wms-tile')).toHaveCount(1);

            await page.goBack();
            await expect(page).not.toHaveURL(/warehouse_ids/);
            await expect(trigger).toHaveText('All warehouses');
            await expect
                .poll(() => page.locator('.wms-tile').count())
                .toBeGreaterThan(1);

            // the keyboard: the down arrow opens, Escape leaves the choice as it was
            await trigger.focus();
            await page.keyboard.press('ArrowDown');
            await expect(
                page.getByRole('menuitemcheckbox', { name: 'All warehouses' }),
            ).toBeFocused();
            await page.keyboard.press('End');
            await page.keyboard.press('Space');
            await page.keyboard.press('Escape');
            await expect(page.locator('.wms-warehouses__panel')).toBeHidden();
            await expect(trigger).toBeFocused();
            await expect(page).not.toHaveURL(/warehouse_ids/);
        } finally {
            await deleteRow(page, depotId, 'ZM');
            await page.goto('/admin/warehouses');
            page.once('dialog', (dialog) => dialog.accept());
            await page
                .getByRole('row', { name: /E2E Map Depot/ })
                .getByRole('button', { name: 'Delete' })
                .click();
            await expect(
                page.getByRole('row', { name: /E2E Map Depot/ }),
            ).toHaveCount(0);
        }
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

    test('starts the rows from the right of the plan, standing up and lying down', async ({
        page,
    }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        const world = page.locator('.wms-plan__world');
        // Read both rectangles in one browser task: the world is animated,
        // so separate protocol calls can compare two different camera positions.
        const delta = (first: string, second: string, axis: 'x' | 'y') =>
            page.evaluate(
                ({ first, second, axis }) => {
                    const a = document.querySelector(first)!;
                    const b = document.querySelector(second)!;

                    return (
                        a.getBoundingClientRect()[axis] -
                        b.getBoundingClientRect()[axis]
                    );
                },
                { first, second, axis },
            );
        const tile = (col: number) =>
            `.wms-tile[data-row="A"][data-col="${col}"]`;

        // auto -> vertical: aisle A-B at the right, A on the right of its aisle, column 1 at the entrance (bottom)
        await page.keyboard.press('r');
        await expect(world).not.toHaveClass(/is-horizontal/);
        await expect(world).not.toHaveClass(/is-animating/);
        expect(
            await delta('.wms-pair', '.wms-pair:nth-child(2)', 'x'),
        ).toBeGreaterThan(0);
        expect(
            await delta('.wms-rack-strip--a', '.wms-rack-strip--b', 'x'),
        ).toBeGreaterThan(0);
        expect(await delta(tile(1), tile(2), 'y')).toBeGreaterThan(0);

        // vertical -> horizontal: entrance at the right, column 1 at the right, aisle A still at the top
        await page.keyboard.press('r');
        await expect(world).toHaveClass(/is-horizontal/);
        await expect(world).not.toHaveClass(/is-animating/);
        expect(
            await delta('.wms-plan__entrance', '.wms-plan__pairs', 'x'),
        ).toBeGreaterThan(0);
        expect(await delta(tile(1), tile(2), 'x')).toBeGreaterThan(0);
        expect(
            await delta('.wms-rack-strip--a', '.wms-rack-strip--b', 'y'),
        ).toBeLessThan(0);
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

    test('opens the product picker across the whole bar on a phone', async ({
        page,
    }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        await expect(page.locator('.wms-products')).toBeHidden();
        await page.locator('.wms-search-toggle').tap();
        await page.locator('.wms-products__trigger').tap();

        const panel = page.locator('.wms-products__panel');

        await expect(panel).toBeVisible();

        const box = await panel.boundingBox();

        expect(box!.x).toBeGreaterThanOrEqual(0);
        expect(box!.x + box!.width).toBeLessThanOrEqual(390);

        const first = page.locator('.wms-products__option input').first();

        await first.tap();
        await expect(first).toBeChecked();
        await expect(page.locator('.wms-products__trigger')).toHaveClass(
            /is-on/,
        );
        await expect(page.locator('.wms-tile.is-dim').first()).toBeAttached();
    });

    test('switches warehouses from the icon on a phone, the menu across the bar', async ({
        page,
    }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        const trigger = page.locator('.wms-warehouses__trigger');

        // no room for the name: the icon opens the menu, the name is in its label
        await expect(trigger).toBeVisible();
        await expect(page.locator('.wms-warehouses__label')).toBeHidden();
        await expect(trigger).toHaveAttribute(
            'aria-label',
            'Warehouse: All warehouses',
        );

        const bar = await page.locator('.wms-topbar').boundingBox();

        expect(bar?.height ?? 999).toBeLessThan(70);

        await trigger.tap();

        const panel = page.locator('.wms-warehouses__panel');

        await expect(panel).toBeVisible();
        await expect(page.locator('.wms-warehouses__head')).toContainText(
            'All warehouses',
        );

        const box = await panel.boundingBox();

        expect(box!.x).toBeGreaterThanOrEqual(0);
        expect(box!.x + box!.width).toBeLessThanOrEqual(390);

        // a tap outside the menu applies the ticks
        await page.getByRole('menuitemcheckbox', { name: 'Default' }).tap();
        await page.locator('.wms-stage--plan').tap({
            position: { x: 30, y: 500 },
        });

        await expect(panel).toBeHidden();
        await expect(page).toHaveURL(/warehouse_ids/);
        await expect(trigger).toHaveAttribute(
            'aria-label',
            'Warehouse: Default',
        );
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

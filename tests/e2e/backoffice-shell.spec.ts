import type { Locator, Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsAdmin, loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';

/**
 * The WMS and the store control panel share one shell
 * (resources/js/components/BackofficeShell.vue, .ai/rules/layouts.md):
 * the same sidebar and header, the phone drawer, and the icon rail the
 * warehouse map starts folded to.
 */

async function textContrast(element: Locator): Promise<number> {
    const { foreground, background } = await element.evaluate((el) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const ctx = canvas.getContext('2d')!;
        const ancestors: Element[] = [];

        for (let node: Element | null = el; node; node = node.parentElement) {
            ancestors.unshift(node);
        }

        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, 1, 1);

        for (const ancestor of ancestors) {
            ctx.fillStyle = getComputedStyle(ancestor).backgroundColor;
            ctx.fillRect(0, 0, 1, 1);
        }

        const background = Array.from(
            ctx.getImageData(0, 0, 1, 1).data.slice(0, 3),
        );
        ctx.fillStyle = getComputedStyle(el).color;
        ctx.fillRect(0, 0, 1, 1);
        const foreground = Array.from(
            ctx.getImageData(0, 0, 1, 1).data.slice(0, 3),
        );

        return { foreground, background };
    });

    return contrastRatio(foreground, background);
}

async function sidebarWidth(page: Page, id: string): Promise<number> {
    return (await page.locator(`#${id}`).boundingBox())?.width ?? 0;
}

test.describe('desktop', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('both back offices render the same sidebar and header', async ({
        page,
    }) => {
        await loginAsAdmin(page);

        const wms = page.locator('#admin-sidebar');
        await expect(wms).toBeVisible();
        await expect(wms.getByTestId('sidebar-brand')).toHaveText('Store name');
        await expect(wms.getByTestId('sidebar-area')).toHaveText(
            'Warehouse Admin',
        );
        await expect(
            page
                .getByRole('navigation', { name: 'Warehouse menu' })
                .getByRole('link'),
        ).toHaveText([
            'Dashboard',
            'Warehouses',
            'Rows',
            'Pallets',
            'Receipts',
            'Warehouse map',
            'Products',
            'Cell Log',
            'Verification Rounds',
            'Users',
            'Settings',
            'Help',
        ]);
        await expect(
            page.locator('header').getByRole('button', { name: 'Arabic' }),
        ).toBeVisible();
        await expect(
            page.locator('header').getByRole('link', { name: 'Help' }),
        ).toBeVisible();
        await expect(
            wms.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();
        expect(await sidebarWidth(page, 'admin-sidebar')).toBe(256);

        await loginAsStoreAdmin(page);

        const store = page.locator('#store-admin-sidebar');
        await expect(store).toBeVisible();
        await expect(store.getByTestId('sidebar-brand')).toHaveText(
            'Store name',
        );
        await expect(store.getByTestId('sidebar-area')).toHaveText(
            'Store Admin',
        );
        await expect(
            page.getByRole('navigation', { name: 'Store admin menu' }),
        ).toBeVisible();
        await expect(
            page.locator('header').getByRole('link', {
                name: 'Browse website',
            }),
        ).toBeVisible();
        await expect(
            store.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();
        expect(await sidebarWidth(page, 'store-admin-sidebar')).toBe(256);
    });

    test('Arabic puts the sidebar on the right and flips the rail and crumb icons', async ({
        page,
    }) => {
        await loginAsAdmin(page);

        const toggle = page.getByTestId('sidebar-collapse').locator('svg');
        await expect(toggle).toHaveCSS('rotate', 'none');

        await switchLocale(page, 'Arabic');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

        const sidebar = await page.locator('#admin-sidebar').boundingBox();
        const main = await page.locator('main#main').boundingBox();

        expect(sidebar!.x).toBeGreaterThan(main!.x);
        await expect(
            page.getByTestId('sidebar-collapse').locator('svg'),
        ).toHaveCSS('rotate', '180deg');
    });

    test('the warehouse map starts with the sidebar folded and remembers an unfolded one', async ({
        page,
    }) => {
        await loginAsAdmin(page);
        await page.goto('/admin/warehouse-map');
        await expect(page.locator('.wms-tile').first()).toBeVisible();

        const menu = page.getByRole('navigation', { name: 'Warehouse menu' });

        expect(await sidebarWidth(page, 'admin-sidebar')).toBe(64);
        await expect(
            menu.getByRole('link', { name: 'Warehouse map' }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(
            menu.getByRole('link', { name: 'Warehouse map' }),
        ).toHaveAttribute('title', 'Warehouse map');

        await page.getByRole('button', { name: 'Expand sidebar' }).click();
        await expect.poll(() => sidebarWidth(page, 'admin-sidebar')).toBe(256);

        // The map's toolbar fits beside the open sidebar: nothing runs under
        // the 2D/3D switch and the search box stays usable.
        const filters = await page.locator('.wms-filters').boundingBox();
        const actions = await page.locator('.wms-actions').boundingBox();
        const search = await page.locator('.wms-search').boundingBox();
        const chips = await page.locator('.wms-chips').boundingBox();

        expect(filters!.x + filters!.width).toBeLessThanOrEqual(actions!.x);
        expect(search!.width).toBeGreaterThanOrEqual(150);

        // The chips scroll inside the filter bar (overflow-x: auto), so a chip
        // past the visible edge is clipped, not drawn under the actions. The
        // brand now also holds the warehouse switcher, which narrows the bar
        // beside an open sidebar; what must hold is that the scroller itself
        // ends before the actions, not that every chip sits left of them.
        expect(chips!.x + chips!.width).toBeLessThanOrEqual(actions!.x);

        await page.reload();
        await expect.poll(() => sidebarWidth(page, 'admin-sidebar')).toBe(256);

        // Other pages keep their own choice: still open.
        await page.goto('/admin/rows');
        expect(await sidebarWidth(page, 'admin-sidebar')).toBe(256);

        await page.getByRole('button', { name: 'Collapse sidebar' }).click();
        await expect.poll(() => sidebarWidth(page, 'admin-sidebar')).toBe(64);
        await expect(
            page
                .getByRole('navigation', { name: 'Warehouse menu' })
                .getByRole('link', { name: 'Rows', exact: true }),
        ).toHaveAttribute('aria-current', 'page');
    });

    test('the sidebar keeps readable contrast in dark mode', async ({
        page,
    }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await loginAsAdmin(page);

        const menu = page.getByRole('navigation', { name: 'Warehouse menu' });
        const active = menu.getByRole('link', { name: 'Dashboard' });
        const idle = menu.getByRole('link', { name: 'Rows', exact: true });

        expect(await textContrast(active)).toBeGreaterThanOrEqual(4.5);
        expect(await textContrast(idle)).toBeGreaterThanOrEqual(4.5);
        expect(
            await textContrast(page.getByTestId('sidebar-area')),
        ).toBeGreaterThanOrEqual(4.5);
    });
});

test.describe('phone', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('the drawer closes on Escape and gives focus back to the menu button', async ({
        page,
    }) => {
        await loginAsAdmin(page);

        const sidebar = page.locator('#admin-sidebar');
        const openMenu = page.getByRole('button', { name: 'Open menu' });

        await expect(sidebar).toBeHidden();
        await openMenu.click();
        await expect(sidebar).toBeVisible();
        await expect(
            sidebar.getByRole('button', { name: 'Close menu' }),
        ).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(sidebar).toBeHidden();
        await expect(openMenu).toBeFocused();
        await expect(openMenu).toHaveAttribute('aria-expanded', 'false');
    });

    test('the store drawer opens from the header too, with the language switcher inside', async ({
        page,
    }) => {
        await loginAsStoreAdmin(page);

        const sidebar = page.locator('#store-admin-sidebar');
        await page.getByRole('button', { name: 'Open menu' }).click();
        await expect(sidebar).toBeVisible();
        await expect(
            sidebar.getByRole('button', { name: 'Arabic' }),
        ).toBeVisible();
        await expect(
            sidebar.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();

        await sidebar.getByRole('button', { name: 'Close menu' }).click();
        await expect(sidebar).toBeHidden();
    });
});

test('the sign-in page shows the logo and the shared brand name in light and dark, and dark mode darkens native controls', async ({
    page,
}) => {
    await page.goto('/admin/login');

    const brand = page.getByTestId('auth-brand');
    await expect(page.getByTestId('auth-logo')).toBeVisible();
    await expect(brand).not.toBeEmpty();
    await expect(page.getByTestId('auth-area')).toBeVisible();
    await expect(page.locator('html')).toHaveCSS('color-scheme', 'normal');
    expect(await textContrast(brand)).toBeGreaterThanOrEqual(4.5);

    await page.emulateMedia({ colorScheme: 'dark' });

    await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
    expect(await textContrast(brand)).toBeGreaterThanOrEqual(4.5);
    expect(
        await textContrast(page.getByTestId('auth-area')),
    ).toBeGreaterThanOrEqual(4.5);
});

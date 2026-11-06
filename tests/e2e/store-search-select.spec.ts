import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { colorsOf } from './support/editor';
import { switchLocale } from './support/nav';
import { pickSearchOption } from './support/searchSelect';

/**
 * Browser-only behaviour of StoreSearchSelect (jsdom has no layout): the open
 * list is a fixed layer that no table or dialog clips, it follows the inline
 * start edge in RTL, it is a top-anchored sheet on a phone, and the keyboard
 * flow moves focus out of and back to the field.
 */
test('the open list overlaps the order table instead of being clipped by it', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    await page.locator('#product-1').click();
    const panel = page.locator('[data-search-select-layer] [role="listbox"]');
    await page.locator('input[role="combobox"]').fill('BROWSER-ORDER');
    await expect(panel.getByRole('option').first()).toBeVisible();

    const table = await page.locator('table').first().boundingBox();
    const open = await page
        .locator('[data-search-select-layer] input[role="combobox"]')
        .boundingBox();
    const rows = await panel.getByRole('option').first().boundingBox();
    expect(table).not.toBeNull();
    expect(rows).not.toBeNull();
    // The list reaches past the table's bottom edge and is still fully hit-testable there.
    expect(rows!.y + rows!.height).toBeGreaterThan(table!.y + table!.height);
    const hit = await page.evaluate(
        ({ x, y }) =>
            document
                .elementFromPoint(x, y)
                ?.closest('[data-search-select-layer]') !== null,
        { x: rows!.x + 20, y: rows!.y + rows!.height - 4 },
    );
    expect(hit).toBe(true);
    expect(open).not.toBeNull();
});

test('the list is anchored to the field edge in Arabic and stays on screen', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const trigger = page.locator('#customer_id');
    await trigger.click();
    await expect(page.locator('input[role="combobox"]')).toBeVisible();
    const field = await trigger.boundingBox();
    const panel = await page
        .locator('[data-search-select-layer] input[role="combobox"]')
        .evaluate((input) => {
            const box = input
                .closest('[id$="-panel"]')!
                .getBoundingClientRect();

            return { left: box.left, right: box.right };
        });

    // The inline start edge in RTL is the right one: the panel hangs from it.
    expect(
        Math.abs(panel.right - (field!.x + field!.width)),
    ).toBeLessThanOrEqual(2);
    expect(panel.left).toBeGreaterThanOrEqual(0);
    expect(panel.right).toBeLessThanOrEqual(page.viewportSize()!.width + 1);
});

test('the keyboard picks a row and returns focus to the field', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    const trigger = page.locator('#customer_id');
    await trigger.focus();
    await page.keyboard.press('ArrowDown');
    const search = page.locator('input[role="combobox"]');
    await expect(search).toBeFocused();
    await search.fill('Browser Buyer');
    await expect(
        page.getByRole('option', { name: /Browser Buyer/ }),
    ).toBeVisible();
    // The list of the empty query may still be on screen: wait for the filtered one.
    await expect(
        page.locator('[data-search-select-layer] [role="option"]'),
    ).toHaveCount(1);
    await page.keyboard.press('ArrowDown');
    await expect(search).toHaveAttribute(
        'aria-activedescendant',
        /option-\d+$/,
    );
    await page.keyboard.press('Enter');
    await expect(search).toHaveCount(0);
    await expect(trigger).toBeFocused();
    await expect(trigger).toContainText('Browser Buyer');
    await expect(
        page.locator('input[type="hidden"][name="customer_id"]'),
    ).not.toHaveValue('');

    await page.keyboard.press('ArrowDown');
    await expect(search).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(search).toHaveCount(0);
    await expect(trigger).toBeFocused();
});

test('on a phone the list is a top sheet over a backdrop and nothing overflows sideways', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 780 });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    await page.locator('#customer_id').click();

    await expect(
        page.locator('[data-testid="search-select-backdrop"]'),
    ).toBeVisible();
    const panel = await page
        .locator('input[role="combobox"]')
        .evaluate((input) => {
            const box = input
                .closest('[id$="-panel"]')!
                .getBoundingClientRect();

            return { left: box.left, right: box.right, top: box.top };
        });
    expect(panel.left).toBeGreaterThanOrEqual(0);
    expect(panel.right).toBeLessThanOrEqual(390);
    expect(panel.top).toBeLessThan(40);
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);

    await pickSearchOption(
        page,
        page.locator('#customer_id'),
        'Browser Buyer',
        /Browser Buyer/,
    );
    await expect(
        page.locator('[data-testid="search-select-backdrop"]'),
    ).toHaveCount(0);
});

test('rows stay readable in dark mode', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    await page.locator('#customer_id').click();
    await page.locator('input[role="combobox"]').fill('Browser Buyer');
    const row = page.getByRole('option', { name: /Browser Buyer/ }).first();
    await expect(row).toBeVisible();

    // The computed colour is an `oklch()` in current Chromium, so read it as rgb.
    const panel = await colorsOf(
        page,
        '[data-search-select-layer] [id$="-panel"]',
    );
    // neutral-800: a dark panel, not the white of light mode.
    expect(panel.background).toEqual([38, 38, 38]);
});

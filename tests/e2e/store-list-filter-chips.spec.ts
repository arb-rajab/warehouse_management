import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { switchLocale } from './support/nav';
import { ensureStoreRtlLanguage } from './support/storeFixtures';

const list = '/temp/admin/all_orders';
const filtered = `${list}?payment_status=paid&from=2026-11-01&to=2026-11-05`;

/** The chip list sits right after the filter form and holds chips plus Clear. */
function chipList(page: Page): Locator {
    return page.locator('form#store-list-filters + ul');
}

test('applied filters show as chips that wrap on a phone and remove one by one', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await loginAsStoreAdmin(page);
    await page.goto(filtered);

    const chips = chipList(page).locator('li:has(span)');
    await expect(chips).toHaveCount(3);
    await expect(chips.nth(1)).toContainText('2026-11-01');
    await expect(
        page.locator('form#store-list-filters > button[type="button"]'),
    ).toContainText('3');

    // Narrow screens wrap the chips instead of scrolling the page sideways.
    const tops = await chipList(page)
        .locator('li')
        .evaluateAll((items) =>
            items.map((item) => Math.round(item.getBoundingClientRect().top)),
        );
    expect(new Set(tops).size).toBeGreaterThan(1);
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);

    await chips
        .nth(1)
        .getByRole('button', { name: /^Remove filter: / })
        .click();
    await expect(page).toHaveURL(/payment_status=paid/);
    await expect(page).toHaveURL(/to=2026-11-05/);
    await expect(page).not.toHaveURL(/from=/);
    await expect(chips).toHaveCount(2);

    await chipList(page)
        .getByRole('button', { name: 'Clear filters', exact: true })
        .click();
    await expect(page).not.toHaveURL(/payment_status=|to=/);
    await expect(chipList(page)).toHaveCount(0);
});

test('chips and their remove buttons mirror in Arabic', async ({ page }) => {
    test.setTimeout(60_000);
    await page.setViewportSize({ width: 1280, height: 800 });
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto(filtered);

    const order = async () => {
        const first = chipList(page).locator('li:has(span)').first();
        const second = chipList(page).locator('li:has(span)').nth(1);
        const [a, b, label, remove] = await Promise.all([
            first.boundingBox(),
            second.boundingBox(),
            first.locator('span').boundingBox(),
            first.locator('button').boundingBox(),
        ]);

        return {
            firstBeforeSecond: a!.x < b!.x,
            labelBeforeRemove: label!.x < remove!.x,
        };
    };

    await expect(chipList(page).locator('li:has(span)')).toHaveCount(3);
    expect(await order()).toEqual({
        firstBeforeSecond: true,
        labelBeforeRemove: true,
    });

    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(chipList(page).locator('li:has(span)')).toHaveCount(3);
    expect(await order()).toEqual({
        firstBeforeSecond: false,
        labelBeforeRemove: false,
    });

    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
});

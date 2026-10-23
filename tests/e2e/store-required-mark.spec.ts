import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import { ensureStoreRtlLanguage } from './support/storeFixtures';

/**
 * The required-field mark (RequiredMark.vue, <x-required-mark />) and its
 * legend in a real browser: readable red in light and dark mode, announced as
 * "required" (not "star"), and placed after the label in both directions
 * (.ai/rules/form-required-fields.md).
 */
async function markContrast(mark: Locator): Promise<number> {
    const { color, background } = await mark.evaluate((element) => {
        const toRgb = (cssColor: string) => {
            const canvas = document.createElement('canvas');
            canvas.width = 1;
            canvas.height = 1;
            const context = canvas.getContext('2d') as CanvasRenderingContext2D;
            context.fillStyle = cssColor;
            context.fillRect(0, 0, 1, 1);

            return Array.from(
                context.getImageData(0, 0, 1, 1).data.slice(0, 3),
            );
        };
        let node: Element | null = element;
        let background = 'rgb(255, 255, 255)';

        while (node) {
            const value = window.getComputedStyle(node).backgroundColor;

            if (value !== 'rgba(0, 0, 0, 0)' && value !== 'transparent') {
                background = value;
                break;
            }

            node = node.parentElement;
        }

        return {
            color: toRgb(window.getComputedStyle(element).color),
            background: toRgb(background),
        };
    });

    return contrastRatio(color, background);
}

async function openStaffCreate(page: Page): Promise<void> {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/staffs/create');
    await expect(page.locator('[data-required-legend]')).toBeVisible();
}

test('the staff form marks its required fields, announces "required" and explains the asterisk', async ({
    page,
}) => {
    await openStaffCreate(page);

    await expect(page.locator('[data-required-legend]')).toHaveText(
        '* Required fields',
    );
    await expect(page.locator('#name')).toHaveAccessibleName('Name required');
    await expect(page.locator('#role_id')).toHaveAccessibleName(
        'Role required',
    );
    const mark = page.locator('label[for="name"] [data-required-mark]');
    await expect(mark.locator('[aria-hidden="true"]')).toHaveText('*');
    // The screen-reader text takes no room on screen.
    const hidden = await mark.locator('.sr-only').boundingBox();
    expect(hidden?.width ?? 0).toBeLessThanOrEqual(1);
});

for (const colorScheme of ['light', 'dark'] as const) {
    test(`the required mark is readable in ${colorScheme} mode`, async ({
        page,
    }) => {
        await page.emulateMedia({ colorScheme });
        await openStaffCreate(page);

        const mark = page.locator('label[for="name"] [data-required-mark]');
        expect(await markContrast(mark)).toBeGreaterThanOrEqual(4.5);
        expect(
            await markContrast(
                page.locator('[data-required-legend] [aria-hidden="true"]'),
            ),
        ).toBeGreaterThanOrEqual(4.5);
    });
}

test('the required mark follows the label in English and Arabic', async ({
    page,
}) => {
    test.setTimeout(60_000);
    ensureStoreRtlLanguage();
    await openStaffCreate(page);
    const mark = page.locator('label[for="name"] [data-required-mark]');

    await expect(mark).toHaveCSS('margin-left', '2px');
    await expect(mark).toHaveCSS('margin-right', '0px');
    const ltr = await page.locator('label[for="name"]').boundingBox();
    const ltrMark = await mark.boundingBox();
    expect(ltrMark!.x).toBeGreaterThan(ltr!.x);

    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(mark).toHaveCSS('margin-right', '2px');
    await expect(mark).toHaveCSS('margin-left', '0px');
    await expect(mark.locator('.sr-only')).toHaveText('مطلوب');
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
});

test('the storefront registration form marks its required fields in a readable red', async ({
    page,
}) => {
    await page.goto('/temp/users/registration');

    await expect(
        page.locator('#reg-form [data-required-legend]'),
    ).toBeVisible();
    await expect(page.locator('#reg-form [data-required-mark]')).toHaveCount(7);
    await expect(page.locator('#reg-form input[name="name"]')).toHaveAttribute(
        'required',
        '',
    );
    expect(
        await markContrast(
            page.locator('#reg-form [data-required-mark]').first(),
        ),
    ).toBeGreaterThanOrEqual(4.5);
});

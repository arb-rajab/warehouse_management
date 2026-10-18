import type { Locator } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { openAccountMenu, switchLocale } from './support/nav';

async function badgeContrast(badge: Locator): Promise<number> {
    const { foreground, background } = await badge.evaluate((el) => {
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

for (const locale of ['en', 'ar']) {
    for (const colorScheme of ['light', 'dark'] as const) {
        test(`product status fits beside long names in ${locale}, ${colorScheme}`, async ({
            page,
        }) => {
            await page.emulateMedia({ colorScheme });
            await loginAsAdmin(page);

            if (locale === 'ar') {
                await openAccountMenu(page);
                await switchLocale(page, 'Arabic');
            }

            await page.setViewportSize({ width: 390, height: 844 });
            await page.route('**/admin/products/search?*', (route) =>
                route.fulfill({
                    json: {
                        data: [true, false].map((active, index) => ({
                            id: index + 1,
                            name: 'VeryLongProductName'.repeat(12),
                            ar_name: 'اسممنتجطويل'.repeat(12),
                            active,
                        })),
                        meta: {
                            current_page: 1,
                            last_page: 1,
                            total: 2,
                            per_page: 20,
                            links: [],
                        },
                    },
                }),
            );
            await page.goto('/admin/products');

            for (const triggerId of [
                'products-quick-search',
                'filter-product',
            ]) {
                if (triggerId === 'filter-product') {
                    await page
                        .getByRole('button', {
                            name: locale === 'ar' ? 'الفلاتر' : 'Filters',
                            exact: true,
                        })
                        .click();
                }

                await page.locator(`#${triggerId}`).click();
                const options = page.getByRole('listbox').getByRole('option');
                await expect(options).toHaveCount(2);

                for (let index = 0; index < 2; index++) {
                    const option = options.nth(index);
                    const badge = option.getByTestId('product-active-badge');
                    await badge.scrollIntoViewIfNeeded();
                    await expect(badge).toBeVisible();
                    await expect(badge).toHaveText(
                        locale === 'ar'
                            ? index === 0
                                ? 'نشط'
                                : 'غير نشط'
                            : index === 0
                              ? 'Active'
                              : 'Inactive',
                    );
                    const box = (await badge.boundingBox())!;
                    const parent = (await option.boundingBox())!;
                    expect(box.x).toBeGreaterThanOrEqual(Math.max(parent.x, 0));
                    expect(box.x + box.width).toBeLessThanOrEqual(
                        Math.min(parent.x + parent.width, 390),
                    );
                    expect(await badgeContrast(badge)).toBeGreaterThanOrEqual(
                        4.5,
                    );
                }

                await page.keyboard.press('Escape');
            }
        });
    }
}

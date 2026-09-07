import type { Locator } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';

/**
 * Both cell-log arrows (the transferred-out ArrowRight icon and the
 * stored/opened/etc. state-change "→" glyph) sit directly on the page
 * background rather than their own card backdrop, so this walks up to the
 * nearest ancestor with an actual background instead of assuming the
 * element paints one itself — see admin-cells-map-3d-dark-mode.spec.ts for
 * the "own opaque backdrop" case this deliberately isn't.
 */
async function contrastAgainstEffectiveBackground(
    locator: Locator,
): Promise<number> {
    const { color, backgroundColor } = await locator.evaluate((el) => {
        const toRgb = (cssColor: string) => {
            const canvas = document.createElement('canvas');
            canvas.width = 1;
            canvas.height = 1;
            const ctx = canvas.getContext('2d') as CanvasRenderingContext2D;
            ctx.fillStyle = cssColor;
            ctx.fillRect(0, 0, 1, 1);

            return Array.from(ctx.getImageData(0, 0, 1, 1).data.slice(0, 3));
        };

        function effectiveBackground(node: Element): string {
            let current: Element | null = node;

            while (current) {
                const bg = window.getComputedStyle(current).backgroundColor;

                if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {
                    return bg;
                }

                current = current.parentElement;
            }

            return window.getComputedStyle(document.body).backgroundColor;
        }

        return {
            color: toRgb(window.getComputedStyle(el).color),
            backgroundColor: toRgb(effectiveBackground(el)),
        };
    });

    return contrastRatio(color, backgroundColor);
}

test('the cell log transfer arrow icon has readable contrast against the page background in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsAdmin(page);

    await page.goto('/admin/cell-logs?action[]=transferred_out');

    const arrow = page.locator('tbody tr svg.lucide-arrow-right').first();
    await expect(arrow).toBeVisible();

    // 3:1 is WCAG's non-text (graphical object) contrast minimum, not the
    // 4.5:1 used for the text checks elsewhere in this suite.
    expect(await contrastAgainstEffectiveBackground(arrow)).toBeGreaterThanOrEqual(
        3,
    );
});

test('the cell log state-change arrow has readable contrast against the page background in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsAdmin(page);

    await page.goto('/admin/cell-logs?action[]=stored');

    const arrow = page.locator('tbody tr').first().getByText('→');
    await expect(arrow).toBeVisible();

    expect(await contrastAgainstEffectiveBackground(arrow)).toBeGreaterThanOrEqual(
        4.5,
    );
});

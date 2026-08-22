import { expect, test } from '@playwright/test';
import { contrastRatio } from './support/contrast';

test('login form text has readable contrast against its input background in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/login');

    const email = page.locator('#email');
    await email.fill('someone@example.com');

    const { color, backgroundColor } = await email.evaluate((el) => {
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

    const ratio = contrastRatio(color, backgroundColor);

    expect(ratio).toBeGreaterThanOrEqual(4.5);
});

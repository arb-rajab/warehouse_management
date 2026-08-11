import { expect, test } from '@playwright/test';

function relativeLuminance([r, g, b]: number[]): number {
    const channel = (c: number) => {
        const s = c / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function contrastRatio(a: number[], b: number[]): number {
    const [lighter, darker] = [relativeLuminance(a), relativeLuminance(b)].sort(
        (x, y) => y - x,
    );

    return (lighter + 0.05) / (darker + 0.05);
}

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

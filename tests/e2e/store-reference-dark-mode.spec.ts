import type { Locator } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';

async function expectReadable(locator: Locator): Promise<void> {
    const { color, backgroundColor } = await locator.evaluate((el) => {
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
    expect(contrastRatio(color, backgroundColor)).toBeGreaterThanOrEqual(4.5);
}

test('reference native selects and currency dialog inputs remain readable in dark mode', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/shipping_configuration');
    const select = page.locator('#shipping_type');
    await expect(select).toBeVisible();
    await expectReadable(select);
    await page.goto('/temp/admin/currency');
    await page.getByRole('link', { name: 'Add currency', exact: true }).click();
    const input = page.getByRole('dialog').locator('#name');
    await input.fill('Contrast Euro');
    await expectReadable(input);
});

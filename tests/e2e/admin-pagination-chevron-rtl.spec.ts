import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { openAccountMenu } from './support/nav';

test('the pagination previous/next chevrons flip direction in RTL layouts', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await openAccountMenu(page);
    await page.getByRole('button', { name: 'Arabic' }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await page.goto('/admin/cell-logs');

    const nextChevron = page.locator('nav svg.lucide-chevron-right').first();
    await expect(nextChevron).toBeVisible();
    await expect(nextChevron).toHaveCSS('rotate', '180deg');
});

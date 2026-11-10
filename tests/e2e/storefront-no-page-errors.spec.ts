import { expect, test } from '@playwright/test';

// Legacy Blade pages carry inline scripts that depend on script order and on
// optional addons. A page script that throws on load leaves the rest of that
// script block unrun, so every public page must load without uncaught errors.
for (const url of [
    '/temp',
    '/temp/cart',
    '/temp/register',
    '/temp/users/registration',
    '/temp/login',
    '/temp/coupons',
    '/temp/compare',
]) {
    test(`${url} loads without uncaught page errors`, async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        await page.goto(url);
        await page.waitForLoadState('load');

        expect(pageErrors).toEqual([]);
    });
}

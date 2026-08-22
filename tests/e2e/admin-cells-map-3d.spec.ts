import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the 3D map view renders a real canvas and survives keyboard/pointer navigation', async ({
    page,
}) => {
    const pageErrors: Error[] = [];
    page.on('pageerror', (error) => pageErrors.push(error));

    await loginAsAdmin(page);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZW');
    await page.locator('#cells_count').fill('2');
    await page.locator('#flats_count').fill('2');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page).toHaveURL(/\/admin\/rows\/ZW$/);

    await page.goto('/admin/cells');
    await page.getByTitle('3D view').click();

    const viewport = page.getByTestId('map-3d-viewport');
    await expect(viewport).toBeVisible();
    await expect(viewport.locator('canvas')).toBeVisible();

    // Walk forward/strafe with the keyboard, then drag to pitch — this is
    // WebGL/three.js behavior jsdom can't render, so it's only verifiable
    // here, in a real browser.
    await viewport.click();
    await page.keyboard.down('w');
    await page.waitForTimeout(150);
    await page.keyboard.up('w');

    const box = await viewport.boundingBox();

    if (box) {
        const centerX = box.x + box.width / 2;
        const centerY = box.y + box.height / 2;
        await page.mouse.move(centerX, centerY);
        await page.mouse.down();
        await page.mouse.move(centerX, centerY - 60);
        await page.mouse.up();
    }

    await expect(viewport.locator('canvas')).toBeVisible();
    expect(pageErrors).toEqual([]);
});

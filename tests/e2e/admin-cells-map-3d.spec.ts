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

test('clicking a cell in the 3D map opens its manage actions (toggle-active, export QR, manage pallet)', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZX');
    await page.locator('#cells_count').fill('2');
    await page.locator('#flats_count').fill('1');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page).toHaveURL(/\/admin\/rows\/ZX$/);

    await page.goto('/admin/cells');
    await page.getByTitle('3D view').click();

    const viewport = page.getByTestId('map-3d-viewport');
    await expect(viewport).toBeVisible();
    await expect(viewport.locator('canvas')).toBeVisible();

    const box = await viewport.boundingBox();

    if (!box) {
        throw new Error('3D viewport has no bounding box');
    }

    const centerX = box.x + box.width / 2;
    const centerY = box.y + box.height / 2;

    // Click the cell we're facing by default to pin the manage panel.
    await page.mouse.click(centerX, centerY);

    const panel = page.getByTestId('map-3d-faced-cell');
    await expect(panel).toBeVisible();

    // Regression coverage: clicking a control inside the panel used to be
    // silently swallowed by the viewport's own pointer-capture-driven
    // click-to-select handling (a real-browser-only bug jsdom unit tests
    // can't reproduce — see CellMap3D.test.ts for the jsdom-level guard).
    await panel.getByTitle('Deactivate cell').click();
    const toggleDialog = page.getByRole('dialog');
    await expect(toggleDialog).toBeVisible();
    await expect(toggleDialog).toContainText('Deactivate cell');
    await toggleDialog.getByRole('button', { name: 'Deactivate' }).click();
    await expect(toggleDialog).not.toBeVisible();

    // Re-select (now-inactive) cell and open its pallet-management panel.
    await page.mouse.click(centerX, centerY);
    await panel.getByTitle('Reactivate cell').click();
    const reactivateDialog = page.getByRole('dialog');
    await expect(reactivateDialog).toBeVisible();
    await reactivateDialog.getByRole('button', { name: 'Reactivate' }).click();
    await expect(reactivateDialog).not.toBeVisible();

    await page.mouse.click(centerX, centerY);
    await panel.getByTitle('Manage pallet').click();
    const manageDialog = page.getByRole('dialog');
    await expect(manageDialog).toBeVisible();
    await expect(manageDialog).toContainText('Manage pallet');

    // Export-QR is a plain download link — verify it triggers a real download.
    await manageDialog.getByRole('button', { name: 'Close filters' }).click();
    await expect(manageDialog).not.toBeVisible();
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        panel.getByTitle('Reprint QR code').click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/qr-code\.pdf$/);
});

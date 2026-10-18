import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    documentViolations,
    expectStrictPolicy,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('default location uses real Leaflet with editable coordinates under enforced CSP', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        foreach (['google_map' => '1', 'google_map_latitude' => '33.513', 'google_map_longtitude' => '36.291'] as $type => $value) {
            App\Models\BusinessSetting::query()->updateOrCreate(['type' => $type, 'lang' => null], ['value' => $value]);
        }
        Illuminate\Support\Facades\Cache::forget('business_settings');
    `);
    ensureStoreRtlLanguage();
    const tile = readFileSync('tests/e2e/fixtures/store-avatar.png');
    let tiles = 0;
    await page.route('https://tile.openstreetmap.org/**', (route) => {
        tiles++;

        return route.fulfill({ contentType: 'image/png', body: tile });
    });
    await loginAsStoreAdmin(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/google-map');
    const map = page.getByRole('region', {
        name: 'Choose the default location',
    });
    const lat = page.locator('#google_map_latitude');
    const lng = page.locator('#google_map_longtitude');
    await expect(map.locator('.leaflet-tile-loaded').first()).toBeVisible();
    expect(tiles).toBeGreaterThan(0);
    await expect(map).toHaveAttribute('dir', 'ltr');
    const initialLat = await lat.inputValue();
    const box = (await map.boundingBox())!;
    await map.click({ position: { x: box.width * 0.65, y: box.height * 0.6 } });
    await expect(lat).not.toHaveValue(initialLat);
    const clickedLat = await lat.inputValue();
    const pin = map.locator('.store-default-location-marker');
    const markerBox = (await pin.boundingBox())!;
    await page.mouse.move(markerBox.x + 15, markerBox.y + 15);
    await page.mouse.down();
    await page.mouse.move(markerBox.x + 65, markerBox.y + 45, { steps: 12 });
    await page.mouse.up();
    await expect(lat).not.toHaveValue(clickedLat);
    await lat.fill('40');
    await lng.fill('10');
    await expect(pin).toBeVisible();
    await lat.fill('91');
    expect(
        await lat.evaluate(
            (input: HTMLInputElement) => input.validity.rangeOverflow,
        ),
    ).toBe(true);
    await lat.fill('40');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByRole('button', { name: 'Save', exact: true }),
    ).toBeEnabled();
    await page.reload();
    await expect(lat).toHaveValue('40');
    await expect(lng).toHaveValue('10');
    await page.locator('#google_map').selectOption('0');
    await expect(map).toHaveCount(0);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(
        page.getByRole('button', { name: 'Save', exact: true }),
    ).toBeEnabled();
    await page.reload();
    await expect(page.locator('#google_map')).toHaveValue('0');
    await page.locator('#google_map').selectOption('1');
    await expect(lat).toHaveValue('40');
    await expect(lng).toHaveValue('10');
    await switchLocale(page, 'Arabic');
    await page.goto('/temp/admin/google-map');
    await page.locator('#google_map').selectOption('1');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('.leaflet-container')).toHaveAttribute(
        'dir',
        'ltr',
    );
    await page.emulateMedia({ colorScheme: 'dark' });
    const colors = await lat.evaluate((element) => {
        const style = getComputedStyle(element);
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const ctx = canvas.getContext('2d')!;
        function rgb(color: string): [number, number, number] {
            ctx.fillStyle = color;
            ctx.fillRect(0, 0, 1, 1);
            const data = ctx.getImageData(0, 0, 1, 1).data;

            return [data[0], data[1], data[2]];
        }

        return { fg: rgb(style.color), bg: rgb(style.backgroundColor) };
    });
    expect(contrastRatio(colors.fg, colors.bg)).toBeGreaterThanOrEqual(4.5);
    expect(await documentViolations(page)).toEqual([]);
    expect(reports).toEqual([]);
    expect(errors).toEqual([]);
    await switchLocale(page, 'English');
});

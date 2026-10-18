import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    documentViolations,
    expectStrictPolicy,
    php,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

/** Real npm Leaflet under enforced CSP; only the OSM tile responses are local. */
test('visit detail and ranked day maps keep popups, Inertia navigation, note ownership, RTL and dark mode', async ({
    page,
}) => {
    const stamp = Date.now();
    const id = 900000 + (stamp % 100000);
    const note = `Visit map ${stamp}`;
    const customerName = `Visit customer ${stamp} <img src=x onerror=alert(1)>`;
    seedStoreFixtures(String.raw`
        $actor = App\Models\StoreUser::query()->where('email', 'store-admin@example.com')->sole();
        $actor->forceFill(['is_rep' => true])->save();
        $customer = (new App\Models\StoreUser)->forceFill(['name' => ${php(customerName)}, 'email' => 'visit-${stamp}@example.com', 'phone' => '06'.random_int(10000000, 99999999), 'password' => 'unused-password', 'user_type' => 'customer', 'is_rep' => true]);
        $customer->save();
        $order = (new App\Models\Order)->forceFill(['user_id' => $customer->id, 'shipping_type' => 'home_delivery', 'code' => 'VISIT-${stamp}', 'date' => now()->timestamp, 'grand_total' => 12.5, 'payment_type' => 'cash_on_delivery', 'delivery_status' => 'delivered']);
        $order->save();
        foreach ([[${id}, $actor->id, 4, 52.37, 4.89, $order->id], [${id + 1}, $actor->id, 1, 52.39, 4.93, null], [${id + 2}, $customer->id, 9, 52.40, 4.95, null]] as [$visitId, $rep, $rank, $lat, $lng, $orderId]) {
            App\Models\Visit::create(['id' => $visitId, 'rep_id' => $rep, 'customer_id' => $customer->id, 'order_id' => $orderId, 'rank' => $rank, 'map_lat' => $lat, 'map_lng' => $lng, 'visit_date' => '2026-10-09 12:00:00', 'note' => ${php(note)}]);
        }
    `);
    let tileRequests = 0;
    const tile = readFileSync('tests/e2e/fixtures/store-avatar.png');
    await page.route('https://tile.openstreetmap.org/**', (route) => {
        tileRequests++;

        return route.fulfill({ contentType: 'image/png', body: tile });
    });
    const remoteCode: string[] = [];
    page.on('request', (request) => {
        if (request.url().includes('unpkg.com')) {
            remoteCode.push(request.url());
        }
    });
    await loginAsStoreAdmin(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const csp = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/representatives/visits/index');
    await page.evaluate(() => {
        (window as unknown as { visitDocument: boolean }).visitDocument = true;
    });
    await page
        .getByRole('row')
        .filter({ hasText: note })
        .filter({ hasText: 'Yes' })
        .getByRole('link', { name: 'Visit details', exact: true })
        .click();
    await expect(page).toHaveURL(new RegExp(`/visits/show/${id}$`));
    expect(
        await page.evaluate(
            () =>
                (window as unknown as { visitDocument: boolean }).visitDocument,
        ),
    ).toBe(true);
    const map = page.getByRole('region', {
        name: 'Map of representative visits',
    });
    await expect(map).toHaveClass(/leaflet-container/);
    await expect(map).toHaveAttribute('dir', 'ltr');
    await expect(map.locator('.store-visit-marker.blue')).toHaveText('5');
    await expect(map.locator('path.leaflet-interactive')).toHaveCount(0);
    await expect.poll(() => tileRequests).toBeGreaterThan(0);
    await expect(map.locator('.leaflet-tile-loaded').first()).toBeVisible();
    await map.locator('.store-visit-marker').focus();
    await page.keyboard.press('Enter');
    await expect(map.locator('.leaflet-popup-content')).toHaveText(
        customerName,
    );
    await expect(map.locator('.leaflet-popup-content img')).toHaveCount(0);
    const updated = `Own visit note ${stamp}`;
    await page.getByLabel('Visit note', { exact: true }).fill(updated);
    await page.getByRole('button', { name: 'Save note', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Note saved.');
    seedStoreFixtures(String.raw`
        if (App\Models\Visit::findOrFail(${id})->note !== ${php(updated)} || App\Models\Visit::findOrFail(${id + 2})->note !== ${php(note)}) throw new RuntimeException('Visit note ownership changed');
    `);
    await page.getByRole('link', { name: /’s visits$/ }).click();
    await expect(page).toHaveURL(/\/visits\/rep-show\/\d+\?date=2026-10-09$/);
    expect(
        await page.evaluate(
            () =>
                (window as unknown as { visitDocument: boolean }).visitDocument,
        ),
    ).toBe(true);
    await expect(map.locator('.store-visit-marker')).toHaveText(['5', '2']);
    await expect(map.locator('.store-visit-marker.blue')).toHaveCount(1);
    await expect(map.locator('.store-visit-marker.red')).toHaveCount(1);
    await expect(map.locator('path.leaflet-interactive')).toHaveCount(1);
    await page.getByLabel('Filter by date', { exact: true }).fill('2026-10-10');
    await page
        .getByRole('button', { name: 'Apply filters', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'No visits found on this date' }),
    ).toBeVisible();
    await expect(map).toHaveCount(0);
    await page.getByLabel('Filter by date', { exact: true }).fill('2026-10-09');
    await page
        .getByRole('button', { name: 'Apply filters', exact: true })
        .click();
    await expect(map.locator('.store-visit-marker')).toHaveCount(2);
    ensureStoreRtlLanguage();
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByTestId('store-visit-map')).toHaveAttribute(
        'dir',
        'ltr',
    );
    await expect(page.locator('#date')).toHaveCSS('color-scheme', 'dark');
    const arrow = page
        .getByRole('link', { name: 'العودة إلى الزيارات' })
        .locator('svg');
    await expect(arrow).toHaveCSS('rotate', '180deg');
    const inputColors = await page.locator('#date').evaluate((element) => {
        const style = getComputedStyle(element);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d')!;
        function rgb(value: string): [number, number, number] {
            ctx.fillStyle = value;
            ctx.fillRect(0, 0, 1, 1);
            const data = ctx.getImageData(0, 0, 1, 1).data;

            return [data[0], data[1], data[2]];
        }

        return { fg: rgb(style.color), bg: rgb(style.backgroundColor) };
    });
    expect(
        contrastRatio(inputColors.fg, inputColors.bg),
    ).toBeGreaterThanOrEqual(4.5);
    await switchLocale(page, 'الإنجليزية');
    await expect(
        page.getByRole('link', { name: 'Back to visits' }).locator('svg'),
    ).toHaveCSS('rotate', 'none');
    await expectStrictPolicy(
        page,
        `/temp/admin/representatives/visits/show/${id + 2}`,
    );
    await expect(page.getByLabel('Visit note', { exact: true })).toHaveCount(0);
    await expect(
        page.getByTestId('store-visit-map').locator('.store-visit-marker.red'),
    ).toHaveText('10');
    expect(await documentViolations(page)).toEqual([]);
    expect(csp).toEqual([]);
    expect(remoteCode).toEqual([]);
    expect(errors).toEqual([]);
});

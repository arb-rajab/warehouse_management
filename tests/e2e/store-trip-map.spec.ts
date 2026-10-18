import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
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

/**
 * The trip map picker (store-backoffice-migration.md §30) with the real
 * Leaflet bundled from node_modules. OpenStreetMap tiles are answered
 * locally so the spec needs no network, but still pass through the page's
 * enforced CSP (`img-src` allows only that tile host beyond 'self').
 */
const tile = readFileSync('tests/e2e/fixtures/store-avatar.png');

async function serveTiles(page: Page): Promise<string[]> {
    const requested: string[] = [];
    await page.route('https://tile.openstreetmap.org/**', (route) => {
        requested.push(route.request().url());

        return route.fulfill({ contentType: 'image/png', body: tile });
    });

    return requested;
}

/**
 * Two located customers, each with an order out for delivery, a free truck
 * and the departure user (store user 9), after retiring earlier runs' map
 * orders. Returns the order ids.
 */
function seedMap(stamp: number, truck: string): [number, number] {
    const ids: [number, number] = [
        800000 + (stamp % 100000),
        700000 + (stamp % 100000),
    ];
    seedStoreFixtures(String.raw`
        if (App\Models\StoreUser::query()->whereKey(9)->doesntExist()) {
            (new App\Models\StoreUser)->forceFill(['id' => 9, 'name' => 'E2E departure', 'email' => 'e2e-departure@example.com', 'phone' => '0600000009', 'password' => 'unused-password', 'user_type' => 'staff'])->save();
        }
        // Earlier runs' map orders would sit on the same spots in this
        // persistent browser database.
        App\Models\Order::query()->where('code', 'like', 'MAP-%')->where('delivery_status', 'on_delivery')->update(['delivery_status' => 'delivered']);
        foreach ([[${ids[0]}, 'A', 52.37, 4.89], [${ids[1]}, 'B', 52.38, 4.93]] as [$id, $letter, $lat, $lng]) {
            $user = new App\Models\StoreUser;
            $user->forceFill(['name' => "Map customer {$letter} ${stamp}", 'email' => "map-{$letter}-${stamp}@example.com", 'phone' => '06'.random_int(10000000, 99999999), 'password' => 'unused-password', 'user_type' => 'customer'])->save();
            (new App\Models\Address)->forceFill(['user_id' => $user->id, 'address' => "Dock road {$letter}", 'state_id' => 0, 'latitude' => $lat, 'longitude' => $lng, 'postal_code' => '1011AB', 'set_default' => 1])->save();
            (new App\Models\Order)->forceFill([
                'id' => $id,
                'user_id' => $user->id,
                'shipping_type' => 'home_delivery',
                'shipping_address' => json_encode(['address' => "Dock road {$letter}", 'country' => 'Netherlands']),
                'payment_type' => 'cash_on_delivery',
                'payment_status' => 'unpaid',
                'grand_total' => 5,
                'code' => "MAP-{$letter}-${stamp}",
                'date' => now()->timestamp,
                'delivery_status' => 'on_delivery',
            ])->save();
        }
        App\Models\Truck::create(['name' => ${php(truck)}, 'is_available' => true, 'max_pallets_number' => '12']);
    `);

    return ids;
}

/** Fails the spec unless `$check` holds for the truck named `truck`. */
function expectStoredTruck(truck: string, check: string): void {
    seedStoreFixtures(String.raw`
        $truck = App\Models\Truck::query()->where('name', ${php(truck)})->sole();
        $trip = $truck->trips()->where('status', '<>', 'closed')->latest('id')->first();
        if (!(${check})) {
            throw new RuntimeException('Unexpected stored truck or trip');
        }
    `);
}

function marker(page: Page, code: string, customer: string) {
    return page.getByRole('button', {
        name: `#${code} ${customer}`,
        exact: true,
    });
}

test('the map picker selects orders on the real Leaflet map, reorders the stops and assigns them to a free truck', async ({
    page,
}) => {
    const stamp = Date.now();
    const truck = `Map truck ${stamp}`;
    const [first, second] = seedMap(stamp, truck);
    const tiles = await serveTiles(page);
    const errors: string[] = [];
    await loginAsStoreAdmin(page);
    // Observe this map workflow after the retained Blade login; the Inertia
    // dashboard has its own real-chart browser spec.
    page.on('pageerror', (error) => errors.push(error.message));
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/trips/create-from-map');
    await expect(
        page.getByRole('heading', {
            name: 'Create trip from the map',
            exact: true,
        }),
    ).toBeVisible();

    const map = page.getByRole('region', {
        name: 'Map of the orders out for delivery',
    });
    await expect(map).toHaveClass(/leaflet-container/);
    await expect(map).toHaveAttribute('dir', 'ltr');
    await expect.poll(() => tiles.length).toBeGreaterThan(0);
    await expect(map.locator('.leaflet-tile-loaded').first()).toBeVisible();
    await expect(map.locator('.leaflet-control-attribution')).toContainText(
        'OpenStreetMap',
    );
    const a = marker(page, `MAP-A-${stamp}`, `Map customer A ${stamp}`);
    const b = marker(page, `MAP-B-${stamp}`, `Map customer B ${stamp}`);
    await expect(a).toHaveAttribute('aria-pressed', 'false');

    const stops = page.locator(
        'section[aria-labelledby="trip-map-stops"] ol > li',
    );
    await a.click();
    await expect(a).toHaveAttribute('aria-pressed', 'true');
    await expect(a).toHaveClass(/hue-rotate-150/);
    // Keyboard selection: Leaflet markers are focusable buttons.
    await b.focus();
    await page.keyboard.press('Enter');
    await expect(b).toHaveAttribute('aria-pressed', 'true');
    await expect(stops).toHaveCount(2);
    await expect(stops.nth(0)).toContainText(`MAP-A-${stamp}`);
    await expect(stops.nth(0)).toContainText('Dock road A');

    await page
        .getByRole('button', { name: 'Move stop 2 up', exact: true })
        .click();
    await expect(stops.nth(0)).toContainText(`MAP-B-${stamp}`);
    await a.click();
    await expect(stops).toHaveCount(1);
    await a.click();
    await expect(stops).toHaveCount(2);
    await expect(stops.nth(1)).toContainText(`MAP-A-${stamp}`);

    const post = page.waitForRequest(
        (request) =>
            request.method() === 'POST' &&
            request.url().endsWith('/temp/admin/trips/store-from-map'),
    );
    await page
        .getByRole('button', {
            name: `Assign the stops to ${truck}`,
            exact: true,
        })
        .click();
    expect((await post).postDataJSON()).toMatchObject({
        trip_id: null,
        markers: [{ order_id: second }, { order_id: first }],
    });

    const busy = page
        .locator('section[aria-labelledby="trip-map-busy-trucks"] li', {
            has: page.getByRole('heading', { name: truck, exact: true }),
        })
        .first();
    await expect(busy).toBeVisible();
    await expect(busy).toContainText(`Map customer B ${stamp}`);
    await expect(stops).toHaveCount(0);
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/create-from-map$/);
    expectStoredTruck(
        truck,
        `!$truck->is_available && $trip !== null && $trip->checkpoints()->where('relationable_type', App\\Models\\Order::class)->pluck('relationable_id')->sort()->values()->all() === [${Math.min(first, second)}, ${Math.max(first, second)}] && $trip->checkpoints()->where('relationable_id', 9)->exists()`,
    );

    expect(errors).toEqual([]);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);
});

test("the map picker confirms a busy truck's trip deletion and a stop's delivery natively", async ({
    page,
}) => {
    const stamp = Date.now();
    const truck = `Map truck ${stamp}`;
    const [first] = seedMap(stamp, truck);
    seedStoreFixtures(String.raw`
        $truck = App\Models\Truck::query()->where('name', ${php(truck)})->sole();
        $truck->forceFill(['is_available' => false])->save();
        App\Models\Trip::create(['code' => ${php(`MAPTRIP-${stamp}`)}, 'truck_id' => $truck->id, 'due_date' => now()->toDateString(), 'status' => 'pending', 'active' => true]);
    `);
    await serveTiles(page);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/trips/create-from-map');
    const busy = page
        .locator('section[aria-labelledby="trip-map-busy-trucks"] li', {
            has: page.getByRole('heading', { name: truck, exact: true }),
        })
        .first();
    await expect(busy).toContainText('No current trip information.');

    page.once('dialog', (confirm) => confirm.dismiss());
    await busy
        .getByRole('button', { name: 'Delete trip', exact: true })
        .click();
    await expect(busy).toBeVisible();
    expectStoredTruck(truck, '$trip !== null');

    page.once('dialog', (confirm) => {
        expect(confirm.message()).toContain(`trip MAPTRIP-${stamp}`);
        void confirm.accept();
    });
    await busy
        .getByRole('button', { name: 'Delete trip', exact: true })
        .click();
    await expect(
        page
            .locator('section[aria-labelledby="trip-map-free-trucks"]')
            .getByRole('heading', { name: truck, exact: true }),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/create-from-map$/);
    expectStoredTruck(truck, '$truck->is_available && $trip === null');

    const a = marker(page, `MAP-A-${stamp}`, `Map customer A ${stamp}`);
    await a.click();
    const stop = page.locator(
        'section[aria-labelledby="trip-map-stops"] ol > li',
    );
    page.once('dialog', (confirm) => confirm.dismiss());
    await stop
        .getByRole('button', { name: 'Mark as delivered', exact: true })
        .click();
    await expect(stop).toHaveCount(1);

    const put = page.waitForRequest(
        (request) =>
            request.method() === 'PUT' &&
            request
                .url()
                .endsWith(
                    `/temp/admin/trips/orders/${first}/delivered?return_to=map`,
                ),
    );
    page.once('dialog', (confirm) => {
        expect(confirm.message()).toBe(
            `Mark order MAP-A-${stamp} as Delivered?`,
        );
        void confirm.accept();
    });
    await stop
        .getByRole('button', { name: 'Mark as delivered', exact: true })
        .click();
    await put;
    await expect(a).toHaveCount(0);
    await expect(stop).toHaveCount(0);
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/create-from-map$/);
    seedStoreFixtures(String.raw`
        if (App\Models\Order::query()->whereKey(${first})->value('delivery_status') !== 'delivered') {
            throw new RuntimeException('The order was not delivered');
        }
    `);
});

test('the map picker keeps its map left to right in Arabic and its text legible in dark mode', async ({
    page,
}) => {
    const stamp = Date.now();
    seedMap(stamp, `Map truck ${stamp}`);
    ensureStoreRtlLanguage();
    await serveTiles(page);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/trips/create-from-map');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', {
            name: 'إنشاء رحلة من الخريطة',
            exact: true,
        }),
    ).toBeVisible();
    const map = page.getByRole('region', { name: 'خريطة الطلبات قيد التوصيل' });
    await expect(map).toHaveAttribute('dir', 'ltr');
    const a = marker(page, `MAP-A-${stamp}`, `Map customer A ${stamp}`);
    await a.click();
    await expect(a).toHaveAttribute('aria-pressed', 'true');
    const stop = page
        .locator('section[aria-labelledby="trip-map-stops"] ol > li')
        .first();
    await expect(stop).toContainText('التوقف 1');

    const colors = await stop.evaluate((element) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d', { willReadFrequently: true })!;
        const rgb = (color: string) => {
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
        };
        let background = 'rgba(0, 0, 0, 0)';

        for (
            let node: Element | null = element;
            node;
            node = node.parentElement
        ) {
            background = getComputedStyle(node).backgroundColor;

            if (!background.endsWith(', 0)') && background !== 'transparent') {
                break;
            }
        }

        return {
            foreground: rgb(getComputedStyle(element).color),
            background: rgb(background),
        };
    });
    expect(Math.max(...colors.background)).toBeLessThan(60);
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
});

import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

/** An order of the seeded browser buyer with `id`, `code` and `status`. */
function seedOrder(id: number, code: string, status: string): void {
    seedStoreFixtures(String.raw`
        $buyer = App\Models\StoreUser::query()->where('email', 'browser-buyer@example.com')->firstOrFail();
        $order = new App\Models\Order;
        $order->forceFill([
            'id' => ${id},
            'user_id' => $buyer->id,
            'shipping_type' => 'home_delivery',
            'shipping_address' => json_encode(['address' => 'Main street 5', 'country' => 'Netherlands', 'phone' => '0611111111']),
            'payment_type' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'grand_total' => 5,
            'code' => ${php(code)},
            'date' => now()->timestamp,
            'delivery_status' => ${php(status)},
        ])->save();
    `);
}

/**
 * Stands in for the picker's Leaflet (loaded from unpkg, which not every
 * runner reaches, and whose approval is unverified, §5): without `L` the
 * picker's inline script stops before it registers its click handlers. The
 * spec checks the order popup's request, not the map.
 */
const leafletStandIn = String.raw`
window.L = (() => {
    const layer = () => {
        const self = { options: {}, getZoom: () => 2, getLatLng: () => [0, 0] };
        for (const name of ['addTo', 'bindPopup', 'on', 'setIcon', 'setView', 'fitBounds', 'setZoom']) {
            self[name] = () => self;
        }
        return self;
    };
    function LatLngBounds() { this.points = []; }
    LatLngBounds.prototype.extend = function (point) { this.points.push(point); return this; };
    LatLngBounds.prototype.isValid = function () { return this.points.length > 0; };
    return { icon: () => ({}), map: layer, tileLayer: layer, marker: layer, polyline: layer, LatLngBounds, Icon: { Default: { prototype: {} } } };
})();
`;

/** Fails the spec unless `$order` (the order with `code`) satisfies `$check`. */
function expectStoredOrder(code: string, check: string): void {
    seedStoreFixtures(String.raw`
        $order = App\Models\Order::query()->where('code', ${php(code)})->first();
        if (!(${check})) {
            throw new RuntimeException('Unexpected stored order');
        }
    `);
}

test('the trip orders list saves an invoice number on change and confirms status changes natively', async ({
    page,
}) => {
    const stamp = Date.now();
    const code = `E2E-ORD-${stamp}`;
    const other = `E2E-ORD2-${stamp}`;
    seedOrder(700000 + (stamp % 100000), code, 'confirmed');
    seedOrder(800000 + (stamp % 100000), other, 'confirmed');
    await loginAsStoreAdmin(page);
    await page.goto(`/temp/admin/trips/orders?search=E2E-ORD`);
    await expect(
        page.getByRole('heading', { name: 'Trip orders', exact: true }),
    ).toBeVisible();
    const row = page.getByRole('row', { name: new RegExp(code) });
    await expect(row).toContainText('Confirmed');

    const invoice = row.getByRole('textbox', {
        name: `Invoice number for order ${code}`,
    });
    await invoice.fill('INV-E2E-1');
    await invoice.blur();
    await expect(row.getByRole('status')).toHaveText('Invoice number saved.');
    await expect(row).toContainText('Out for delivery');
    await expect(page).toHaveURL(
        /\/temp\/admin\/trips\/orders\?search=E2E-ORD$/,
    );
    expectStoredOrder(
        code,
        "$order?->invoice_number === 'INV-E2E-1' && $order->delivery_status === 'on_delivery'",
    );

    page.once('dialog', (confirm) => {
        expect(confirm.message()).toBe(`Mark order ${code} as Delivered?`);
        void confirm.dismiss();
    });
    await row.getByRole('button', { name: 'Mark as delivered' }).click();
    await expect(row).toContainText('Out for delivery');
    expectStoredOrder(code, "$order?->delivery_status === 'on_delivery'");

    page.once('dialog', (confirm) => confirm.accept());
    await row.getByRole('button', { name: 'Mark as delivered' }).click();
    await expect(row).toContainText('Delivered');
    await expect(
        row.getByRole('button', { name: 'Mark as delivered' }),
    ).toHaveCount(0);
    await expect(page).toHaveURL(
        /\/temp\/admin\/trips\/orders\?search=E2E-ORD$/,
    );
    expectStoredOrder(code, "$order?->delivery_status === 'delivered'");

    const otherRow = page.getByRole('row', { name: new RegExp(other) });
    await otherRow
        .getByRole('checkbox', { name: `Select order ${other}` })
        .check();
    page.once('dialog', (confirm) => {
        expect(confirm.message()).toBe(
            'Mark 1 selected orders as Ready for delivery?',
        );
        void confirm.accept();
    });
    await page
        .getByRole('button', {
            name: 'Mark selection as Ready for delivery',
        })
        .click();
    await expect(otherRow).toContainText('Ready for delivery');
    await expect(
        otherRow.getByRole('checkbox', { name: `Select order ${other}` }),
    ).not.toBeChecked();
    expectStoredOrder(
        other,
        "$order?->delivery_status === 'ready_for_delivery'",
    );
    expectStoredOrder(code, "$order?->delivery_status === 'delivered'");
});

test("the Blade map picker's order popup marks an order delivered through the spoofed PUT and reloads", async ({
    page,
}) => {
    const stamp = Date.now();
    const code = `E2E-MAPORD-${stamp}`;
    const id = 900000 + (stamp % 100000);
    seedOrder(id, code, 'on_delivery');
    await loginAsStoreAdmin(page);
    await page.route(
        'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js',
        (route) =>
            route.fulfill({
                contentType: 'text/javascript',
                body: leafletStandIn,
            }),
    );
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const response = await page.goto('/temp/admin/trips/create-from-map');
    expect(response?.status()).toBe(200);
    const alerts: string[] = [];
    page.on('dialog', (dialog) => {
        alerts.push(dialog.message());
        void dialog.dismiss();
    });

    // The popup the map builds for an order marker, with its real
    // document-level click handler.
    await page.evaluate((orderId) => {
        const popup = (
            window as unknown as {
                tripMapPopup: (
                    rows: [string | null, string][],
                    orderId: number,
                ) => HTMLElement;
            }
        ).tripMapPopup([['Order', 'browser']], orderId);
        popup.style.cssText =
            'position: fixed; top: 1rem; right: 1rem; z-index: 10000; background: #fff';
        document.body.append(popup);
    }, id);
    const put = page.waitForRequest(
        (request) =>
            request.method() === 'POST' &&
            request.url().endsWith(`/temp/admin/trips/orders/${id}/delivered`),
    );
    const reload = page.waitForEvent('load');
    await page.getByRole('button', { name: 'Mark as Delivered' }).click();
    expect((await put).postDataJSON()).toEqual({
        _method: 'PUT',
        return_to: 'map',
    });
    await reload;

    await expect(page).toHaveURL(/\/temp\/admin\/trips\/create-from-map$/);
    expect(alerts).toEqual([]);
    expect(errors.filter((message) => message.includes('L is not'))).toEqual(
        [],
    );
    expectStoredOrder(code, "$order?->delivery_status === 'delivered'");
});

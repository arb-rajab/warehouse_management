/// <reference types="node" />

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
import { afterEach, describe, expect, it, vi } from 'vitest';

function scriptFrom(path: string): string {
    const template = readFileSync(
        resolve(process.cwd(), 'resources/views/shop/backend/trips', path),
        'utf8',
    );
    const script = template.match(/<script>([\s\S]*?)<\/script>/)?.[1];

    if (!script) {
        throw new Error(`No script in ${path}`);
    }

    return script.replace(/@json\(translate\('([^']*)'\)\)/g, (_, label) =>
        JSON.stringify(label),
    );
}

const popupScript = scriptFrom('inc/map-popups.blade.php');
const unsafe = '<img src=x onerror=alert(1)> "Customer"';

function mapContext(withElement = true, canEdit = true) {
    document.body.innerHTML = withElement ? '<div id="map"></div>' : '';
    const map = {
        setView: vi.fn(),
        fitBounds: vi.fn(),
        getZoom: vi.fn(() => 2),
        setZoom: vi.fn(),
    };
    map.setView.mockReturnValue(map);
    const popup = vi.fn();
    const markerLayer = {
        options: {},
        addTo: vi.fn(),
        bindPopup: popup,
        on: vi.fn(),
        getLatLng: vi.fn(() => [0, 0]),
    };
    markerLayer.addTo.mockReturnValue(markerLayer);
    popup.mockReturnValue(markerLayer);
    const layer = { addTo: vi.fn() };
    class Bounds {
        constructor(public points: unknown[] = []) {}
        extend(point: unknown) {
            this.points.push(point);
        }
        isValid() {
            return this.points.length > 0;
        }
    }
    const leaflet = {
        map: vi.fn(() => map),
        marker: vi.fn(() => markerLayer),
        tileLayer: vi.fn(() => layer),
        polyline: vi.fn(() => layer),
        divIcon: vi.fn(),
        icon: vi.fn(),
        LatLngBounds: Bounds,
    };
    const context = {
        L: leaflet,
        document: {
            createElement: document.createElement.bind(document),
            getElementById: document.getElementById.bind(document),
            querySelector: document.querySelector.bind(document),
            querySelectorAll: document.querySelectorAll.bind(document),
            addEventListener: vi.fn(),
        },
        $: vi.fn(() => ({ on: vi.fn() })),
        console: { warn: vi.fn(), log: vi.fn(), error: vi.fn() },
    };
    runInNewContext(
        popupScript.replace(
            "@json(auth('store')->user()?->can('edit_trip') ?? false)",
            JSON.stringify(canEdit),
        ),
        context,
    );

    return { context, leaflet, map, popup };
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the retained Blade trip maps', () => {
    it('builds text popups with a safe order button and preserves every value', () => {
        const { context } = mapContext();
        const content = runInNewContext(
            'tripMapPopup([[null, unsafe], ["Phone", unsafe], ["Missing", null]], "42\\\" onclick=alert(1)")',
            { ...context, unsafe },
        ) as HTMLElement;
        expect(content.textContent).toBe(
            `${unsafe}Phone: ${unsafe}Missing: Mark as Delivered`,
        );
        expect(content.querySelector('img')).toBeNull();
        const button = content.querySelector('button')!;
        expect(button.type).toBe('button');
        expect(button.textContent).toBe('Mark as Delivered');
        expect(button.className).toBe('btn btn-danger btn-sm mark-delivered');
        expect(button.dataset.orderId).toBe('42" onclick=alert(1)');
        expect(button.getAttribute('onclick')).toBeNull();
    });

    it('omits the delivered action for a user without edit trip', () => {
        const { context } = mapContext(true, false);
        const content = runInNewContext(
            'tripMapPopup([[null, "Customer"]], 42)',
            context,
        ) as HTMLElement;
        expect(content.textContent).toBe('Customer');
        expect(content.querySelector('button')).toBeNull();
    });

    it.each([
        ['', '0'],
        ['0', '  '],
        [null, 0],
        [undefined, 0],
        [true, 0],
        ['91', '0'],
        ['-91', 0],
        [0, '181'],
        [0, '-181'],
        ['NaN', 0],
        ['1x', 0],
        [Infinity, 0],
    ])(
        'refuses malformed or out-of-range coordinates %s,%s',
        (latitude, longitude) => {
            const { context } = mapContext();
            expect(
                runInNewContext('tripMapCoordinates(latitude, longitude)', {
                    ...context,
                    latitude,
                    longitude,
                }),
            ).toBeNull();
        },
    );

    it.each([
        [0, 0],
        [-90, -180],
        [90, 180],
        [' 0 ', '0'],
    ])('accepts valid coordinate pair %s,%s', (latitude, longitude) => {
        const { context } = mapContext();
        expect(
            runInNewContext('tripMapCoordinates(latitude, longitude)', {
                ...context,
                latitude,
                longitude,
            }),
        ).toEqual([Number(latitude), Number(longitude)]);
    });

    it('does not plot empty or out-of-range coordinate lookup results', async () => {
        const { context, leaflet } = mapContext();
        document.body.innerHTML +=
            '<select class="user-id-input"><option value="9" selected>Departure</option></select>' +
            '<select class="order-id-input"><option value="42" selected>Order</option></select>';
        const fetch = vi
            .fn()
            .mockResolvedValueOnce({
                json: () => Promise.resolve({ latitude: '', longitude: '0' }),
            })
            .mockResolvedValueOnce({
                json: () => Promise.resolve({ latitude: '91', longitude: '0' }),
            });
        runInNewContext(scriptFrom('inc/map-scripts.blade.php'), {
            ...context,
            fetch,
        });
        const callback = context.document.addEventListener.mock.calls.find(
            ([event]) => event === 'DOMContentLoaded',
        )?.[1] as () => void;
        callback();
        await vi.waitFor(() =>
            expect(context.console.error).toHaveBeenCalledTimes(2),
        );
        expect(leaflet.marker).not.toHaveBeenCalled();
    });

    it('renders picker metadata as text and keeps the order id for the delivered action', () => {
        const { context, leaflet, popup } = mapContext();
        const marker = {
            latitude: 0,
            longitude: 0,
            label: unsafe,
            order_code: unsafe,
            user_id: 7,
            postal_code: unsafe,
            phone: unsafe,
            shipping_address: unsafe,
            otajer_id: unsafe,
            order_id: 42,
        };
        const script = scriptFrom('create-from-map.blade.php').replace(
            '@json($long_lat_array)',
            JSON.stringify([marker]),
        );
        runInNewContext(script, context);
        expect(leaflet.marker).toHaveBeenCalledExactlyOnceWith([0, 0]);
        const content = popup.mock.calls[0][0] as HTMLElement;
        expect(content.querySelector('img')).toBeNull();
        expect(content.textContent).toBe(
            `${unsafe}Order Code: ${unsafe}Customer ID: 7Post Code: ${unsafe}Phone: ${unsafe}Shipping Address: ${unsafe}Otajer ID: ${unsafe}Mark as Delivered`,
        );
        expect(content.querySelector('button')?.dataset.orderId).toBe('42');
    });

    it("marks the popup's order delivered with a spoofed PUT to its bound route and reloads", async () => {
        const { context } = mapContext();
        const fetch = vi.fn().mockResolvedValue({ ok: true });
        const location = { reload: vi.fn() };
        const alert = vi.fn();
        document.head.innerHTML = '<meta name="csrf-token" content="token">';
        const script = scriptFrom('create-from-map.blade.php')
            .replace('@json($long_lat_array)', '[]')
            .replace(
                '{{ route("trips.orders.update_order_status_to_delivered", ["order" => "__order__"]) }}',
                '/temp/admin/trips/orders/__order__/delivered',
            );
        runInNewContext(script, { ...context, fetch, location, alert });
        const onClick = context.document.addEventListener.mock.calls.find(
            ([event]) => event === 'click',
        )?.[1] as (event: { target: HTMLElement }) => void;
        const button = document.createElement('button');
        button.className = 'mark-delivered';
        button.setAttribute('data-order-id', '42');

        onClick({ target: button });
        expect(fetch).toHaveBeenCalledExactlyOnceWith(
            '/temp/admin/trips/orders/42/delivered',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': 'token',
                },
                body: JSON.stringify({ _method: 'PUT', return_to: 'map' }),
            },
        );
        await vi.waitFor(() => expect(location.reload).toHaveBeenCalledOnce());
        expect(alert).not.toHaveBeenCalled();

        fetch.mockResolvedValue({ ok: false });
        onClick({ target: button });
        await vi.waitFor(() =>
            expect(alert).toHaveBeenCalledWith(
                'Failed to mark order as delivered.',
            ),
        );
        expect(location.reload).toHaveBeenCalledOnce();
        document.head.innerHTML = '';
    });

    it('fetches every initial checkpoint and accepts zero-valued lookup coordinates', async () => {
        const { context, leaflet, popup } = mapContext();
        document.body.innerHTML +=
            '<select class="user-id-input"><option value="9" selected>Departure</option></select>' +
            '<select class="order-id-input"><option value="42" selected>Order</option></select>';
        const fetch = vi.fn().mockResolvedValue({
            json: () => Promise.resolve({ latitude: 0, longitude: 0 }),
        });
        runInNewContext(scriptFrom('inc/map-scripts.blade.php'), {
            ...context,
            fetch,
        });
        const callback = context.document.addEventListener.mock.calls.find(
            ([event]) => event === 'DOMContentLoaded',
        )?.[1] as () => void;
        callback();
        await vi.waitFor(() => expect(leaflet.marker).toHaveBeenCalledTimes(2));
        expect(fetch).toHaveBeenCalledTimes(2);
        expect(popup).toHaveBeenCalledTimes(2);
        expect((popup.mock.calls[0][0] as HTMLElement).textContent).toBe(
            "Rank: UserNotes: User's checkpoint",
        );
    });
});

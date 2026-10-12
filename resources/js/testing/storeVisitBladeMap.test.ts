/// <reference types="node" />

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
import { afterEach, describe, expect, it, vi } from 'vitest';

const partial = readFileSync(
    resolve(
        process.cwd(),
        'resources/views/shop/backend/representatives/visits/map.blade.php',
    ),
    'utf8',
);
const script = partial.match(/<script>([\s\S]*?)<\/script>/)?.[1];

if (!script) {
    throw new Error('The visit map partial has no script');
}

type Marker = {
    id: number;
    lat: number;
    lng: number;
    rank: number;
    has_order: boolean;
    label: string;
};

function runMap(markers: Marker[], withElement = true) {
    document.body.innerHTML = withElement ? '<div id="map"></div>' : '';
    const map = { setView: vi.fn(), fitBounds: vi.fn() };
    map.setView.mockReturnValue(map);
    const popup = vi.fn();
    const markerLayer = { addTo: vi.fn(), bindPopup: popup };
    markerLayer.addTo.mockReturnValue(markerLayer);
    const layer = { addTo: vi.fn() };
    const leaflet = {
        map: vi.fn(() => map),
        tileLayer: vi.fn(() => layer),
        marker: vi.fn(() => markerLayer),
        polyline: vi.fn(() => layer),
        divIcon: vi.fn((options) => options),
    };
    runInNewContext(
        script!.replace('@json($mapMarkers)', JSON.stringify(markers)),
        {
            L: leaflet,
            document,
        },
    );

    return { leaflet, map, popup };
}

const first: Marker = {
    id: 1,
    lat: 0,
    lng: 0,
    rank: 1,
    has_order: false,
    label: '<img src=x onerror=alert(1)> "Customer"',
};

afterEach(() => {
    document.body.innerHTML = '';
});

describe('the existing Blade visit maps', () => {
    it('does not initialize a missing map container on an empty representative day', () => {
        const { leaflet } = runMap([], false);
        expect(leaflet.map).not.toHaveBeenCalled();
        expect(leaflet.marker).not.toHaveBeenCalled();
    });

    it('keeps a world fallback without fitting invalid empty bounds', () => {
        const { leaflet, map } = runMap([]);
        expect(map.setView).toHaveBeenCalledExactlyOnceWith([0, 0], 2);
        expect(map.fitBounds).not.toHaveBeenCalled();
        expect(leaflet.marker).not.toHaveBeenCalled();
        expect(leaflet.polyline).not.toHaveBeenCalled();
    });

    it('accepts zero coordinates and uses customer names as text in popups', () => {
        const { leaflet, map, popup } = runMap([first]);
        expect(leaflet.marker).toHaveBeenCalledExactlyOnceWith([0, 0], {
            icon: { className: 'custom-marker red', html: '1' },
        });
        expect(popup).toHaveBeenCalledTimes(1);
        const content = popup.mock.calls[0][0] as HTMLElement;
        expect(content.textContent).toBe(first.label);
        expect(content.querySelector('img')).toBeNull();
        expect(map.setView).toHaveBeenLastCalledWith([0, 0], 14);
        expect(map.fitBounds).not.toHaveBeenCalled();
        expect(leaflet.polyline).not.toHaveBeenCalled();
    });

    it('draws each visit once with its order color and keeps the supplied rank order', () => {
        const second = {
            ...first,
            id: 2,
            lat: 51,
            lng: 5,
            rank: 4,
            has_order: true,
        };
        const { leaflet, map, popup } = runMap([second, first]);
        expect(leaflet.marker).toHaveBeenCalledTimes(2);
        expect(leaflet.marker).toHaveBeenNthCalledWith(1, [51, 5], {
            icon: { className: 'custom-marker blue', html: '4' },
        });
        expect(leaflet.marker).toHaveBeenNthCalledWith(2, [0, 0], {
            icon: { className: 'custom-marker red', html: '1' },
        });
        expect(popup).toHaveBeenCalledTimes(2);
        expect(leaflet.polyline).toHaveBeenCalledExactlyOnceWith([
            [51, 5],
            [0, 0],
        ]);
        expect(map.fitBounds).toHaveBeenCalledExactlyOnceWith(
            [
                [51, 5],
                [0, 0],
            ],
            {
                padding: [24, 24],
                maxZoom: 14,
            },
        );
    });
});

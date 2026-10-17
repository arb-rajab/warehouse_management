import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { t } from '@/lib/i18n';
import StoreTripMap from './StoreTripMap.vue';
import type { StoreTripMapPoint } from './StoreTripMap.vue';

/**
 * Leaflet replaced by recording stand-ins, as CellMap3D.test.ts replaces
 * three.js: jsdom has no layout for Leaflet to measure. The browser spec
 * (store-trip-map.spec.ts) drives the real package.
 */
const leaflet = vi.hoisted(() => {
    type Handler = (event?: unknown) => void;
    class FakeMarker {
        element = document.createElement('img');
        handlers: Record<string, Handler> = {};
        icon: unknown;
        constructor(
            public position: [number, number],
            public options: { icon: unknown; title: string; alt: string },
        ) {
            this.icon = options.icon;
        }
        on(event: string, handler: Handler) {
            this.handlers[event] = handler;

            return this;
        }
        addTo = vi.fn(() => this);
        remove = vi.fn(() => this);
        setIcon(icon: unknown) {
            this.icon = icon;

            return this;
        }
        getElement() {
            return this.element;
        }
    }
    const map = {
        fitBounds: vi.fn(),
        setView: vi.fn(),
        remove: vi.fn(),
    };
    const tiles = { addTo: vi.fn() };
    const markers: FakeMarker[] = [];

    return {
        FakeMarker,
        map,
        tiles,
        markers,
        mapFactory: vi.fn(() => map),
        tileLayer: vi.fn(() => tiles),
        marker: vi.fn(
            (
                position: [number, number],
                options: { icon: unknown; title: string; alt: string },
            ) => {
                const created = new FakeMarker(position, options);
                markers.push(created);

                return created;
            },
        ),
        icon: vi.fn((options: { className?: string }) => ({
            kind: options.className ? 'selected' : 'default',
            options,
        })),
        latLngBounds: vi.fn((points: [number, number][]) => ({ points })),
    };
});
vi.mock('leaflet', () => ({
    map: leaflet.mapFactory,
    tileLayer: leaflet.tileLayer,
    marker: leaflet.marker,
    icon: leaflet.icon,
    latLngBounds: leaflet.latLngBounds,
}));

const points: StoreTripMapPoint[] = [
    { id: 77, latitude: 52.37, longitude: 4.89, label: '#A Alma' },
    { id: 78, latitude: 52.37, longitude: 4.89, label: '#B Bram' },
    { id: 79, latitude: 51.9, longitude: 4.48, label: '#C Cas' },
];

function mountMap(selected: number[] = [], given = points) {
    return mount(StoreTripMap, {
        props: { points: given, selected },
        attachTo: document.body,
    });
}

beforeEach(() => {
    leaflet.markers.length = 0;
    vi.clearAllMocks();
});
afterEach(() => {
    document.body.innerHTML = '';
});

describe('StoreTripMap', () => {
    it('mounts a left-to-right, labelled map region on OpenStreetMap tiles', () => {
        const wrapper = mountMap();
        const region = wrapper.get('[data-testid="store-trip-map"]');

        expect(region.attributes('dir')).toBe('ltr');
        expect(region.attributes('role')).toBe('region');
        expect(region.attributes('aria-label')).toBe(
            t('store.trips.map.mapLabel'),
        );
        expect(leaflet.mapFactory).toHaveBeenCalledExactlyOnceWith(
            region.element,
        );
        expect(leaflet.tileLayer).toHaveBeenCalledExactlyOnceWith(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            },
        );
        expect(leaflet.tiles.addTo).toHaveBeenCalledWith(leaflet.map);
    });

    it('puts one labelled, keyboard-reachable marker per point, spreading markers at the same spot, and fits the map to them', () => {
        mountMap();

        expect(leaflet.markers.map((item) => item.position)).toEqual([
            [52.37, 4.89],
            [52.37, 4.89 + 0.0011],
            [51.9, 4.48],
        ]);
        expect(leaflet.marker.mock.calls.map(([, options]) => options)).toEqual(
            points.map((point) => ({
                icon: expect.objectContaining({ kind: 'default' }),
                title: point.label,
                alt: point.label,
                keyboard: true,
            })),
        );

        for (const item of leaflet.markers) {
            expect(item.addTo).toHaveBeenCalledWith(leaflet.map);
            expect(item.element.getAttribute('aria-pressed')).toBe('false');
        }

        expect(leaflet.latLngBounds).toHaveBeenCalledExactlyOnceWith([
            [52.37, 4.89],
            [52.37, 4.89],
            [51.9, 4.48],
        ]);
        expect(leaflet.map.fitBounds).toHaveBeenCalledExactlyOnceWith(
            { points: leaflet.latLngBounds.mock.calls[0][0] },
            { padding: [50, 50] },
        );
        expect(leaflet.map.setView).not.toHaveBeenCalled();
        // Leaflet defers adding a marker (and creating its element, which
        // carries aria-pressed) until the map has a view.
        expect(leaflet.map.fitBounds.mock.invocationCallOrder[0]).toBeLessThan(
            leaflet.markers[0].addTo.mock.invocationCallOrder[0],
        );
    });

    it('uses the bundled marker images, highlighting the selected icon', () => {
        mountMap();

        const [defaultOptions, selectedOptions] = leaflet.icon.mock.calls.map(
            ([options]) => options as Record<string, unknown>,
        );
        expect(defaultOptions).toMatchObject({
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            shadowSize: [41, 41],
        });
        expect(String(defaultOptions.iconUrl)).toContain('marker-icon');
        expect(String(defaultOptions.iconRetinaUrl)).toContain(
            'marker-icon-2x',
        );
        expect(String(defaultOptions.shadowUrl)).toContain('marker-shadow');
        expect(selectedOptions).toEqual({
            ...defaultOptions,
            className: 'hue-rotate-150 saturate-200',
        });
    });

    it('emits toggle with the point id when a marker is clicked', () => {
        const wrapper = mountMap();

        leaflet.markers[1].handlers.click();
        leaflet.markers[0].handlers.click();

        expect(wrapper.emitted('toggle')).toEqual([[78], [77]]);
    });

    it('emits toggle for Enter and Space on a focused marker, and ignores other keys', () => {
        const wrapper = mountMap();
        const press = (index: number, key: string) => {
            const originalEvent = new KeyboardEvent('keydown', {
                key,
                cancelable: true,
            });
            leaflet.markers[index].handlers.keydown({ originalEvent });

            return originalEvent.defaultPrevented;
        };

        expect(press(2, 'Enter')).toBe(true);
        expect(press(0, ' ')).toBe(true);
        expect(press(1, 'a')).toBe(false);

        expect(wrapper.emitted('toggle')).toEqual([[79], [77]]);
    });

    it('shows the selected points with the highlighted icon and aria-pressed, following the prop', async () => {
        const wrapper = mountMap([79]);

        expect(leaflet.markers.map((item) => item.icon)).toEqual([
            expect.objectContaining({ kind: 'default' }),
            expect.objectContaining({ kind: 'default' }),
            expect.objectContaining({ kind: 'selected' }),
        ]);
        expect(leaflet.markers[2].element.getAttribute('aria-pressed')).toBe(
            'true',
        );

        await wrapper.setProps({ selected: [77] });

        expect(leaflet.markers.map((item) => item.icon)).toEqual([
            expect.objectContaining({ kind: 'selected' }),
            expect.objectContaining({ kind: 'default' }),
            expect.objectContaining({ kind: 'default' }),
        ]);
        expect(
            leaflet.markers.map((item) =>
                item.element.getAttribute('aria-pressed'),
            ),
        ).toEqual(['true', 'false', 'false']);
    });

    it('redraws the markers when the points change and shows the world without points', async () => {
        const wrapper = mountMap();
        const first = [...leaflet.markers];

        await wrapper.setProps({ points: [points[2]] });
        await nextTick();

        for (const item of first) {
            expect(item.remove).toHaveBeenCalledOnce();
        }

        expect(leaflet.markers).toHaveLength(4);
        expect(leaflet.markers[3].position).toEqual([51.9, 4.48]);

        await wrapper.setProps({ points: [] });

        expect(leaflet.markers[3].remove).toHaveBeenCalledOnce();
        expect(leaflet.map.setView).toHaveBeenCalledExactlyOnceWith([0, 0], 2);
    });

    it('removes the map when unmounted', () => {
        const wrapper = mountMap();

        wrapper.unmount();

        expect(leaflet.map.remove).toHaveBeenCalledOnce();
    });
});

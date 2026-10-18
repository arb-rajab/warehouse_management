import { vi } from 'vitest';

/** Recording Leaflet layers for full component mounts without jsdom layout. */
export const leafletMapMock = {
    fitBounds: vi.fn(),
    setView: vi.fn(),
    remove: vi.fn(),
};
class LeafletLayerMock {
    addTo = vi.fn<(map: unknown) => LeafletLayerMock>(() => this);
    bindPopup = vi.fn<(popup: HTMLElement) => LeafletLayerMock>(() => this);
    remove = vi.fn();
}
export const leafletMock = {
    map: vi.fn(() => leafletMapMock),
    tileLayer: vi.fn(() => new LeafletLayerMock()),
    marker: vi.fn<
        (position: [number, number], options: unknown) => LeafletLayerMock
    >(() => new LeafletLayerMock()),
    polyline: vi.fn<(points: [number, number][]) => LeafletLayerMock>(
        () => new LeafletLayerMock(),
    ),
    divIcon: vi.fn((options: unknown) => options),
};

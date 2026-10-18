import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import StoreLeafletMap from '@/components/StoreLeafletMap.vue';
import { t } from '@/lib/i18n';
import { representativeVisitMarker } from '@/testing/factories';
import { leafletMapMock, leafletMock } from '@/testing/leafletMock';
import StoreVisitMap from './StoreVisitMap.vue';

vi.mock(
    'leaflet',
    async () => (await import('@/testing/leafletMock')).leafletMock,
);
beforeEach(() => vi.clearAllMocks());
it('keeps rank ordering, coordinates, red/blue numbered markers, safe popups and the day polyline', () => {
    const points = [
        representativeVisitMarker({
            rank: 5,
            label: '<img src=x onerror=alert(1)>',
            lat: 51,
            lng: 5,
        }),
        representativeVisitMarker({ id: 82, has_order: false, rank: 2 }),
    ];
    const wrapper = mount(StoreVisitMap, { props: { markers: points } });
    expect(wrapper.getComponent(StoreLeafletMap).props('label')).toBe(
        t('store.representatives.visits.mapLabel'),
    );
    expect(leafletMock.marker.mock.calls.map(([position]) => position)).toEqual(
        [
            [51, 5],
            [0, 0],
        ],
    );
    expect(leafletMock.divIcon).toHaveBeenNthCalledWith(1, {
        className: 'store-visit-marker blue',
        html: '5',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
    expect(leafletMock.divIcon).toHaveBeenNthCalledWith(
        2,
        expect.objectContaining({
            className: 'store-visit-marker red',
            html: '2',
        }),
    );
    const popup = leafletMock.marker.mock.results[0].value.bindPopup.mock
        .calls[0][0] as HTMLElement;
    expect(popup.textContent).toBe(points[0].label);
    expect(popup.querySelector('img')).toBeNull();
    expect(leafletMock.marker.mock.calls[0][1]).toMatchObject({
        title: points[0].label,
        alt: points[0].label,
        keyboard: true,
    });
    expect(leafletMock.polyline).toHaveBeenCalledExactlyOnceWith([
        [51, 5],
        [0, 0],
    ]);
    expect(leafletMapMock.fitBounds).toHaveBeenCalledWith(
        [
            [51, 5],
            [0, 0],
        ],
        { padding: [24, 24], maxZoom: 14 },
    );
    expect(leafletMapMock.fitBounds.mock.invocationCallOrder[0]).toBeLessThan(
        leafletMock.marker.mock.invocationCallOrder[0],
    );
    wrapper.unmount();
});
it('uses a single-point view, redraws and removes old layers when the day changes, and keeps an empty world fallback', async () => {
    const wrapper = mount(StoreVisitMap, {
        props: { markers: [representativeVisitMarker()] },
    });
    expect(leafletMapMock.setView).toHaveBeenCalledWith([0, 0], 14);
    expect(leafletMock.polyline).not.toHaveBeenCalled();
    const first = leafletMock.marker.mock.results[0].value;
    await wrapper.setProps({ markers: [] });
    expect(first.remove).toHaveBeenCalledOnce();
    expect(leafletMapMock.setView).toHaveBeenLastCalledWith([0, 0], 2);
    expect(leafletMapMock.fitBounds).not.toHaveBeenCalled();
    wrapper.unmount();
    expect(leafletMapMock.remove).toHaveBeenCalledOnce();
});

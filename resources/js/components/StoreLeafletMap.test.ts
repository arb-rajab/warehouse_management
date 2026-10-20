import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { leafletMapMock, leafletMock } from '@/testing/leafletMock';
import StoreLeafletMap from './StoreLeafletMap.vue';

vi.mock(
    'leaflet',
    async () => (await import('@/testing/leafletMock')).leafletMock,
);
beforeEach(() => vi.clearAllMocks());
it('owns the LTR map, npm tile layer and teardown for both map consumers', () => {
    const wrapper = mount(StoreLeafletMap, { props: { label: 'Visits' } });
    expect(wrapper.attributes()).toMatchObject({
        dir: 'ltr',
        role: 'region',
        'aria-label': 'Visits',
    });
    expect(wrapper.classes()).toContain('isolate');
    expect(leafletMock.map).toHaveBeenCalledWith(wrapper.element);
    expect(leafletMock.tileLayer).toHaveBeenCalledWith(
        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        },
    );
    expect(wrapper.emitted('ready')).toEqual([[leafletMapMock]]);
    wrapper.unmount();
    expect(leafletMapMock.remove).toHaveBeenCalledOnce();
});

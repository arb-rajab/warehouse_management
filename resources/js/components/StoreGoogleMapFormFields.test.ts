import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import FormField from '@/components/FormField.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreLeafletMap from '@/components/StoreLeafletMap.vue';
import { i18n, t } from '@/lib/i18n';
import Index from './StoreGoogleMapFormFields.vue';

const { slotMock, map, pin, leaflet, events, pinEvents } = vi.hoisted(() => {
    const events: Record<string, (event: unknown) => void> = {};
    const pinEvents: Record<string, () => void> = {};
    const map = {
        setView: vi.fn(),
        panTo: vi.fn(),
        on: vi.fn((name, callback) => {
            events[name] = callback;
        }),
        remove: vi.fn(),
    };
    const pin = {
        addTo: vi.fn(),
        setLatLng: vi.fn(),
        remove: vi.fn(),
        on: vi.fn((name, callback) => {
            pinEvents[name] = callback;
        }),
        getLatLng: vi.fn(() => ({ lat: 45, lng: 12 })),
    };
    pin.addTo.mockReturnValue(pin);

    return {
        slotMock: vi.fn(() => ({
            errors: {} as Record<string, string>,
            processing: false,
        })),
        map,
        pin,
        events,
        pinEvents,
        leaflet: {
            map: vi.fn(() => map),
            tileLayer: vi.fn(() => ({ addTo: vi.fn() })),
            marker: vi.fn(() => pin),
            divIcon: vi.fn((options) => options),
        },
    };
});
vi.mock('leaflet', () => leaflet);
const mounted: ReturnType<typeof mount>[] = [];
function page(enabled = true, latitude = '33.513', longitude = '36.291') {
    const wrapper = mount(Index, {
        props: {
            settings: {
                google_map: enabled,
                google_map_latitude: latitude,
                google_map_longtitude: longitude,
            },
            errors: slotMock().errors,
        },
    });
    mounted.push(wrapper);

    return wrapper;
}
beforeEach(() => {
    vi.clearAllMocks();
    slotMock.mockReturnValue({ errors: {}, processing: false });
});
afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});
it.each(['en', 'ar'] as const)(
    'full mounts the form with all labels and native constraints in %s',
    (locale) => {
        i18n.global.locale.value = locale;
        const wrapper = page();
        expect(wrapper.getComponent(StoreBooleanField).props()).toMatchObject({
            name: 'google_map',
            value: true,
            label: t('store.googleMap.enabled'),
        });

        for (const key of [
            'enabled',
            'location',
            'latitude',
            'longitude',
            'hint',
        ]) {
            expect(wrapper.text()).toContain(t(`store.googleMap.${key}`));
            expect(t(`store.googleMap.${key}`)).not.toBe(
                `store.googleMap.${key}`,
            );
        }

        const base = wrapper.getComponent(StoreLeafletMap);
        expect(base.props('label')).toBe(t('store.googleMap.mapLabel'));
        expect(base.attributes('dir')).toBe('ltr');
        expect(wrapper.findAllComponents(FormField)).toHaveLength(2);

        for (const [id, min, max, value] of [
            ['google_map_latitude', '-90', '90', '33.513'],
            ['google_map_longtitude', '-180', '180', '36.291'],
        ]) {
            const input = wrapper.get<HTMLInputElement>(`#${id}`);
            expect(input.attributes()).toMatchObject({
                name: id,
                type: 'number',
                min,
                max,
                step: 'any',
            });
            expect(input.element.value).toBe(value);
            expect(input.attributes('readonly')).toBeUndefined();
        }
    },
);
it('synchronizes manual input, map clicks and marker drags without losing edited values', async () => {
    const wrapper = page();
    expect(map.setView).toHaveBeenCalledWith([33.513, 36.291], 13);
    expect(leaflet.marker.mock.invocationCallOrder[0]).toBeGreaterThan(
        map.setView.mock.invocationCallOrder[0],
    );
    expect(leaflet.marker).toHaveBeenCalledWith(
        [33.513, 36.291],
        expect.objectContaining({ draggable: true }),
    );
    await wrapper.get('#google_map_latitude').setValue('40');
    await wrapper.get('#google_map_longtitude').setValue('10');
    expect(pin.setLatLng).toHaveBeenLastCalledWith([40, 10]);
    events.click({ latlng: { lat: 42, lng: 11 } });
    await wrapper.vm.$nextTick();
    expect(
        wrapper.get<HTMLInputElement>('#google_map_latitude').element.value,
    ).toBe('42');
    expect(
        wrapper.get<HTMLInputElement>('#google_map_longtitude').element.value,
    ).toBe('11');
    pinEvents.dragend();
    await wrapper.vm.$nextTick();
    expect(
        wrapper.get<HTMLInputElement>('#google_map_latitude').element.value,
    ).toBe('45');
    expect(
        wrapper.get<HTMLInputElement>('#google_map_longtitude').element.value,
    ).toBe('12');
});
it('keeps coordinates when disabling and recreates the map when enabling', async () => {
    const wrapper = page();
    await wrapper.get('#google_map').setValue('0');
    expect(wrapper.findComponent(StoreLeafletMap).exists()).toBe(false);
    expect(wrapper.find('#google_map_latitude').exists()).toBe(false);
    expect(map.remove).toHaveBeenCalledOnce();
    await wrapper.get('#google_map').setValue('1');
    expect(
        wrapper.get<HTMLInputElement>('#google_map_latitude').element.value,
    ).toBe('33.513');
    expect(wrapper.findComponent(StoreLeafletMap).exists()).toBe(true);
});
it('starts without a map while disabled and uses a world view for unset coordinates', async () => {
    const wrapper = page(false, '', '');
    expect(leaflet.map).not.toHaveBeenCalled();
    await wrapper.get('#google_map').setValue('1');
    expect(map.setView).toHaveBeenCalledWith([0, 0], 2);
    expect(leaflet.marker).not.toHaveBeenCalled();
    await wrapper.get('#google_map_latitude').setValue('0');
    await wrapper.get('#google_map_longtitude').setValue('0');
    expect(leaflet.marker).toHaveBeenCalledWith([0, 0], expect.anything());
    await wrapper.get('#google_map_latitude').setValue('91');
    expect(pin.remove).toHaveBeenCalled();
});
it('shows server errors', () => {
    slotMock.mockReturnValue({
        errors: {
            google_map: 'Toggle error',
            google_map_latitude: 'Latitude error',
            google_map_longtitude: 'Longitude error',
        },
        processing: true,
    });
    const wrapper = page();

    for (const error of ['Toggle error', 'Latitude error', 'Longitude error']) {
        expect(wrapper.text()).toContain(error);
    }
});

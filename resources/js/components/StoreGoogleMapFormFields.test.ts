import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
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
function page(
    enabled = true,
    latitude = '33.513',
    longitude = '36.291',
    key: { set?: boolean; mask?: string; env?: boolean } = {
        set: true,
        mask: '••••••••wXyZ',
    },
) {
    const wrapper = mount(Index, {
        props: {
            settings: {
                google_map: enabled,
                google_map_latitude: latitude,
                google_map_longtitude: longitude,
                google_map_api_key_set: key.set ?? false,
                google_map_api_key_mask: key.mask ?? '',
                google_map_api_key_from_env: key.env ?? false,
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
            'requirementsHint',
        ]) {
            expect(wrapper.text()).toContain(t(`store.googleMap.${key}`));
            expect(t(`store.googleMap.${key}`)).not.toBe(
                `store.googleMap.${key}`,
            );
        }

        const base = wrapper.getComponent(StoreLeafletMap);
        expect(base.props('label')).toBe(t('store.googleMap.mapLabel'));
        expect(base.attributes('dir')).toBe('ltr');
        expect(wrapper.findAllComponents(FormField)).toHaveLength(3);

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
it('matches required_without_all and both required_with conditions as coordinates are entered and cleared', async () => {
    const wrapper = page(true, '', '');
    const check = (id: string, required: boolean) => {
        expect(wrapper.get(`#${id}`).attributes('required') !== undefined).toBe(
            required,
        );
        expect(
            wrapper
                .get(`label[for=${id}]`)
                .findComponent(RequiredMark)
                .exists(),
        ).toBe(required);
    };
    check('google_map', true);
    check('google_map_latitude', false);
    check('google_map_longtitude', false);
    await wrapper.get('#google_map_latitude').setValue('0');
    check('google_map', false);
    check('google_map_longtitude', true);
    await wrapper.get('#google_map_longtitude').setValue('0');
    check('google_map_latitude', true);
    await wrapper.get('#google_map_latitude').setValue('');
    check('google_map_latitude', true);
    check('google_map_longtitude', false);
    await wrapper.get('#google_map').setValue('0');
    check('google_map', true);
    expect(wrapper.find('#google_map_longtitude').exists()).toBe(false);
    await wrapper.get('#google_map').setValue('1');
    check('google_map', false);
});

const keyNone = { set: false, mask: '', env: false };
const keyHelpText = (wrapper: ReturnType<typeof page>) =>
    wrapper.get('#google_map_api_key_help').text();

it.each(['en', 'ar'] as const)(
    'shows the API key field under the switch with its label, help and a masked placeholder in %s',
    (locale) => {
        i18n.global.locale.value = locale;
        const wrapper = page();
        const input = wrapper.get<HTMLInputElement>('#google_map_api_key');
        expect(input.attributes()).toMatchObject({
            name: 'google_map_api_key',
            type: 'text',
            autocomplete: 'off',
            spellcheck: 'false',
            maxlength: '255',
            dir: 'ltr',
            placeholder: '••••••••wXyZ',
            'aria-describedby': 'google_map_api_key_help',
        });
        expect(input.element.value).toBe('');
        expect(wrapper.get('label[for=google_map_api_key]').text()).toContain(
            t('store.googleMap.apiKey'),
        );
        expect(keyHelpText(wrapper)).toContain(
            t('store.googleMap.apiKeyStatusSaved'),
        );
        expect(keyHelpText(wrapper)).toContain(t('store.googleMap.apiKeyHelp'));
        // After the switch, before the default location.
        const html = wrapper.html();
        expect(html.indexOf('id="google_map"')).toBeLessThan(
            html.indexOf('id="google_map_api_key"'),
        );
        expect(html.indexOf('id="google_map_api_key"')).toBeLessThan(
            html.indexOf('id="google_map_latitude"'),
        );
    },
);
it('has every API key string in both locales', () => {
    for (const locale of ['en', 'ar'] as const) {
        i18n.global.locale.value = locale;

        for (const key of [
            'apiKey',
            'apiKeyPlaceholder',
            'apiKeyHelp',
            'apiKeyStatusSaved',
            'apiKeyStatusEnv',
            'apiKeyStatusNone',
            'apiKeyRequiredHint',
        ]) {
            expect(t(`store.googleMap.${key}`)).not.toBe(
                `store.googleMap.${key}`,
            );
        }
    }
});
it('falls back to the generic placeholder and the "none" status when no key exists', () => {
    const wrapper = page(true, '', '', keyNone);
    expect(wrapper.get('#google_map_api_key').attributes('placeholder')).toBe(
        t('store.googleMap.apiKeyPlaceholder'),
    );
    expect(keyHelpText(wrapper)).toContain(
        t('store.googleMap.apiKeyStatusNone'),
    );
});
it('says MAP_API_KEY is in use when only the environment supplies a key', () => {
    const wrapper = page(true, '', '', { set: false, mask: '', env: true });
    expect(keyHelpText(wrapper)).toContain(
        t('store.googleMap.apiKeyStatusEnv'),
    );
    expect(keyHelpText(wrapper)).toContain('MAP_API_KEY');
});
it('marks the key required only while the map is on and no key is saved or in the environment', async () => {
    const wrapper = page(true, '', '', keyNone);
    const state = () => ({
        required:
            wrapper.get('#google_map_api_key').attributes('required') !==
            undefined,
        mark: wrapper
            .get('label[for=google_map_api_key]')
            .findComponent(RequiredMark)
            .exists(),
        hint: keyHelpText(wrapper).includes(
            t('store.googleMap.apiKeyRequiredHint'),
        ),
    });
    expect(state()).toEqual({ required: true, mark: true, hint: true });
    await wrapper.get('#google_map').setValue('0');
    expect(state()).toEqual({ required: false, mark: false, hint: false });
    await wrapper.get('#google_map').setValue('1');
    expect(state()).toEqual({ required: true, mark: true, hint: true });
});
it.each([
    ['a saved key', { set: true, mask: '••••••••wXyZ', env: false }],
    ['a MAP_API_KEY key', { set: false, mask: '', env: true }],
])('does not require the key when %s exists', (_name, key) => {
    const wrapper = page(true, '', '', key);
    const input = wrapper.get('#google_map_api_key');
    expect(input.attributes('required')).toBeUndefined();
    expect(
        wrapper
            .get('label[for=google_map_api_key]')
            .findComponent(RequiredMark)
            .exists(),
    ).toBe(false);
});
it('shows the server error for the key', () => {
    slotMock.mockReturnValue({
        errors: { google_map_api_key: 'Key error' },
        processing: false,
    });
    expect(page().text()).toContain('Key error');
});

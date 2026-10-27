import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    states,
    cities,
} from '@/actions/App/Http/Controllers/Store/Admin/AddressController';
import RequiredMark from '@/components/RequiredMark.vue';
import { t } from '@/lib/i18n';
import StoreCustomerAddressForm from './StoreCustomerAddressForm.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ processing: false, get: getMock }),
}));
beforeEach(() => {
    getMock.mockReset();
});
it('renders labels coordinates and required constraints', () => {
    const wrapper = mount(StoreCustomerAddressForm, {
        props: {
            countries: [{ id: 1, name: 'Netherlands' }],
            errors: { phone: 'Missing' },
        },
    });

    for (const key of [
        'address',
        'phone',
        'country',
        'state',
        'city',
        'postal_code',
        'latitude',
        'longitude',
    ]) {
        expect(wrapper.text()).toContain(t(`store.customers.fields.${key}`));
    }

    for (const id of ['address', 'phone', 'country_id']) {
        expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
    }

    expect(wrapper.get('#latitude').attributes('min')).toBe('-90');
    expect(wrapper.get('#longitude').attributes('max')).toBe('180');
    expect(wrapper.text()).toContain('Missing');
});
it('loads JSON options with Wayfinder and clears dependent selections', async () => {
    getMock.mockImplementation(
        (url: string, options: { onSuccess: (value: unknown) => void }) =>
            options.onSuccess(
                url.includes('states')
                    ? [{ id: 4, name: 'Noord Holland' }]
                    : [{ id: 5, name: 'Amsterdam' }],
            ),
    );
    const wrapper = mount(StoreCustomerAddressForm, {
        props: { countries: [{ id: 1, name: 'Netherlands' }], errors: {} },
    });
    await wrapper.get('#country_id').setValue('1');
    expect(getMock).toHaveBeenCalledWith(
        states({ query: { country_id: 1 } }).url,
        expect.any(Object),
    );
    await wrapper.get('#state_id').setValue('4');
    expect(getMock).toHaveBeenCalledWith(
        cities({ query: { state_id: 4 } }).url,
        expect.any(Object),
    );
    expect(wrapper.get('#city_id').text()).toContain('Amsterdam');
    await wrapper.get('#country_id').setValue('');
    expect((wrapper.get('#state_id').element as HTMLSelectElement).value).toBe(
        '',
    );
    expect(wrapper.get('#city_id').text()).not.toContain('Amsterdam');
});

it.each([
    undefined,
    {
        id: 1,
        country_id: 1,
        address: 'Street',
        phone: '31600000001',
        state_id: null,
        city_id: null,
        postal_code: null,
        latitude: null,
        longitude: null,
        set_default: 1,
    },
])(
    'marks only required address controls for new and existing addresses',
    (address) => {
        const wrapper = mount(StoreCustomerAddressForm, {
            props: {
                address,
                countries: [{ id: 1, name: 'Netherlands' }],
                errors: {},
            },
        });

        for (const id of ['address', 'phone', 'country_id']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(
                wrapper
                    .get(`label[for="${id}"]`)
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(true);
        }

        for (const id of [
            'state_id',
            'city_id',
            'postal_code',
            'latitude',
            'longitude',
        ]) {
            expect(
                wrapper.get(`#${id}`).attributes('required'),
            ).toBeUndefined();
            expect(
                wrapper
                    .get(`label[for="${id}"]`)
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(false);
        }

        wrapper.unmount();
    },
);

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    states,
    cities,
} from '@/actions/App/Http/Controllers/Store/Admin/AddressController';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';
import StoreCustomerAddressForm from './StoreCustomerAddressForm.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ processing: false, get: getMock }),
}));
beforeEach(() => {
    getMock.mockReset();
});

const north: SearchOption = {
    id: 4,
    label: 'Noord Holland',
    description: 'Netherlands',
    icon: 'map-pin',
    extra: { country_id: 1 },
};
const amsterdam: SearchOption = {
    id: 5,
    label: 'Amsterdam',
    description: 'Noord Holland',
    icon: 'map-pin',
    extra: { state_id: 4 },
};

function selects(wrapper: VueWrapper) {
    const [state, city] = wrapper.findAllComponents(StoreSearchSelect);

    return { state, city };
}

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
it('searches the states of the chosen country and the cities of the chosen state, and drops dependent picks', async () => {
    const wrapper = mount(StoreCustomerAddressForm, {
        props: { countries: [{ id: 1, name: 'Netherlands' }], errors: {} },
    });

    // Nothing can be searched before the parent is chosen.
    expect(selects(wrapper).state.props()).toMatchObject({
        endpoint: states,
        name: 'state_id',
        disabled: true,
        placeholder: t('store.customers.fields.selectCountryFirst'),
    });
    expect(selects(wrapper).city.props()).toMatchObject({
        endpoint: cities,
        name: 'city_id',
        disabled: true,
        placeholder: t('store.customers.fields.selectStateFirst'),
    });

    await wrapper.get('#country_id').setValue('1');
    expect(selects(wrapper).state.props()).toMatchObject({
        disabled: false,
        params: { country_id: 1 },
        placeholder: t('store.customers.fields.selectState'),
    });

    selects(wrapper).state.vm.$emit('update:modelValue', north);
    await wrapper.vm.$nextTick();
    expect(selects(wrapper).city.props()).toMatchObject({
        disabled: false,
        params: { state_id: 4 },
    });
    selects(wrapper).city.vm.$emit('update:modelValue', amsterdam);
    await wrapper.vm.$nextTick();
    expect(
        wrapper
            .get('input[type="hidden"][name="state_id"]')
            .attributes('value'),
    ).toBe('4');
    expect(
        wrapper.get('input[type="hidden"][name="city_id"]').attributes('value'),
    ).toBe('5');
    expect(wrapper.get('#city_id').text()).toContain('Amsterdam');

    // Picking the same state again keeps the city; another state drops it.
    selects(wrapper).state.vm.$emit('update:modelValue', north);
    await wrapper.vm.$nextTick();
    expect(wrapper.get('#city_id').text()).toContain('Amsterdam');
    selects(wrapper).state.vm.$emit('update:modelValue', {
        id: 6,
        label: 'Zuid Holland',
    });
    await wrapper.vm.$nextTick();
    expect(
        wrapper.get('input[type="hidden"][name="city_id"]').attributes('value'),
    ).toBe('');

    // A new country drops both.
    selects(wrapper).city.vm.$emit('update:modelValue', amsterdam);
    await wrapper.vm.$nextTick();
    await wrapper.get('#country_id').setValue('');
    expect(
        wrapper
            .get('input[type="hidden"][name="state_id"]')
            .attributes('value'),
    ).toBe('');
    expect(
        wrapper.get('input[type="hidden"][name="city_id"]').attributes('value'),
    ).toBe('');
    expect(selects(wrapper).state.props('disabled')).toBe(true);
    // Nothing was fetched until a list is opened.
    expect(getMock).not.toHaveBeenCalled();
});
it('shows the stored state and city before any search', () => {
    const wrapper = mount(StoreCustomerAddressForm, {
        props: {
            address: {
                id: 1,
                country_id: 1,
                address: 'Street',
                phone: '31600000001',
                state_id: 4,
                city_id: 5,
                postal_code: null,
                latitude: null,
                longitude: null,
                set_default: 0,
                state_option: north,
                city_option: amsterdam,
            },
            countries: [{ id: 1, name: 'Netherlands' }],
            errors: { city_id: 'Wrong city' },
        },
    });

    expect(wrapper.get('#state_id').text()).toContain('Noord Holland');
    expect(wrapper.get('#city_id').text()).toContain('Amsterdam');
    expect(
        wrapper
            .get('input[type="hidden"][name="state_id"]')
            .attributes('value'),
    ).toBe('4');
    expect(wrapper.text()).toContain('Wrong city');
    expect(selects(wrapper).city.props('disabled')).toBe(false);
    expect(getMock).not.toHaveBeenCalled();
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

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import CurrenciesIndex from '@/pages/Store/Admin/Setup/Currencies/Index.vue';
import OrderConfigurationIndex from '@/pages/Store/Admin/Setup/OrderConfiguration/Index.vue';
import PickupPointsCreate from '@/pages/Store/Admin/Setup/PickupPoints/Create.vue';
import PickupPointsEdit from '@/pages/Store/Admin/Setup/PickupPoints/Edit.vue';
import PickupPointsIndex from '@/pages/Store/Admin/Setup/PickupPoints/Index.vue';
import TaxesEdit from '@/pages/Store/Admin/Setup/Taxes/Edit.vue';
import TaxesIndex from '@/pages/Store/Admin/Setup/Taxes/Index.vue';
import CitiesEdit from '@/pages/Store/Admin/Shipping/Cities/Edit.vue';
import CitiesIndex from '@/pages/Store/Admin/Shipping/Cities/Index.vue';
import ConfigurationIndex from '@/pages/Store/Admin/Shipping/Configuration/Index.vue';
import CountriesIndex from '@/pages/Store/Admin/Shipping/Countries/Index.vue';
import StatesEdit from '@/pages/Store/Admin/Shipping/States/Edit.vue';
import StatesIndex from '@/pages/Store/Admin/Shipping/States/Index.vue';
import ZonesCreate from '@/pages/Store/Admin/Shipping/Zones/Create.vue';
import ZonesEdit from '@/pages/Store/Admin/Shipping/Zones/Edit.vue';
import ZonesIndex from '@/pages/Store/Admin/Shipping/Zones/Index.vue';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';

const { formSlotPropsMock, usePageMock, routerGetMock, routerPostMock } =
    vi.hoisted(() => ({
        formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
        usePageMock: vi.fn(),
        routerGetMock: vi.fn(),
        routerPostMock: vi.fn(),
    }));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formSlotPropsMock),
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const meta = {
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 1,
    from: 1,
    to: 1,
    links: [
        { url: null, label: 'Previous', active: false },
        { url: '/page=1', label: '1', active: true },
        { url: null, label: 'Next', active: false },
    ],
};
const country = {
    id: 1,
    code: 'NL',
    name: 'Netherlands',
    zone_id: 0,
    active: true,
};
const state = { id: 2, name: 'North', country_id: 1, active: true, country };
const city = {
    id: 3,
    name: 'Utrecht',
    state_id: 2,
    cost: '2.50',
    active: true,
    state,
};
const zone = { id: 4, name: 'Europe' };
const manager = { id: 5, name: 'Manager' };
const pickupPoint = {
    id: 6,
    staff_id: 5,
    name: 'Depot',
    address: 'Street',
    phone: '123',
    active: true,
    manager,
};
const tax = { id: 7, name: 'VAT', active: true };
const currency = {
    id: 8,
    name: 'Euro',
    symbol: '€',
    code: 'EUR',
    exchange_rate: '1.00000',
    active: true,
};

const cases = [
    [
        CountriesIndex,
        { countries: { data: [country], meta }, filters: { per_page: 20 } },
        'Netherlands',
    ],
    [
        StatesIndex,
        {
            states: { data: [state], meta },
            countries: [country],
            filters: { per_page: 20 },
        },
        'North',
    ],
    [StatesEdit, { state, countries: [country] }, 'North'],
    [
        CitiesIndex,
        {
            cities: { data: [city], meta },
            states: [state],
            filters: { per_page: 20 },
        },
        'Utrecht',
    ],
    [
        CitiesEdit,
        { city, lang: 'en', name: 'Utrecht', languages: [], states: [state] },
        'Utrecht',
    ],
    [
        ZonesIndex,
        { zones: { data: [zone], meta }, filters: { per_page: 20 } },
        'Europe',
    ],
    [ZonesCreate, { countries: [country] }, 'Netherlands'],
    [ZonesEdit, { zone, countries: [country], countryIds: [1] }, 'Europe'],
    [
        PickupPointsIndex,
        {
            pickupPoints: { data: [pickupPoint], meta },
            filters: { per_page: 20 },
        },
        'Depot',
    ],
    [PickupPointsCreate, { staffOptions: [manager] }, 'Manager'],
    [
        PickupPointsEdit,
        {
            pickupPoint,
            lang: 'en',
            translatedName: 'Depot',
            translatedAddress: 'Street',
            languages: [],
            staffOptions: [manager],
        },
        'Depot',
    ],
    [
        TaxesIndex,
        { taxes: { data: [tax], meta }, filters: { per_page: 20 } },
        'VAT',
    ],
    [TaxesEdit, { tax }, 'VAT'],
    [
        CurrenciesIndex,
        {
            currencies: { data: [currency], meta },
            activeCurrencies: [currency],
            settings: {
                system_default_currency: 8,
                symbol_format: 1,
                decimal_separator: 1,
                no_of_decimals: 2,
            },
            filters: { per_page: 20 },
        },
        'Euro',
    ],
    [
        ConfigurationIndex,
        {
            settings: {
                shipping_type: 'flat_rate',
                flat_rate_shipping_cost: '5',
                shipping_cost_admin: '3',
            },
        },
        'Flat-rate',
    ],
    [
        OrderConfigurationIndex,
        {
            settings: {
                minimum_order_amount_check: true,
                minimum_order_amount: '25',
                default_specific_product: 'MAT-1',
                default_representative_check: true,
                default_representative_id: 5,
            },
            selectedProduct: { id: 9, name: 'Product', mat_id: 'MAT-1' },
            representatives: [manager],
        },
        'Product',
    ],
] as const;

describe('batch one store-admin pages', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        routerGetMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReturnValue({
            url: '/temp/admin/test',
            props: defaultAuthProps({
                store: storeSharedProps({ isSuperAdmin: true }),
            }),
        });
    });

    it.each(cases)(
        'full-mounts %s in StoreAdminLayout',
        (component, props, text) => {
            const wrapper = mount(component as Component, {
                props: props as Record<string, unknown>,
            });

            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.text()).toContain(text);
        },
    );
});

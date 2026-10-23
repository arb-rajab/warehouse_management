import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as carriersIndex } from '@/actions/App/Http/Controllers/Store/Admin/CarrierController';
import { index as citiesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';
const { usePageMock, routerPostMock, slotMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    slotMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

const settings = {
    shipping_type: 'area_wise_shipping',
    flat_rate_shipping_cost: '2.50',
    shipping_cost_admin: '7.50',
};
function mountPage(
    permissions = [
        'shipping_configuration',
        'manage_shipping_cities',
        'manage_carriers',
    ],
) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, { props: { settings } });
}
describe('Shipping configuration', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
        slotMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('uses the store layout and renders all explanatory labels', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.shippingConfiguration.title'),
        );

        for (const key of [
            'method',
            'flatCost',
            'adminCost',
            'productNote',
            'flatNote',
            'sellerNote',
            'areaNote',
            'carrierNote',
            'cities',
            'carriers',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.shippingConfiguration.${key}`),
            );
        }
    });
    it('posts three separate setting scopes and offers all existing methods', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();
        expect(
            wrapper.findAllComponents(Form).map((form) => form.props('action')),
        ).toEqual([update(), update(), update()]);
        expect(
            wrapper
                .findAll('input[name=type]')
                .map((input) => input.attributes('value')),
        ).toEqual([
            'shipping_type',
            'flat_rate_shipping_cost',
            'shipping_cost_admin',
        ]);
        const methods = [
            'product_wise_shipping',
            'flat_rate',
            'seller_wise_shipping',
            'area_wise_shipping',
            'carrier_wise_shipping',
        ];
        expect(
            wrapper
                .findAll('#shipping_type option')
                .map((option) => option.text()),
        ).toEqual(
            methods.map((method) => t(`store.shippingConfiguration.${method}`)),
        );
        expect(wrapper.get('#shipping_type').element).toHaveProperty(
            'value',
            'area_wise_shipping',
        );

        for (const id of ['flat_rate_shipping_cost', 'shipping_cost_admin']) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                type: 'number',
                required: '',
                min: '0',
                step: 'any',
            });
        }

        for (const form of wrapper.findAllComponents(Form)) {
            const scope = form.get('input[name=type]').attributes('value');
            expect(form.get(`#${scope}`).attributes('required')).toBeDefined();
            expect(
                form
                    .get(`label[for=${scope}]`)
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(true);
            expect(form.findAllComponents(RequiredMark)).toHaveLength(1);
            expect(form.findAll('[data-required-legend]')).toHaveLength(1);
        }
    });
    it('uses Inertia for both cities and carriers', () => {
        const wrapper = mountPage();
        expect(
            wrapper
                .findAllComponents(Link)
                .some(
                    (link) =>
                        JSON.stringify(link.props('href')) ===
                        JSON.stringify(citiesIndex()),
                ),
        ).toBe(true);
        const carrierLink = wrapper
            .findAllComponents(Link)
            .find(
                (link) =>
                    JSON.stringify(link.props('href')) ===
                    JSON.stringify(carriersIndex()),
            );
        expect(carrierLink?.text()).toBe(
            t('store.shippingConfiguration.carriers'),
        );
    });
    it('hides forms and related links without their permissions', () => {
        const wrapper = mountPage([]);
        expect(wrapper.findComponent(Form).exists()).toBe(false);
        expect(wrapper.find(`a[href="${carriersIndex.url()}"]`).exists()).toBe(
            false,
        );
        expect(wrapper.find(`a[href="${citiesIndex.url()}"]`).exists()).toBe(
            false,
        );
    });
    it('renders every server error', () => {
        slotMock.mockReturnValue({
            errors: {
                shipping_type: 'Invalid method',
                flat_rate_shipping_cost: 'Invalid flat cost',
                shipping_cost_admin: 'Invalid admin cost',
            },
            processing: true,
        });
        const wrapper = mountPage();

        for (const error of [
            'Invalid method',
            'Invalid flat cost',
            'Invalid admin cost',
        ]) {
            expect(wrapper.text()).toContain(error);
        }

        expect(
            wrapper
                .findAll('button[type=submit]')
                .every((button) => button.attributes('disabled') !== undefined),
        ).toBe(true);
    });
});

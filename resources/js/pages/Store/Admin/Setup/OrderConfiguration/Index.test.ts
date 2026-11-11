import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    representatives,
    update,
    updateDelivery,
    updateRepresentative,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import { search } from '@/actions/App/Http/Controllers/Store/Admin/ProductSearchController';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';
import { deliverySettings, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { SearchOption } from '@/types/searchSelect';
import type { StoreDeliverySettings } from '@/types/store';
import Index from './Index.vue';
const { usePageMock, routerPostMock, slotMock, getMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    slotMock: vi.fn(() => ({ errors: {}, processing: false })),
    getMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: () => ({ get: getMock }),
        router: { post: routerPostMock },
    };
});
const settings = {
    minimum_order_amount_check: true,
    minimum_order_amount: '25.50',
    default_specific_product: '42',
    default_representative_check: false,
    default_representative_id: 7,
};
const selectedProduct: SearchOption = {
    id: 42,
    label: 'Special product',
    description: '#42',
    icon: 'package',
    extra: { mat_id: '42' },
};
const selectedRepresentative: SearchOption = {
    id: 7,
    label: 'Representative',
    description: '0612345678 · rep@example.test',
    icon: 'user',
    badge: { text: '10% discount', tone: 'info' },
};
function mountPage(
    permissions = ['order_configuration'],
    delivery: StoreDeliverySettings = deliverySettings(),
) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            settings,
            selectedProduct,
            selectedRepresentative,
            delivery,
        },
    });
}
describe('Order configuration', () => {
    beforeEach(() => {
        getMock.mockReset();
        slotMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('uses the store layout and renders all static labels and the selected product and representative', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.orderConfiguration.title'),
        );

        for (const key of [
            'minimum',
            'minimumCheck',
            'amount',
            'product',
            'searchHint',
            'representative',
            'representativeCheck',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.orderConfiguration.${key}`),
            );
        }

        expect(wrapper.text()).toContain('Default product ID');
        expect(wrapper.text()).toContain(
            'Enter at least 4 characters to search by product ID.',
        );
        expect(ar.store.orderConfiguration.product).toBe(
            'معرّف المنتج الافتراضي',
        );
        expect(ar.store.orderConfiguration.searchHint).toBe(
            'أدخل 4 أحرف على الأقل للبحث بمعرّف المنتج.',
        );
        expect(wrapper.get('#default_specific_product').text()).toContain(
            'Special product',
        );
        expect(wrapper.get('#default_representative_id').text()).toContain(
            'Representative',
        );
        // Both are searched on demand: nothing is listed or fetched on load.
        expect(getMock).not.toHaveBeenCalled();
        wrapper.unmount();
    });
    it('keeps the minimum/product and representative forms separate and posts both picks as ids', () => {
        const wrapper = mountPage();
        expect(
            wrapper.findAllComponents(Form).map((form) => form.props('action')),
        ).toEqual([update(), updateRepresentative(), updateDelivery()]);
        expect(wrapper.get('#minimum_order_amount').attributes()).toMatchObject(
            { type: 'number', required: '', min: '0', step: 'any' },
        );
        const [product, representative] =
            wrapper.findAllComponents(StoreSearchSelect);
        expect(product.props()).toMatchObject({
            id: 'default_specific_product',
            name: 'default_specific_product',
            endpoint: search,
            minChars: 4,
            required: true,
            modelValue: selectedProduct,
        });
        expect(representative.props()).toMatchObject({
            id: 'default_representative_id',
            name: 'default_representative_id',
            endpoint: representatives,
            required: true,
            modelValue: selectedRepresentative,
        });
        expect(
            wrapper
                .get('input[type="hidden"][name="default_specific_product"]')
                .attributes('value'),
        ).toBe('42');
        expect(
            wrapper
                .get('input[type="hidden"][name="default_representative_id"]')
                .attributes('value'),
        ).toBe('7');
        expect(
            wrapper
                .get('#default_specific_product')
                .attributes('aria-required'),
        ).toBe('true');
        wrapper.unmount();
    });
    it('changes the posted product id when another product is picked', async () => {
        const wrapper = mountPage();

        wrapper.getComponent(StoreSearchSelect).vm.$emit('update:modelValue', {
            id: 55,
            label: '<img src=x onerror=alert(1)>',
            icon: 'package',
        });
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .get('input[type="hidden"][name="default_specific_product"]')
                .attributes('value'),
        ).toBe('55');
        expect(wrapper.get('#default_specific_product').text()).toContain(
            '<img src=x onerror=alert(1)>',
        );
        expect(wrapper.get('main').find('img').exists()).toBe(false);
        wrapper.unmount();
    });
    it('hides both writable groups without permission', () => {
        const wrapper = mountPage([]);
        expect(wrapper.findComponent(Form).exists()).toBe(false);
        wrapper.unmount();
    });
    it('forwards field errors and disables submits during a request', () => {
        slotMock.mockReturnValue({
            errors: {
                minimum_order_amount_check: 'Invalid check',
                minimum_order_amount: 'Invalid amount',
                default_specific_product: 'Invalid product',
                default_representative_check: 'Invalid representative check',
                default_representative_id: 'Invalid representative',
            },
            processing: true,
        });
        const wrapper = mountPage();

        for (const error of Object.values(slotMock().errors)) {
            expect(wrapper.text()).toContain(error);
        }

        expect(
            wrapper
                .findAll('button[type=submit]')
                .every((button) => button.attributes('disabled') !== undefined),
        ).toBe(true);
        wrapper.unmount();
    });
    describe('delivery dates', () => {
        const saved = deliverySettings({
            saved: {
                delivery_lead_days: 3,
                delivery_timezone: 'Europe/London',
            },
            effective: {
                delivery_lead_days: { value: 3, source: 'admin' },
                delivery_timezone: { value: 'Europe/London', source: 'admin' },
            },
        });

        function deliveryForm(wrapper: ReturnType<typeof mountPage>) {
            return wrapper.get('[data-section="delivery_settings"] form');
        }

        it('shows what is in use with its source, and both fields empty when nothing is saved', () => {
            const wrapper = mountPage();
            const section = wrapper.get('[data-section="delivery_settings"]');

            expect(section.text()).toContain(
                t('store.orderConfiguration.delivery.inUse.leadDays', {
                    value: 2,
                    source: t('store.orderConfiguration.delivery.sources.env'),
                }),
            );
            expect(section.text()).toContain(
                t('store.orderConfiguration.delivery.inUse.timezone', {
                    value: 'Europe/Amsterdam',
                    source: t('store.orderConfiguration.delivery.sources.env'),
                }),
            );
            const days = wrapper.get('#delivery_lead_days');
            expect(days.attributes()).toMatchObject({
                type: 'number',
                min: '0',
                max: '30',
                step: '1',
                placeholder: '2',
            });
            expect((days.element as HTMLInputElement).value).toBe('');
            expect(
                (wrapper.get('#delivery_timezone').element as HTMLSelectElement)
                    .value,
            ).toBe('');
            expect(wrapper.get('#delivery_timezone').text()).toContain(
                t('store.orderConfiguration.delivery.timezoneServer', {
                    timezone: 'Europe/Amsterdam',
                }),
            );
            wrapper.unmount();
        });

        it('shows the saved values selected and "saved in admin"', () => {
            const wrapper = mountPage(['order_configuration'], saved);

            expect(
                (wrapper.get('#delivery_lead_days').element as HTMLInputElement)
                    .value,
            ).toBe('3');
            expect(
                (wrapper.get('#delivery_timezone').element as HTMLSelectElement)
                    .value,
            ).toBe('Europe/London');
            expect(
                wrapper.get('[data-section="delivery_settings"]').text(),
            ).toContain(t('store.orderConfiguration.delivery.sources.admin'));
            wrapper.unmount();
        });

        it('has no required field, mark or legend: an empty field uses .env', () => {
            const wrapper = mountPage();
            const form = deliveryForm(wrapper);

            expect(form.find('[required]').exists()).toBe(false);
            expect(form.find('[data-required-mark]').exists()).toBe(false);
            expect(form.find('[data-required-legend]').exists()).toBe(false);
            expect(form.attributes('novalidate')).toBeDefined();
            wrapper.unmount();
        });

        it('offers every timezone it was given, and the .env choice first', () => {
            const wrapper = mountPage();
            const options = wrapper
                .get('#delivery_timezone')
                .findAll('option')
                .map((option) => option.attributes('value'));

            expect(options).toEqual([
                '',
                'Europe/Amsterdam',
                'Europe/London',
                'UTC',
            ]);
            wrapper.unmount();
        });

        it('blocks the submit and shows the message for lead days above the maximum', async () => {
            const wrapper = mountPage();
            const form = deliveryForm(wrapper);
            const input = wrapper.get('#delivery_lead_days');

            await input.setValue('31');
            await form.trigger('submit');
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-section="delivery_settings"]').text(),
            ).toContain(
                'The delivery lead days field must not be greater than 30.',
            );
            expect(input.attributes('aria-invalid')).toBe('true');
            wrapper.unmount();
        });

        it('shows the server errors of both fields', () => {
            slotMock.mockReturnValue({
                errors: {
                    delivery_lead_days: 'Lead days is invalid',
                    delivery_timezone: 'Timezone is invalid',
                },
                processing: false,
            });
            const wrapper = mountPage();
            const section = wrapper.get('[data-section="delivery_settings"]');

            expect(section.text()).toContain('Lead days is invalid');
            expect(section.text()).toContain('Timezone is invalid');
            wrapper.unmount();
        });

        it('has the same delivery strings in English and Arabic', () => {
            expect(Object.keys(ar.store.orderConfiguration.delivery)).toEqual(
                Object.keys(en.store.orderConfiguration.delivery),
            );
            expect(
                Object.keys(ar.store.orderConfiguration.delivery.inUse),
            ).toEqual(Object.keys(en.store.orderConfiguration.delivery.inUse));
        });
    });
});

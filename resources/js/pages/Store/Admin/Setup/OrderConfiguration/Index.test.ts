import { Form, Head } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import {
    index,
    update,
    updateRepresentative,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import { search } from '@/actions/App/Http/Controllers/Store/Admin/ProductSearchController';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { OrderConfigurationProduct } from '@/types/store';
import Index from './Index.vue';
const { usePageMock, routerPostMock, slotMock, getMock, cancelMock, httpMock } =
    vi.hoisted(() => ({
        usePageMock: vi.fn(),
        routerPostMock: vi.fn(),
        slotMock: vi.fn(() => ({ errors: {}, processing: false })),
        getMock: vi.fn(),
        cancelMock: vi.fn(),
        httpMock: vi.fn(),
    }));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: httpMock,
        router: { post: routerPostMock },
    };
});
const settings = {
    minimum_order_amount_check: true,
    minimum_order_amount: '25.50',
    default_specific_product: 'MAT-42',
    default_representative_check: false,
    default_representative_id: 7,
};
const selectedProduct = { id: 42, name: 'Special product', mat_id: 'MAT-42' };
function mountPage(permissions = ['order_configuration']) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            settings,
            selectedProduct,
            representatives: [{ id: 7, name: 'Representative' }],
        },
    });
}
describe('Order configuration', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        getMock.mockReset();
        cancelMock.mockReset();
        slotMock.mockReturnValue({ errors: {}, processing: false });
        httpMock.mockReturnValue(
            reactive({
                query: '',
                errors: {},
                processing: false,
                get: getMock,
                cancel: cancelMock,
            }),
        );
    });
    afterEach(() => vi.useRealTimers());
    it('uses the store layout and renders all static labels and the selected product as text', () => {
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

        expect(wrapper.text()).toContain(
            t('store.orderConfiguration.selected', { name: 'Special product' }),
        );
        expect(wrapper.get('#default_specific_product').element).toHaveProperty(
            'value',
            'MAT-42',
        );
        wrapper.unmount();
    });
    it('keeps the minimum/product and representative forms separate with native constraints', () => {
        const wrapper = mountPage();
        expect(
            wrapper.findAllComponents(Form).map((form) => form.props('action')),
        ).toEqual([update(), updateRepresentative()]);
        expect(wrapper.get('#minimum_order_amount').attributes()).toMatchObject(
            { type: 'number', required: '', min: '0', step: 'any' },
        );
        expect(
            wrapper.get('#default_specific_product').attributes(),
        ).toMatchObject({ required: '', maxlength: '255' });
        expect(
            wrapper.get('#default_representative_id').element,
        ).toHaveProperty('value', '7');
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
    it('waits for four characters, debounces search and safely selects a MAT id', async () => {
        const malicious = {
            id: 55,
            name: '<img src=x onerror=alert(1)>',
            mat_id: 'MAT-55',
        };
        getMock.mockResolvedValue([malicious]);
        const wrapper = mountPage();
        const input = wrapper.get('#default_specific_product');
        await input.setValue('abc');
        await vi.advanceTimersByTimeAsync(350);
        expect(getMock).not.toHaveBeenCalled();
        await input.setValue('abcd');
        await vi.advanceTimersByTimeAsync(299);
        expect(getMock).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();
        expect(httpMock().query).toBe('abcd');
        expect(getMock).toHaveBeenCalledWith(search.url());
        expect(wrapper.text()).toContain(
            t('store.orderConfiguration.searchResults'),
        );
        expect(wrapper.find('img').exists()).toBe(false);
        const button = wrapper
            .findAll('button[type=button]')
            .find((button) => button.text().includes(malicious.name));
        expect(button).toBeDefined();
        await button!.trigger('click');
        expect(input.element).toHaveProperty('value', 'MAT-55');
        expect(wrapper.text()).toContain(
            t('store.orderConfiguration.selected', { name: malicious.name }),
        );
        expect(wrapper.find('img').exists()).toBe(false);
        wrapper.unmount();
    });
    it('discards an older response after the query changes', async () => {
        let finish: (products: OrderConfigurationProduct[]) => void = () => {};
        getMock
            .mockImplementationOnce(
                () =>
                    new Promise<OrderConfigurationProduct[]>((resolve) => {
                        finish = resolve;
                    }),
            )
            .mockResolvedValueOnce([
                { id: 2, name: 'Current result', mat_id: 'NEW' },
            ]);
        const wrapper = mountPage();
        await wrapper.get('#default_specific_product').setValue('older');
        await vi.advanceTimersByTimeAsync(300);
        await wrapper.get('#default_specific_product').setValue('newer');
        await vi.advanceTimersByTimeAsync(300);
        finish([{ id: 1, name: 'Stale result', mat_id: 'OLD' }]);
        await flushPromises();
        expect(wrapper.text()).toContain('Current result');
        expect(wrapper.text()).not.toContain('Stale result');
        wrapper.unmount();
    });
    it('clears a pending request when unmounted', async () => {
        const wrapper = mountPage();
        await wrapper.get('#default_specific_product').setValue('pending');
        wrapper.unmount();
        await vi.advanceTimersByTimeAsync(300);
        expect(getMock).not.toHaveBeenCalled();
        expect(cancelMock).toHaveBeenCalled();
    });
    it('shows empty results, loading and errors from the HTTP helper', async () => {
        getMock.mockResolvedValue([]);
        const wrapper = mountPage();
        await wrapper.get('#default_specific_product').setValue('empty');
        await vi.advanceTimersByTimeAsync(300);
        expect(wrapper.text()).toContain(
            t('store.orderConfiguration.noResults'),
        );
        Object.assign(httpMock(), {
            processing: true,
            errors: { query: 'Search unavailable' },
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain(
            t('store.orderConfiguration.searching'),
        );
        expect(wrapper.text()).toContain('Search unavailable');
        wrapper.unmount();
    });
});

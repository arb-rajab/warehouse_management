import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    index as brandDiscountsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BrandDiscountController';
import DataTable from '@/components/DataTable.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import Pagination from '@/components/Pagination.vue';
import StoreBrandDiscountFormFields from '@/components/StoreBrandDiscountFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeBrandDiscount,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreBrandDiscount } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(formSlotPropsMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
function mountPage(permissions = ['view_all_brands', 'product_edit']) {
    usePageMock.mockReturnValue({
        url: brandDiscountsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            brands: paginated([
                storeBrandDiscount({
                    logo_url: 'https://example.com/logo.png',
                }),
                storeBrandDiscount({
                    id: 4,
                    name: '<img src=x onerror=alert(1)>',
                    logo_url: null,
                }),
            ]),
            filters: { per_page: 20, search: null },
        },
    });
}
describe('Store brand discounts', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('mounts the store layout and translated labels with narrow table and field props', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.findComponent(Head).props('title')).toBe(
            t('store.brandDiscount.title'),
        );
        expect(
            wrapper
                .findComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreBrandDiscount[];
                        emptyMessage: string;
                    }>
                >(DataTable)
                .props(),
        ).toMatchObject({
            columns: ['logo', 'name', 'action'].map((key) =>
                t(`store.brandDiscount.${key}`),
            ),
            emptyMessage: t('store.brandDiscount.empty'),
        });
        expect(
            wrapper
                .findAllComponents(StoreBrandDiscountFormFields)
                .map((fields) => fields.props('brandId')),
        ).toEqual([3, 4]);

        for (const key of [
            'title',
            'search',
            'set',
            'discount',
            'dateRange',
            'dateHelp',
        ]) {
            expect(wrapper.text()).toContain(t(`store.brandDiscount.${key}`));
        }

        expect(wrapper.text()).toContain(t('common.filters.apply'));
        expect(wrapper.get('td img').attributes()).toMatchObject({
            src: 'https://example.com/logo.png',
            alt: 'Dates brand',
        });
        expect(wrapper.findAll('td img')).toHaveLength(1);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(
            wrapper.get('input[name="search"]').attributes('maxlength'),
        ).toBe('200');
    });
    it('keeps one confirmation per row and targets the preserved mutation action', async () => {
        const wrapper = mountPage();
        const forms = wrapper.findAllComponents(Form);
        expect(forms[0].props('action')).toEqual(update({ mergeQuery: {} }));
        expect(forms[0].attributes('id')).toBe('brand-discount-3');
        expect(wrapper.findAll('[role="dialog"]')).toHaveLength(0);
        await forms[0].get('button[type="button"]').trigger('click');
        expect(wrapper.findAll('[role="dialog"]')).toHaveLength(1);
        expect(wrapper.text()).toContain(t('store.brandDiscount.confirmTitle'));
        expect(wrapper.text()).toContain(
            t('store.brandDiscount.confirmMessage', { brand: 'Dates brand' }),
        );
        const confirm = wrapper
            .findAllComponents(SubmitButton)
            .find(
                (button) =>
                    button.props('label') === t('store.brandDiscount.confirm'),
            );
        expect(confirm?.props('form')).toBe('brand-discount-3');
        expect(confirm?.props('processingLabel')).toBe(
            t('store.brandDiscount.saving'),
        );
        expect(
            wrapper.findAllComponents(FilterDialog)[0].props('closeLabel'),
        ).toBe(t('common.close'));
        forms[0].vm.$emit('success');
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('[role="dialog"]')).toHaveLength(0);
    });
    it('omits write forms when only the read permission is present', () => {
        const wrapper = mountPage(['view_all_brands']);
        expect(wrapper.findComponent(Form).exists()).toBe(false);
        expect(
            wrapper.findComponent(StoreBrandDiscountFormFields).exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain(t('store.brandDiscount.readOnly'));
    });
    it('forwards errors and handles the form processing state', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: { discount: 'Invalid discount', brand_id: 'Missing brand' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.findComponent(StoreBrandDiscountFormFields).props('errors'),
        ).toEqual({ discount: 'Invalid discount', brand_id: 'Missing brand' });
        expect(wrapper.text()).toContain('Missing brand');
        expect(
            wrapper
                .findComponent(Form)
                .get('button[type="button"]')
                .attributes('disabled'),
        ).toBeDefined();
    });
    it('preserves search when applying filters and changing page size', async () => {
        const wrapper = mountPage();
        await wrapper.get('input[name="search"]').setValue('Dates');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenLastCalledWith(
            brandDiscountsIndex().url,
            { search: 'Dates', per_page: 20 },
            { preserveState: true, replace: true },
        );
        expect(wrapper.findComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        wrapper.findComponent(Pagination).vm.$emit('update:per-page', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            brandDiscountsIndex().url,
            { search: 'Dates', per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});

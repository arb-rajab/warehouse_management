import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    index as reviewsIndex,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ReviewController';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeReview, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreReview } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(formSlotPropsMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
function mountPage(
    permissions = ['view_product_reviews', 'publish_product_review'],
    published = true,
) {
    usePageMock.mockReturnValue({
        url: reviewsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            reviews: paginated([
                storeReview({
                    published,
                    comment: '<img src=x onerror=alert(1)>',
                }),
            ]),
            filters: { per_page: 20, rating: null },
        },
    });
}
describe('Store product reviews', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('mounts the store layout with title, all labels and escaped comments', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.findComponent(Head).props('title')).toBe(
            t('store.reviews.title'),
        );
        expect(
            wrapper
                .findComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreReview[];
                        emptyMessage: string;
                    }>
                >(DataTable)
                .props('columns'),
        ).toEqual(
            [
                'product',
                'owner',
                'customer',
                'rating',
                'comment',
                'published',
            ].map((key) => t(`store.reviews.${key}`)),
        );
        expect(
            wrapper
                .findComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreReview[];
                        emptyMessage: string;
                    }>
                >(DataTable)
                .props('emptyMessage'),
        ).toBe(t('store.reviews.empty'));

        for (const key of [
            'title',
            'filterRating',
            'newest',
            'highRating',
            'lowRating',
            'yes',
        ]) {
            expect(wrapper.text()).toContain(t(`store.reviews.${key}`));
        }

        expect(wrapper.text()).toContain(t('common.filters.apply'));
        expect(wrapper.find('td img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.get('td a').attributes('href')).toBe(
            '/temp/product/dates',
        );
        expect(wrapper.get('td a').attributes('rel')).toBe(
            'noopener noreferrer',
        );
    });
    it('posts publication status to the bound action and handles form errors and processing', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { status: 'Invalid status' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            updatePublished(storeReview(), { mergeQuery: {} }),
        );
        expect(wrapper.get('input[name="status"]').attributes('value')).toBe(
            '0',
        );
        expect(wrapper.text()).toContain('Invalid status');
        const toggle = wrapper.getComponent(StoreStatusSwitch);
        expect(toggle.props()).toMatchObject({
            action: updatePublished(storeReview(), { mergeQuery: {} }),
            checked: true,
            label: t('store.reviews.published'),
            stateLabels: {
                on: t('store.reviews.yes'),
                off: t('store.reviews.no'),
            },
        });
        const button = toggle.get('[role="switch"]');
        expect(button.attributes('aria-checked')).toBe('true');
        expect(button.attributes('aria-busy')).toBe('true');
        expect(button.attributes('disabled')).toBeDefined();
    });
    it('offers publishing hidden reviews and omits write controls without permission', () => {
        const hidden = mountPage(undefined, false);
        expect(hidden.get('input[name="status"]').attributes('value')).toBe(
            '1',
        );
        expect(hidden.get('[role="switch"]').attributes('aria-checked')).toBe(
            'false',
        );
        expect(hidden.get('[role="switch"]').text()).toBe(
            t('store.reviews.no'),
        );
        const readonly = mountPage(['view_product_reviews']);
        expect(readonly.findComponent(Form).exists()).toBe(false);
        expect(readonly.findComponent(StoreStatusSwitch).exists()).toBe(false);
        expect(readonly.text()).toContain(t('store.reviews.yes'));
        expect(mountPage(['view_product_reviews'], false).text()).toContain(
            t('store.reviews.no'),
        );
    });
    it('applies sorting and per-page changes with the selected filter', async () => {
        const wrapper = mountPage();
        await wrapper.get('select[name="rating"]').setValue('rating,asc');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenLastCalledWith(
            reviewsIndex().url,
            { rating: 'rating,asc', per_page: 20 },
            { preserveState: true, replace: true },
        );
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        wrapper.getComponent(Pagination).vm.$emit('update:per-page', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            reviewsIndex().url,
            { rating: 'rating,asc', per_page: 10 },
            { preserveState: true, replace: true },
        );
    });

    it('links the reviewer to their edit page only with the edit_customer permission', () => {
        const customerCell = (wrapper: ReturnType<typeof mountPage>) =>
            wrapper.get('tbody tr').findAll('td')[2];

        const allowed = mountPage(['view_product_reviews', 'edit_customer']);
        expect(customerCell(allowed).get('a').attributes('href')).toBe(
            customerEdit(9).url,
        );
        expect(customerCell(allowed).text()).toContain('customer@example.com');

        const denied = mountPage(['view_product_reviews']);
        expect(customerCell(denied).find('a').exists()).toBe(false);
        expect(customerCell(denied).text()).toContain('Customer');
    });
});

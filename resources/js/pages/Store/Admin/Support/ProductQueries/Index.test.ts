import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as productEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeProductQuery,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import {
    expectColumnCanBeHidden,
    expectColumnLocked,
} from '@/testing/tableColumns';
import Index from './Index.vue';
const { usePageMock, routerGetMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});
beforeEach(() => {
    resetMocks({ usePageMock, routerGetMock, routerPostMock });
    usePageMock.mockReturnValue({
        url: '/temp/admin/conversations',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: [
                    'view_all_product_conversations',
                    'reply_to_product_conversations',
                    'delete_product_conversations',
                    'view_all_product_queries',
                    'reply_to_product_queries',
                ],
            }),
        }),
    });
});

describe('Product query index', () => {
    function mountPage(rows = [storeProductQuery()]) {
        return mount(Index, {
            props: { queries: paginated(rows), filters: { per_page: 20 } },
        });
    }
    it('renders the store layout, titles, complete table labels and view action', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.support.queries'),
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            t('store.support.queries'),
        );
        expect(wrapper.findAll('th').map((th) => th.text())).toEqual(
            [
                'customer',
                'product',
                'question',
                'reply',
                'status',
                'actions',
            ].map((key) => t(`store.support.${key}`)),
        );
        expect(wrapper.text()).toContain('Customer');
        expect(wrapper.text()).toContain('Rice');
        expect(wrapper.text()).toContain('Is this available?');
        expect(wrapper.text()).toContain(t('store.support.unanswered'));
        expect(wrapper.getComponent(TableActionLink).props('href')).toEqual({
            url: '/temp/admin/product-queries/43',
            method: 'get',
        });
        expect(wrapper.getComponent(TableActionLink).text()).toContain(
            t('store.support.view'),
        );
    });
    it('renders answer and missing relation states with all user text escaped', () => {
        const wrapper = mountPage([
            storeProductQuery({
                user: null,
                product: null,
                reply: '<img src=x onerror=alert(1)>',
            }),
        ]);
        expect(wrapper.text()).toContain(t('store.support.missingUser'));
        expect(wrapper.text()).toContain(t('store.support.missingProduct'));
        expect(wrapper.text()).toContain(t('store.support.answered'));
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('td img').exists()).toBe(false);
    });
    it('renders empty results and passes pagination with the page selector', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain(t('store.support.emptyQueries'));
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/product-queries',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });

    it('links the customer and the product to their edit pages by permission', () => {
        const cells = (w: ReturnType<typeof mountPage>) =>
            w.get('tbody tr').findAll('td');

        usePageMock.mockReturnValue({
            url: '/temp/admin/x',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: [
                        'view_all_product_queries',
                        'edit_customer',
                        'product_edit',
                    ],
                }),
            }),
        });
        const allowed = cells(mountPage());
        expect(allowed[0].get('a').attributes('href')).toBe(
            customerEdit(8).url,
        );
        expect(allowed[1].get('a').attributes('href')).toBe(
            productEdit(10).url,
        );

        usePageMock.mockReturnValue({
            url: '/temp/admin/x',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['view_all_product_queries'],
                }),
            }),
        });
        const denied = cells(mountPage());
        expect(denied[0].find('a').exists()).toBe(false);
        expect(denied[1].find('a').exists()).toBe(false);
        expect(denied[0].text()).toBe('Customer');
        expect(denied[1].text()).toBe('Rice');
    });

    it('lets staff hide an optional column from the header and every row, and keeps the identifier', async () => {
        window.localStorage.clear();
        const wrapper = mountPage();
        await expectColumnCanBeHidden(wrapper, 'support.productQueries', {
            key: 'reply',
            label: t('store.support.reply'),
        });
        expectColumnLocked(wrapper, t('store.support.customer'));
        window.localStorage.clear();
    });
});

import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PageHeader from '@/components/PageHeader.vue';
import StoreSupportReplyForm from '@/components/StoreSupportReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeProductQuery, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Show from './Show.vue';
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

describe('Product query show', () => {
    function mountPage(canReply = true) {
        return mount(Show, { props: { query: storeProductQuery(), canReply } });
    }
    it('renders all detail labels, product title and list link inside the store layout', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe('Rice');
        expect(wrapper.getComponent(PageHeader).props('title')).toBe('Rice');
        expect(wrapper.findAll('dt').map((dt) => dt.text())).toEqual(
            ['customer', 'date', 'question', 'reply'].map((key) =>
                t(`store.support.${key}`),
            ),
        );
        expect(wrapper.text()).toContain(t('store.support.unanswered'));
        expect(wrapper.getComponent(TableActionLink).props('href')).toEqual({
            url: '/temp/admin/product-queries',
            method: 'get',
        });
        expect(wrapper.getComponent(TableActionLink).text()).toBe(
            t('store.support.back'),
        );
    });
    it('gates replies by record restrictions and the exact write permission', () => {
        expect(mountPage().getComponent(StoreSupportReplyForm).props()).toEqual(
            {
                action: {
                    url: '/temp/admin/product-queries/43',
                    method: 'put',
                },
                field: 'reply',
            },
        );
        expect(
            mountPage(false).findComponent(StoreSupportReplyForm).exists(),
        ).toBe(false);
        usePageMock.mockReturnValue({
            url: '/temp/admin/product-queries/43',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['view_all_product_queries'],
                }),
            }),
        });
        expect(mountPage().findComponent(StoreSupportReplyForm).exists()).toBe(
            false,
        );
    });
    it('renders missing relations and existing answers with escaped HTML', () => {
        const wrapper = mount(Show, {
            props: {
                query: storeProductQuery({
                    user: null,
                    product: null,
                    reply: '<script>bad</script>',
                    created_at: null,
                }),
                canReply: false,
            },
        });
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.support.missingProduct'),
        );
        expect(wrapper.text()).toContain(t('store.support.missingUser'));
        expect(wrapper.text()).toContain('<script>bad</script>');
        expect(wrapper.find('dd script').exists()).toBe(false);
    });
});

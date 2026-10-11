import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeConversation,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
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

describe('Conversation index', () => {
    function mountPage(rows = [storeConversation()], enabled = true) {
        return mount(Index, {
            props: {
                conversations: paginated(rows),
                filters: { per_page: 20 },
                enabled,
            },
        });
    }
    it('uses the store layout, titles and complete table labels', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.support.conversations'),
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            t('store.support.conversations'),
        );
        expect(wrapper.findAll('th').map((th) => th.text())).toEqual(
            ['date', 'title', 'sender', 'receiver', 'actions'].map((key) =>
                t(`store.support.${key}`),
            ),
        );
        expect(wrapper.text()).toContain('Delivery question');
        expect(wrapper.text()).toContain('Customer');
        expect(wrapper.text()).toContain(t('store.support.new'));
    });
    it('links to the bound record and deletes with the query and native confirmation', () => {
        const wrapper = mountPage();
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links[0].props('href')).toEqual({
            url: '/temp/admin/conversations/41/show',
            method: 'get',
        });
        expect(links[0].text()).toContain(t('store.support.view'));
        expect(links[1].props('href')).toEqual({
            url: '/temp/admin/conversations/41',
            method: 'delete',
        });
        expect(links[1].text()).toContain(t('store.support.delete'));
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Delivery question' }),
        );
        confirm.mockRestore();
    });
    it('hides delete without the permission and renders missing users safely', () => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/conversations',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['view_all_product_conversations'],
                }),
            }),
        });
        const wrapper = mountPage([
            storeConversation({
                sender: null,
                receiver: null,
                title: '<img src=x onerror=alert(1)>',
            }),
        ]);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(1);
        expect(wrapper.text()).toContain(t('store.support.missingUser'));
        expect(wrapper.find('td img').exists()).toBe(false);
    });
    it('renders the empty and disabled states and wires the page selector', () => {
        const wrapper = mountPage([], false);
        expect(wrapper.text()).toContain(t('store.support.emptyConversations'));
        expect(wrapper.text()).toContain(t('store.support.disabled'));
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/conversations',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});

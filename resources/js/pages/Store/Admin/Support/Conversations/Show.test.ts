import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSupportReplyForm from '@/components/StoreSupportReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeConversation,
    storeMessage,
    storeSharedProps,
} from '@/testing/factories';
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

describe('Conversation show', () => {
    function mountPage(canReply = true) {
        return mount(Show, {
            props: {
                conversation: storeConversation(),
                messages: paginated([
                    storeMessage({ message: '<img src=x onerror=alert(1)>' }),
                ]),
                filters: { per_page: 20 },
                canReply,
            },
        });
    }
    it('renders escaped messages, participants, title and list link in the store layout', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            'Delivery question',
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            'Delivery question',
        );
        expect(wrapper.getComponent(TableActionLink).props('href')).toEqual({
            url: '/temp/admin/conversations',
            method: 'get',
        });
        expect(wrapper.getComponent(TableActionLink).text()).toBe(
            t('store.support.back'),
        );
        expect(wrapper.text()).toContain(
            t('store.support.between', {
                sender: 'Customer',
                receiver: 'Store owner',
            }),
        );
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('li img').exists()).toBe(false);
    });
    it('gates the reply form by both the record restriction and write permission', () => {
        expect(mountPage().getComponent(StoreSupportReplyForm).props()).toEqual(
            {
                action: {
                    url: '/temp/admin/conversations/41/reply',
                    method: 'post',
                },
                field: 'message',
            },
        );
        expect(
            mountPage(false).findComponent(StoreSupportReplyForm).exists(),
        ).toBe(false);
        usePageMock.mockReturnValue({
            url: '/temp/admin/conversations/41/show',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['view_all_product_conversations'],
                }),
            }),
        });
        expect(mountPage().findComponent(StoreSupportReplyForm).exists()).toBe(
            false,
        );
    });
    it('passes message pagination and navigates to the same bound conversation', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/conversations/41/show',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('provides fallbacks for deleted participants and a missing title', () => {
        const wrapper = mount(Show, {
            props: {
                conversation: storeConversation({
                    title: null,
                    sender: null,
                    receiver: null,
                }),
                messages: paginated([
                    storeMessage({ user: null, created_at: null }),
                ]),
                filters: {},
                canReply: false,
            },
        });
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.support.conversations'),
        );
        expect(wrapper.text()).toContain(t('store.support.missingUser'));
    });
});

import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeSupportTicket,
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
        url: '/temp/admin/support_ticket',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['view_all_support_tickets'],
            }),
        }),
    });
});
describe('Support ticket list', () => {
    function mountPage(
        rows = [storeSupportTicket()],
        search: string | null = null,
    ) {
        return mount(Index, {
            props: {
                tickets: paginated(rows),
                filters: { per_page: 20, search },
            },
        });
    }
    it('uses store layout, full labels, unread state and a full-page visit into the retained Blade detail', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.support.tickets'),
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            t('store.support.tickets'),
        );
        expect(wrapper.findAll('th').map((th) => th.text())).toEqual(
            [
                'ticketCode',
                'date',
                'subject',
                'customer',
                'status',
                'lastReply',
                'actions',
            ].map((key) => t(`store.support.${key}`)),
        );
        expect(wrapper.text()).toContain('#123456');
        expect(wrapper.text()).toContain('Customer');
        expect(wrapper.text()).toContain(t('store.support.pending'));
        expect(wrapper.text()).toContain(t('store.support.new'));
        const view = wrapper.getComponent(TableActionLink);
        expect(view.props('inertia')).toBe(false);
        expect(view.get('a').attributes('href')).toBe(
            '/temp/admin/support_ticket/45/show',
        );
        expect(view.text()).toContain(t('store.support.view'));
        expect(wrapper.get('label[for="search"]').text()).toBe(
            t('store.support.searchCode'),
        );
        expect(wrapper.get('form button').text()).toBe(
            t('store.support.search'),
        );
    });
    it('submits code searches and preserves the search when changing the page size', async () => {
        const wrapper = mountPage([], '123');
        expect(
            (wrapper.get('input[name="search"]').element as HTMLInputElement)
                .value,
        ).toBe('123');
        await wrapper.get('input[name="search"]').setValue('456');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/support_ticket',
            { search: '456', per_page: 20 },
            { preserveState: true, replace: true },
        );
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/support_ticket',
            { search: '123', per_page: 10 },
            { preserveState: true, replace: true },
        );
        expect(wrapper.text()).toContain(t('store.support.emptyTickets'));
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: [],
            perPage: 20,
        });
    });
    it('escapes customer subjects, handles missing accounts and renders each known status', () => {
        const wrapper = mountPage([
            storeSupportTicket({
                subject: '<img src=x onerror=alert(1)>',
                user: null,
                status: 'open',
                viewed: true,
            }),
            storeSupportTicket({ id: 46, status: 'solved', viewed: true }),
        ]);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('td img').exists()).toBe(false);
        expect(wrapper.text()).toContain(t('store.support.missingUser'));
        expect(wrapper.text()).toContain(t('store.support.open'));
        expect(wrapper.text()).toContain(t('store.support.solved'));
        expect(wrapper.text()).not.toContain(`· ${t('store.support.new')}`);
    });
});

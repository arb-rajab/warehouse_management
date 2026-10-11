import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import Pagination from '@/components/Pagination.vue';
import StoreSanitizedHtml from '@/components/StoreSanitizedHtml.vue';
import StoreTicketReplyForm from '@/components/StoreTicketReplyForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import {
    storeTicketDetail,
    storeTicketReply,
    paginated,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
const { usePageMock, routerGetMock, slotMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    slotMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
        useHttp: () =>
            reactive({
                processing: false,
                get: () => Promise.resolve(),
                post: () => Promise.resolve(),
            }),
    };
});
beforeEach(() => {
    vi.clearAllMocks();
    slotMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: [
                    'footer_setup',
                    'website_appearance',
                    'manage_website_scripts',
                    'edit_website_page',
                    'reply_to_support_tickets',
                ],
            }),
        }),
    });
    i18n.global.locale.value = 'en';
});
import Show from './Show.vue';
describe('ticket detail', () => {
    it('full-mounts sanitized server HTML attachment downloads and scoped paginated replies', async () => {
        const ticket = storeTicketDetail();
        const wrapper = mount(Show, {
            props: {
                ticket,
                replies: paginated([storeTicketReply()]),
                filters: { per_page: 20 },
            },
        });
        await nextTick();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(
            wrapper
                .findAllComponents(StoreSanitizedHtml)
                .map((html) => html.props('html')),
        ).toEqual([ticket.details_html, '<b>Safe reply</b>']);
        expect(
            wrapper.getComponent(StoreTicketReplyForm).props('ticket'),
        ).toEqual(ticket);
        expect(
            wrapper
                .findAll('a[download]')
                .map((link) => link.attributes('href')),
        ).toEqual(['/uploads/all/ticket.png', '/uploads/all/reply.png']);
        expect(wrapper.text()).toContain('ticket.png');
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/support_ticket/1/show',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
        wrapper.unmount();
    });
    it('escapes customer names, subjects and attachment labels', async () => {
        const wrapper = mount(Show, {
            props: {
                ticket: storeTicketDetail({
                    subject: '<script>subject</script>',
                    author: '<img onerror=attack>',
                    attachments: [
                        {
                            id: 1,
                            name: '<script>file</script>',
                            url: '/safe.png',
                        },
                    ],
                }),
                replies: paginated([]),
                filters: { per_page: 20 },
            },
        });
        await nextTick();
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.text()).toContain('<script>subject</script>');
        expect(wrapper.get('a[download]').text()).toContain(
            '<script>file</script>',
        );
        wrapper.unmount();
    });
});

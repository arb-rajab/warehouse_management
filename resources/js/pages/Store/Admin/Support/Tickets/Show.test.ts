import { Head } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as ticketsIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSanitizedHtml from '@/components/StoreSanitizedHtml.vue';
import StoreTicketReplyForm from '@/components/StoreTicketReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeTicketDetail,
    storeTicketReply,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import type { StoreTicketReply } from '@/types/store';
import Show from './Show.vue';

const { usePageMock, useHttpMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        usePage: usePageMock,
        useHttp: useHttpMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});

function grant(permissions: string[]): void {
    usePageMock.mockReturnValue({
        url: '/temp/admin/support_ticket/45/show',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions }),
        }),
    });
}

function mountPage(
    replies = paginated<StoreTicketReply>([
        storeTicketReply({
            attachments: [storeUpload({ id: 9, name: 'label', url: null })],
        }),
        storeTicketReply({
            id: 90,
            user: null,
            reply_html: '<p>First answer</p>',
            created_at: '2026-10-09T11:00:00+00:00',
        }),
    ]),
    ticket = storeTicketDetail(),
) {
    return mount(Show, {
        props: { ticket, replies, filters: { per_page: 20 } },
        attachTo: document.body,
    });
}

describe('Store support ticket Show', () => {
    beforeEach(() => {
        routerGetMock.mockReset();
        grant([
            'view_all_support_tickets',
            'reply_to_support_tickets',
            'view_uploads',
        ]);
        useHttpMock.mockImplementation((data) => createUseHttpStub(data, []));
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
        document.body.innerHTML = '';
    });

    it.each(['en', 'ar'] as const)(
        'full mounts the ticket, its replies and the reply form in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            await flushPromises();
            const title = t('store.support.ticketTitle', {
                subject: 'Help',
                code: '123456',
            });

            expect(title).toBe('Help #123456');
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(title);
            expect(wrapper.getComponent(PageHeader).props('title')).toBe(title);
            const back = wrapper.getComponent(TableActionLink);
            expect(back.props('href')).toEqual(ticketsIndex());
            expect(back.text()).toBe(t('store.support.back'));
            expect(wrapper.text()).toContain('Customer');
            expect(wrapper.text()).toContain(
                formatDateTime('2026-10-09T10:00:00+00:00'),
            );
            expect(wrapper.text()).toContain(
                `${t('store.support.status')}: ${t('store.support.open')}`,
            );
            expect(
                wrapper.getComponent(StoreTicketReplyForm).props('ticket'),
            ).toEqual(storeTicketDetail());
            expect(wrapper.get('#reply-label').text()).toBe(
                t('store.support.reply') + '*' + t('common.required'),
            );
            expect(wrapper.text()).toContain(
                t('store.support.originalMessage'),
            );
            expect(wrapper.text()).toContain(t('store.support.missingUser'));
            expect(wrapper.text()).not.toContain(t('store.support.noReplies'));
            expect(
                wrapper
                    .findAll('[aria-label]')
                    .map((list) => list.attributes('aria-label')),
            ).toContain(t('store.support.attachments'));
            const pagination = wrapper.getComponent(Pagination);
            expect(pagination.props()).toMatchObject({ perPage: 20 });
            wrapper.unmount();
        },
    );

    it('lists the replies newest first and ends the last page with the original request', async () => {
        const wrapper = mountPage();
        await flushPromises();
        const html = wrapper
            .findAllComponents(StoreSanitizedHtml)
            .map((part) => part.props('html'));
        expect(html).toEqual([
            '<p>It ships <em>today</em></p>',
            '<p>First answer</p>',
            '<p>My <strong>order</strong> is late</p>',
        ]);
        const items = wrapper.findAll('ul.space-y-4 > li');
        expect(items[0].text()).toContain('Staff member');
        expect(items[0].text()).toContain(
            formatDateTime('2026-10-09T12:00:00+00:00'),
        );
        expect(items[2].text()).toContain(t('store.support.originalMessage'));
        expect(items[2].get('strong').text()).toBe('order');
        wrapper.unmount();
    });

    it('links reachable attachments for download and names the rest', async () => {
        const wrapper = mountPage();
        await flushPromises();
        const receipt = wrapper.get('a[download]');
        expect(receipt.attributes('href')).toBe(
            'https://store.example/photo.webp',
        );
        expect(receipt.text()).toBe('receipt');
        expect(wrapper.findAll('a[download]')).toHaveLength(1);
        expect(wrapper.text()).toContain('label');
        wrapper.unmount();
    });

    it('keeps the original request for the last page only', async () => {
        const replies = paginated([storeTicketReply()]);
        replies.meta.current_page = 1;
        replies.meta.last_page = 2;
        const wrapper = mountPage(replies);
        await flushPromises();
        expect(wrapper.text()).not.toContain(
            t('store.support.originalMessage'),
        );
        expect(wrapper.findAllComponents(StoreSanitizedHtml)).toHaveLength(1);
        wrapper.unmount();
    });

    it('says when there are no replies yet', async () => {
        const wrapper = mountPage(paginated<StoreTicketReply>([]));
        await flushPromises();
        expect(wrapper.text()).toContain(t('store.support.noReplies'));
        expect(wrapper.text()).toContain(t('store.support.originalMessage'));
        wrapper.unmount();
    });

    it('hides the reply form without the reply permission', async () => {
        grant(['view_all_support_tickets']);
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.findComponent(StoreTicketReplyForm).exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain('First answer');
        wrapper.unmount();
    });

    it('changes the page size on the same ticket', async () => {
        const wrapper = mountPage();
        await flushPromises();
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(routerGetMock).toHaveBeenCalledWith(
            show.url(storeTicketDetail()),
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
        wrapper.unmount();
    });
});

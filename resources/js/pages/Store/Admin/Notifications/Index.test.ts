import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { index as notificationsIndex } from '@/actions/App/Http/Controllers/Store/Admin/NotificationController';
import Pagination from '@/components/Pagination.vue';
import { formatDateTime } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import {
    adminNotification,
    paginated,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { AdminNotification } from '@/types/storeNotifications';
import Index from './Index.vue';
const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
function mountPage(rows: AdminNotification[] = []) {
    return mount(Index, {
        props: { notifications: paginated(rows), filters: { per_page: 20 } },
    });
}
beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        url: notificationsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('personal admin notification index', () => {
    it('renders all static labels and empty state', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain(
            t('store.notifications.personal.title'),
        );
        expect(wrapper.text()).toContain(t('store.notifications.index.empty'));
        expect(wrapper.getComponent(Pagination).props()).toEqual({
            async: false,
            links: [],
            perPage: 20,
        });
    });
    it('renders order notifications as plain links to the Blade order page with locale-aware dates', () => {
        const notification = adminNotification();
        const wrapper = mountPage([notification]);
        expect(wrapper.get(`a[href="${notification.url}"]`).text()).toBe(
            'ORDER-7',
        );
        expect(wrapper.text()).toContain(
            t('store.notifications.personal.orderCode'),
        );
        expect(wrapper.text()).toContain(
            t('store.notifications.personal.status', {
                status: t('store.notifications.statuses.payment_received'),
            }),
        );
        expect(wrapper.get('time').text()).toBe(
            formatDateTime(notification.created_at!),
        );
    });
    it('preserves cart links and escapes customer text, including unknown notification types', () => {
        const wrapper = mountPage([
            adminNotification({
                kind: 'cart',
                customer_name: '<b>Customer</b>',
                message: 'Cart waiting',
                url: '/temp/admin/customer/whatsapp/21',
            }),
            adminNotification({ id: 'unknown', kind: 'other', url: null }),
        ]);
        expect(
            wrapper.get('a[href="/temp/admin/customer/whatsapp/21"]').text(),
        ).toBe('<b>Customer</b> Cart waiting');
        expect(wrapper.find('b').exists()).toBe(false);
        expect(wrapper.text()).toContain(
            t('store.notifications.personal.other'),
        );
    });
    it('translates known statuses in Arabic and humanizes unknown statuses', () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountPage([
            adminNotification({ status: 'paid' }),
            adminNotification({
                id: 'unknown-status',
                status: 'custom_status',
            }),
        ]);
        expect(wrapper.text()).toContain(
            t('store.notifications.statuses.paid'),
        );
        expect(wrapper.text()).not.toContain('Paid');
        expect(wrapper.text()).toContain('custom status');
    });
    it('visits the personal list when its page size changes', async () => {
        const wrapper = mountPage();
        await wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            notificationsIndex().url,
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});

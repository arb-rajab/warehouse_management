import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    create,
    destroy,
    index as notificationsIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import {
    customerNotification,
    paginated,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { CustomerNotification } from '@/types/storeNotifications';
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
vi.mock('@/lib/confirm', () => ({
    confirmDelete: vi.fn(() => false),
    confirmLogout: vi.fn(() => false),
}));
function mountPage(
    rows: CustomerNotification[] = [customerNotification()],
    permissions = ['add_notification', 'delete_notification'],
) {
    usePageMock.mockReturnValue({
        url: notificationsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: { notifications: paginated(rows), filters: { per_page: 20 } },
    });
}
beforeEach(() => vi.clearAllMocks());

describe('customer notification index', () => {
    it('passes narrow records and all labels to the table and pagination', () => {
        const wrapper = mountPage();
        expect(
            wrapper.getComponent<ComponentPublicInstance>(DataTable).props(),
        ).toMatchObject({
            rows: [customerNotification()],
            columns: [
                t('store.notifications.fields.title'),
                t('store.notifications.index.status'),
                t('store.notifications.fields.customer'),
                t('store.notifications.index.actions'),
            ],
            emptyMessage: t('store.notifications.index.empty'),
        });
        expect(wrapper.getComponent(Pagination).props()).toEqual({
            links: [],
            perPage: 20,
        });

        for (const key of [
            'title',
            'add',
            'status',
            'actions',
            'sent',
            'delete',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.notifications.index.${key}`),
            );
        }

        expect(wrapper.text()).toContain('Hello');
        expect(wrapper.text()).toContain('Customer');
        expect(mountPage([]).text()).toContain(
            t('store.notifications.index.empty'),
        );
    });
    it('gates both write controls and wires deletion to the bound notification and confirmation', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );
        const action = wrapper.getComponent(TableActionLink);
        expect(action.props('href')).toEqual(
            destroy(customerNotification(), { mergeQuery: {} }),
        );
        expect(action.attributes('method')).toBe('delete');
        expect(action.getComponent(Link).props('as')).toBe('button');
        expect(
            (action.getComponent(Link).props('onBefore') as () => boolean)(),
        ).toBe(false);
        expect(confirmDelete).toHaveBeenCalledWith('Hello');
        const denied = mountPage([customerNotification()], []);
        expect(denied.findComponent(AddResourceLink).exists()).toBe(false);
        expect(denied.findComponent(TableActionLink).exists()).toBe(false);
    });
    it('requests page changes and escapes notification content', async () => {
        const wrapper = mountPage([
            customerNotification({ title: '<b>Hello</b>', customer: null }),
        ]);
        expect(wrapper.find('b').exists()).toBe(false);
        expect(wrapper.text()).toContain('<b>Hello</b>');
        await wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(routerGetMock).toHaveBeenCalledWith(
            notificationsIndex().url,
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

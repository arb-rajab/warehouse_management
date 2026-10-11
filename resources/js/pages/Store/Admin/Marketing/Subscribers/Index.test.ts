import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    destroy as subscribersDestroy,
    index as subscribersIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/SubscriberController';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import {
    paginated,
    storeSubscriber,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreSubscriber } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const subscriberPermissions = ['view_all_subscribers', 'delete_subscriber'];

function mountPage(
    subscribers: StoreSubscriber[],
    { permissions = subscriberPermissions, perPage = 20 } = {},
) {
    usePageMock.mockReturnValue({
        url: subscribersIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            subscribers: paginated(subscribers),
            filters: { per_page: perPage },
        },
    });
}

function actionLink(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}

describe('Store subscribers Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, routerPostMock });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders inside the store layout, titled in the Head and the header', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.subscribers.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('store.subscribers.index.title'),
        );
        expect(t('store.subscribers.index.title')).toBe('Subscribers');
    });

    it('renders every column header and the empty message', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.subscribers.index.columnEmail'),
            t('store.subscribers.index.columnDate'),
            t('store.subscribers.index.columnActions'),
        ]);
        expect(wrapper.text()).toContain(t('store.subscribers.index.empty'));
    });

    it('renders a subscriber email, date and delete action', () => {
        const role = storeSubscriber();
        const wrapper = mountPage([role]);

        expect(rowCells(wrapper)[0].text()).toBe('reader@example.com');
        expect(rowCells(wrapper)[1].text()).toBe(formatDate('2026-10-08'));
        expect(
            actionLink(wrapper, t('store.subscribers.index.delete'))?.props(),
        ).toMatchObject({
            href: subscribersDestroy(role, { mergeQuery: {} }),
            variant: 'danger',
        });
    });

    it('asks before deleting a subscriber', () => {
        const wrapper = mountPage([storeSubscriber()]);
        const onBefore = actionLink(
            wrapper,
            t('store.subscribers.index.delete'),
        )
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'reader@example.com' }),
        );
    });

    it('offers no delete without permission', () => {
        const wrapper = mountPage([storeSubscriber()], {
            permissions: ['view_all_subscribers'],
        });

        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
    });

    it('pages the list, and reloads it at a new page size', () => {
        const wrapper = mountPage([storeSubscriber()], { perPage: 25 });
        const pagination = wrapper.getComponent(Pagination);

        expect(pagination.props('perPage')).toBe(25);

        pagination.vm.$emit('update:perPage', 50);

        expect(routerGetMock).toHaveBeenCalledWith(
            subscribersIndex.url(),
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

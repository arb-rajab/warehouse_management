import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    edit,
    index as trucksIndex,
    qr,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import { show as showTrip } from '@/actions/App/Http/Controllers/Trip/TripController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeTruck,
    truckPhoto,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
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

function mountPage(
    permissions = [
        'view_all_trucks',
        'add_truck',
        'edit_truck',
        'view_all_trips',
    ],
    empty = false,
) {
    usePageMock.mockReturnValue({
        url: trucksIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            trucks: paginated(empty ? [] : [storeTruck({ open_trip_id: 14 })]),
            photos: [truckPhoto()],
            filters: { search: '', per_page: 20 },
        },
    });
}

describe('truck list', () => {
    beforeEach(() => routerGetMock.mockReset());

    it('uses the store layout, labels every column and wires authorized links', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.trucks.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.trucks.index.title'));

        for (const label of [
            'name',
            'model',
            'capacity',
            'description',
            'plate',
        ]) {
            expect(wrapper.findAll('th').map((th) => th.text())).toContain(
                t(`store.trucks.fields.${label}`),
            );
        }

        for (const label of ['id', 'actions']) {
            expect(wrapper.text()).toContain(t(`store.trucks.index.${label}`));
        }

        expect(wrapper.getComponent(AddResourceLink).props()).toMatchObject({
            href: create(),
            label: t('store.trucks.index.add'),
        });
        expect(wrapper.getComponent(TableActionLink).props('href')).toEqual(
            edit(storeTruck()),
        );
        expect(wrapper.getComponent(TableActionLink).text()).toBe(
            t('store.trucks.index.edit'),
        );
        expect(wrapper.get(`a[href="${qr.url(storeTruck())}"]`).text()).toBe(
            t('store.trucks.index.print'),
        );
        expect(wrapper.get(`a[href="${showTrip.url(14)}"]`).text()).toBe(
            'Cargo truck',
        );
        expect(wrapper.get('tbody img').attributes('src')).toBe(
            truckPhoto().url,
        );
        expect(wrapper.get('label[for="search"]').text()).toBe(
            t('store.trucks.index.search'),
        );
        expect(wrapper.getComponent(Pagination).props('perPage')).toBe(20);
    });

    it('hides create and edit buttons and trip navigation without permissions', async () => {
        const wrapper = mountPage(['view_all_trucks']);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findComponent(TableActionLink).exists()).toBe(false);
        expect(wrapper.find(`a[href="${showTrip.url(14)}"]`).exists()).toBe(
            false,
        );
        expect(wrapper.find(`a[href="${qr.url(storeTruck())}"]`).exists()).toBe(
            true,
        );
        await wrapper.setProps({
            trucks: paginated([storeTruck({ license_plate: null })]),
        });
        expect(wrapper.find(`a[href="${qr.url(storeTruck())}"]`).exists()).toBe(
            false,
        );
    });

    it('renders empty state and preserves search when changing page size', async () => {
        expect(mountPage([], true).text()).toContain(
            t('store.trucks.index.empty'),
        );
        const wrapper = mountPage();
        await wrapper.get('#search').setValue('Cargo');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            trucksIndex().url,
            { search: 'Cargo', per_page: 20 },
            { preserveState: true, replace: true },
        );
        wrapper.getComponent(Pagination).vm.$emit('update:per-page', 50);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            trucksIndex().url,
            { search: 'Cargo', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

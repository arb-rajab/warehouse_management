import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DefineComponent } from 'vue';
import {
    create,
    destroy,
    edit,
    index as driversIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { paginated, storeDriver, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreDriver } from '@/types/store';
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
function mountPage(
    drivers: StoreDriver[] = [],
    permissions = ['add_driver', 'edit_driver', 'delete_driver'],
) {
    usePageMock.mockReturnValue({
        url: driversIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            drivers: paginated(drivers),
            filters: { per_page: 20, search: 'Sam' },
        },
    });
}

describe('Store driver Index', () => {
    beforeEach(() =>
        resetMocks({ usePageMock, routerGetMock, routerPostMock }),
    );
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });
    it.each(['en', 'ar'] as const)(
        'renders the store layout title search all headers and empty label in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.drivers.index.title'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.drivers.index.title'),
            );
            expect(wrapper.get('label[for="search"]').text()).toBe(
                t('store.drivers.index.search'),
            );
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                ['name', 'id', 'email', 'phone', 'country', 'actions'].map(
                    (key) => t(`store.drivers.index.${key}`),
                ),
            );
            expect(wrapper.text()).toContain(t('store.drivers.index.empty'));
        },
    );
    it('links add and edit to the correct driver pages and passes data to the table', () => {
        const driver = storeDriver();
        const wrapper = mountPage([driver]);
        expect(wrapper.getComponent(AddResourceLink).props()).toEqual({
            href: create(),
            label: t('store.drivers.index.add'),
        });
        expect(
            wrapper
                .getComponent(
                    DataTable as unknown as DefineComponent<{
                        rows: StoreDriver[];
                    }>,
                )
                .props('rows'),
        ).toEqual([driver]);
        expect(
            wrapper
                .findAll('tbody td')
                .slice(0, 5)
                .map((td) => td.text()),
        ).toEqual([
            'Sam Driver',
            '31',
            'sam@example.com',
            '0612345678',
            'Netherlands',
        ]);
        const editLink = wrapper
            .findAllComponents(TableActionLink)
            .find((link) =>
                link.text().includes(t('store.drivers.index.edit')),
            );
        expect(editLink?.props('href')).toEqual(edit(driver));
    });
    it('wires native confirmation to DELETE while preserving filters', () => {
        const driver = storeDriver();
        const wrapper = mountPage([driver]);
        const action = wrapper
            .findAllComponents(TableActionLink)
            .find((link) =>
                link.text().includes(t('store.drivers.index.delete')),
            );
        expect(action?.props()).toMatchObject({
            href: destroy(driver, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(action?.attributes('method')).toBe('delete');
        const onBefore = action
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: driver.name }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore()).toBe(true);
    });
    it('hides all writes without their own permissions including a customer delete permission', () => {
        const wrapper = mountPage(
            [storeDriver()],
            ['view_all_drivers', 'delete_customer'],
        );
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
    });
    it('shows only independently authorized edit or delete actions', () => {
        expect(
            mountPage([storeDriver()], ['edit_driver'])
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.drivers.index.edit')]);
        expect(
            mountPage([storeDriver()], ['delete_driver'])
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.drivers.index.delete')]);
    });
    it('submits name or email search and preserves it on page size changes', async () => {
        const wrapper = mountPage([storeDriver()]);
        expect(wrapper.get('#search').attributes('maxlength')).toBe('191');
        await wrapper.get('#search').setValue('sam@example.com');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            driversIndex.url(),
            { search: 'sam@example.com', per_page: 20 },
            { preserveState: true, replace: true },
        );
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ links: [], perPage: 20 });
        pagination.vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            driversIndex.url(),
            { search: 'Sam', per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('escapes driver names rather than rendering supplied HTML', () => {
        const wrapper = mountPage([
            storeDriver({ name: '<script>alert(1)</script>' }),
        ]);
        expect(wrapper.find('tbody script').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain(
            '<script>alert(1)</script>',
        );
    });
});

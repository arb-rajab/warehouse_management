import { Head, router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as warehouseMapIndex,
    rows as warehouseMapRows,
} from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { WarehouseSummary } from '@/types/admin';
import Index from './Index.vue';

const {
    usePageMock,
    routerPostMock,
    routerGetMock,
    mountMapMock,
    disposeMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    routerGetMock: vi.fn(),
    mountMapMock: vi.fn(),
    disposeMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock, get: routerGetMock },
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) =>
                options?.onSuccess?.({
                    data: [{ id: 5, name: 'Annex' }],
                    meta: {
                        current_page: 1,
                        last_page: 1,
                        per_page: 20,
                        total: 1,
                        from: 1,
                        to: 1,
                        links: [],
                    },
                }),
        }),
    };
});

vi.mock('@/warehouse-map/main', () => ({
    mountWarehouseMap: mountMapMock,
}));

function mountPage(
    warehouseIds: number[] | null = null,
    warehouses: WarehouseSummary[] = [],
) {
    usePageMock.mockReturnValue({
        url: '/admin/warehouse-map',
        props: defaultAuthProps(),
    });

    return mount(Index, {
        attachTo: document.body,
        props: {
            filters: { warehouse_ids: warehouseIds },
            filterOptions: { warehouses },
        },
    });
}

describe('WarehouseMap Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock, routerGetMock });
        window.localStorage.clear();
        mountMapMock.mockReset();
        disposeMock.mockReset();
        mountMapMock.mockReturnValue({
            dispose: disposeMock,
            refresh: vi.fn(),
        });
    });

    it('sets the page title in the Head', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('nav.warehouseMap'),
        );
        wrapper.unmount();
    });

    it('hands the whole area beside the sidebar and under the header to the map', () => {
        const wrapper = mountPage();
        const main = wrapper.get('main');

        expect(main.classes()).toEqual(
            expect.arrayContaining(['relative', 'min-h-0', 'flex-1']),
        );
        expect(main.classes()).not.toContain('px-4');
        expect(main.find('.max-w-screen-2xl').exists()).toBe(false);
        expect(main.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="warehouse-map"]').exists()).toBe(
            true,
        );
        wrapper.unmount();
    });

    it('starts with the sidebar folded to its icon rail and remembers an unfolded one for the map only', async () => {
        let wrapper = mountPage();

        expect(wrapper.get('#admin-sidebar').attributes('data-collapsed')).toBe(
            'true',
        );

        await wrapper.get('[data-testid="sidebar-collapse"]').trigger('click');
        expect(wrapper.get('#admin-sidebar').attributes('data-collapsed')).toBe(
            'false',
        );
        expect(
            window.localStorage.getItem(
                'backoffice.sidebar.admin-sidebar.map.7',
            ),
        ).toBe('0');
        expect(
            window.localStorage.getItem(
                'backoffice.sidebar.admin-sidebar.page.7',
            ),
        ).toBeNull();
        wrapper.unmount();

        wrapper = mountPage();
        expect(wrapper.get('#admin-sidebar').attributes('data-collapsed')).toBe(
            'false',
        );
        wrapper.unmount();
    });

    it('mounts the map on its container with the endpoint, the help page and the panel language', () => {
        const wrapper = mountPage();

        expect(mountMapMock).toHaveBeenCalledTimes(1);

        const [element, options] = mountMapMock.mock.calls[0];

        expect(element).toBe(
            wrapper.get('[data-testid="warehouse-map"]').element,
        );
        expect(options.rowsUrl).toBe(warehouseMapRows.url());
        expect(options.helpUrl).toBe(helpIndex.url());
        expect(options.locale).toBe('en');
        expect(options.messages.appTitle).toBe('Warehouse Map');
        expect(options.fallbackMessages.appTitle).toBe('Warehouse Map');
        wrapper.unmount();
    });

    it('switches the panel language with a full reload, like the language switcher', () => {
        const wrapper = mountPage();
        const { switchLocale } = mountMapMock.mock.calls[0][1];

        switchLocale('ar');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/locale/ar',
            {},
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
        expect(router.post).toBe(routerPostMock);
        wrapper.unmount();
    });

    it('disposes the map when leaving the page', () => {
        const wrapper = mountPage();

        wrapper.unmount();

        expect(disposeMock).toHaveBeenCalledTimes(1);
    });
    it('fetches every warehouse unless the page is filtered', () => {
        const wrapper = mountPage();

        expect(mountMapMock.mock.calls[0][1].rowsUrl).toBe(
            warehouseMapRows.url(),
        );
        wrapper.unmount();
    });

    it('fetches only the selected warehouses, and labels the filter with them', () => {
        const wrapper = mountPage([5], [{ id: 5, name: 'Annex' }]);

        expect(mountMapMock.mock.calls[0][1].rowsUrl).toBe(
            warehouseMapRows.url({ query: { warehouse_ids: [5] } }),
        );
        expect(wrapper.get('#warehouse-map-warehouse').text()).toBe('Annex');
        wrapper.unmount();
    });

    it('revisits the page without keeping its state when the warehouse filter changes, so the map mounts on the new rows', async () => {
        const wrapper = mountPage();

        await wrapper.get('#warehouse-map-warehouse').trigger('click');
        await wrapper.get('input[type="checkbox"]').setValue(true);

        expect(routerGetMock).toHaveBeenCalledWith(
            warehouseMapIndex.url(),
            { warehouse_ids: ['5'] },
            { preserveState: false },
        );
        wrapper.unmount();
    });
});

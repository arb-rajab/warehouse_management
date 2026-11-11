import { Head, router } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as warehouseMapIndex,
    rows as warehouseMapRows,
} from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import PalletTransferDialog from '@/components/PalletTransferDialog.vue';
import SuccessBanner from '@/components/SuccessBanner.vue';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { PalletTransferTargets, WarehouseSummary } from '@/types/admin';
import type { PalletTransferRequest } from '@/warehouse-map/main';
import Index from './Index.vue';

const {
    usePageMock,
    routerPostMock,
    routerGetMock,
    routerReloadMock,
    formSubmitMock,
    mountMapMock,
    disposeMock,
    refreshMock,
    showWarehousesMock,
    snapshotMock,
    routerOnMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerReloadMock: vi.fn(),
    formSubmitMock: vi.fn(),
    mountMapMock: vi.fn(),
    disposeMock: vi.fn(),
    refreshMock: vi.fn(),
    showWarehousesMock: vi.fn(),
    snapshotMock: vi.fn(),
    routerOnMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useForm: createUseFormStub(formSubmitMock),
        router: {
            post: routerPostMock,
            get: routerGetMock,
            reload: routerReloadMock,
            on: routerOnMock,
        },
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

const transferTargets: PalletTransferTargets = {
    warehouses: [
        { id: 1, name: 'Main' },
        { id: 5, name: 'Annex' },
    ],
    rows: [
        { id: 1, letter: 'A', cells_count: 4, flats_count: 2, warehouse_id: 1 },
        { id: 2, letter: 'B', cells_count: 4, flats_count: 2, warehouse_id: 5 },
    ],
};

const transferRequest: PalletTransferRequest = {
    palletId: 43,
    rowLetter: 'A',
    cellNumber: 3,
    flatNumber: 2,
    warehouseId: 1,
    productName: 'Olive oil',
    productArName: 'زيت زيتون',
};

const everyWarehouse: WarehouseSummary[] = [
    { id: 1, name: 'Main' },
    { id: 5, name: 'Annex' },
];

function mountPage(
    warehouseIds: number[] | null = null,
    warehouses: WarehouseSummary[] = everyWarehouse,
    flash: Record<string, string> | undefined = undefined,
) {
    usePageMock.mockReturnValue({
        url: '/admin/warehouse-map',
        props: defaultAuthProps(),
        flash,
    });

    return mount(Index, {
        attachTo: document.body,
        props: {
            filters: { warehouse_ids: warehouseIds },
            warehouses,
        },
    });
}

describe('WarehouseMap Index', () => {
    beforeEach(() => {
        resetMocks({
            usePageMock,
            routerPostMock,
            routerGetMock,
            routerReloadMock,
            formSubmitMock,
            refreshMock,
        });
        window.localStorage.clear();
        mountMapMock.mockReset();
        disposeMock.mockReset();
        showWarehousesMock.mockReset();
        snapshotMock.mockReset();
        routerOnMock.mockReset();
        routerOnMock.mockImplementation(() => vi.fn());
        mountMapMock.mockReturnValue({
            dispose: disposeMock,
            refresh: refreshMock,
            showWarehouses: showWarehousesMock,
            snapshot: snapshotMock,
        });
        window.location.hash = '';
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

    it("fetches only the selected warehouses, and hands every warehouse and the URL's selection to the map's switcher", () => {
        const wrapper = mountPage([5]);
        const options = mountMapMock.mock.calls[0][1];

        expect(options.rowsUrl).toBe(
            warehouseMapRows.url({ query: { warehouse_ids: [5] } }),
        );
        expect(options.warehouses).toMatchObject({
            options: everyWarehouse,
            selectedIds: [5],
        });
        expect(options.carry).toBeNull();
        wrapper.unmount();
    });

    it('has no warehouse filter of its own: the switcher inside the map is the only one', () => {
        const wrapper = mountPage();

        expect(wrapper.find('#warehouse-map-warehouse').exists()).toBe(false);
        expect(wrapper.find('[aria-haspopup="listbox"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it("visits the page with the switcher's choice, keeping its state and the view's fragment", () => {
        const wrapper = mountPage();
        const { select } = mountMapMock.mock.calls[0][1].warehouses;

        window.location.hash = '#3d';
        select([1, 5]);

        expect(routerGetMock).toHaveBeenCalledWith(
            warehouseMapIndex.url({ query: { warehouse_ids: [1, 5] } }) + '#3d',
            {},
            { preserveState: true, preserveScroll: true },
        );

        // every warehouse drops the parameter; a slot link stays behind
        window.location.hash = '#slot=B5-2';
        select(null);

        expect(routerGetMock).toHaveBeenLastCalledWith(
            warehouseMapIndex.url(),
            {},
            { preserveState: true, preserveScroll: true },
        );
        wrapper.unmount();
    });

    it("loads the new warehouses' rows in place once the URL's selection changed, without mounting the map again", async () => {
        const wrapper = mountPage();

        await wrapper.setProps({ filters: { warehouse_ids: [5] } });

        expect(showWarehousesMock).toHaveBeenCalledTimes(1);
        expect(showWarehousesMock).toHaveBeenCalledWith(
            [5],
            warehouseMapRows.url({ query: { warehouse_ids: [5] } }),
        );

        // the transfer's redirect brings the same selection back: nothing to load
        await wrapper.setProps({ filters: { warehouse_ids: [5] } });
        expect(showWarehousesMock).toHaveBeenCalledTimes(1);

        await wrapper.setProps({ filters: { warehouse_ids: null } });
        expect(showWarehousesMock).toHaveBeenLastCalledWith(
            null,
            warehouseMapRows.url(),
        );
        expect(mountMapMock).toHaveBeenCalledTimes(1);
        expect(disposeMock).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('carries what the map held into its next mount (Back / Forward), and forgets it after a visit elsewhere', () => {
        const held = { warehouseIds: [5], query: 'rice' };

        snapshotMock.mockReturnValue(held);

        let wrapper = mountPage([5]);

        wrapper.unmount();
        expect(routerOnMock).toHaveBeenCalledWith(
            'navigate',
            expect.any(Function),
        );

        const backToTheMap = routerOnMock.mock.calls.at(-1)![1];

        wrapper = mountPage([1]);
        expect(mountMapMock.mock.calls.at(-1)![1].carry).toEqual(held);
        backToTheMap({
            detail: { page: { component: 'Admin/WarehouseMap/Index' } },
        });

        wrapper.unmount();

        const elsewhere = routerOnMock.mock.calls.at(-1)![1];

        elsewhere({ detail: { page: { component: 'Admin/Dashboard/Index' } } });
        wrapper = mountPage();
        expect(mountMapMock.mock.calls.at(-1)![1].carry).toBeNull();
        wrapper.unmount();
        routerOnMock.mock.calls.at(-1)![1]({
            detail: { page: { component: 'Admin/Dashboard/Index' } },
        });
    });

    it("opens the transfer dialog on the map's request and loads the destinations once", async () => {
        routerReloadMock.mockImplementation(
            (options: {
                onSuccess: (page: { props: Record<string, unknown> }) => void;
            }) => options.onSuccess({ props: { transferTargets } }),
        );
        const wrapper = mountPage([1]);
        const { transferPallet } = mountMapMock.mock.calls[0][1];

        transferPallet(transferRequest);
        await flushPromises();

        expect(routerReloadMock).toHaveBeenCalledTimes(1);
        expect(routerReloadMock.mock.calls[0][0]).toMatchObject({
            only: ['transferTargets'],
        });

        const dialog = wrapper.getComponent(PalletTransferDialog);

        expect(dialog.props('open')).toBe(true);
        expect(dialog.props('target')).toEqual({
            palletId: 43,
            location: formatSlot('A', 3, 2),
            productName: 'Olive oil',
            warehouseId: 1,
        });
        expect(dialog.props('targets')).toEqual(transferTargets);
        expect(dialog.props('returnWarehouseIds')).toEqual([1]);

        transferPallet({ ...transferRequest, palletId: 44 });
        await flushPromises();

        expect(routerReloadMock).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it('refreshes the map once a transfer succeeded', async () => {
        const wrapper = mountPage();

        wrapper.getComponent(PalletTransferDialog).vm.$emit('transferred');

        expect(refreshMock).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it('takes no room above the map without a success message', () => {
        const wrapper = mountPage();
        const bar = wrapper.get('[data-testid="warehouse-map-bar"]');

        expect(bar.classes()).toContain('empty:hidden');
        expect(bar.element.children).toHaveLength(0);
        wrapper.unmount();
    });

    it("shows a transfer's success message in the bar above the map", () => {
        const wrapper = mountPage(null, everyWarehouse, {
            success: 'Pallet has been transferred successfully',
        });

        expect(wrapper.findComponent(SuccessBanner).exists()).toBe(true);
        expect(wrapper.get('[role="status"]').text()).toContain(
            'Pallet has been transferred successfully',
        );
        wrapper.unmount();
    });
});

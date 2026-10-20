import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { show as rowShow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { matchingPallet, paginated } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    MatchingPallet,
    MatchingPalletFilters,
    ProductFilterOption,
} from '@/types/admin';
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
        router: { get: routerGetMock },
    };
});

function mountPage(
    pallets: MatchingPallet[],
    filters: MatchingPalletFilters = {},
    selectedProducts: ProductFilterOption[] = [],
) {
    usePageMock.mockReturnValue({
        url: palletsIndex.url(),
        props: defaultAuthProps(),
    });

    return mount(Index, {
        props: {
            pallets: paginated(pallets),
            filters,
            filterOptions: { products: selectedProducts },
        },
    });
}

describe('Pallets Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage([]);

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('pallets.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('pallets.title'));
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('pallets.columns.product'),
            t('pallets.columns.state'),
            t('pallets.columns.location'),
            t('pallets.columns.expiration'),
            t('pallets.columns.added'),
            t('pallets.columns.remainingBoxes'),
        ]);
    });

    it('shows the empty message when there are no matching pallets', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('pallets.empty'));
    });

    it("renders a pallet's product, state, location, expiration, added date and remaining boxes", () => {
        const wrapper = mountPage([
            matchingPallet({
                state: 'opened',
                expiration_date: '2026-09-01',
                added_at: '2026-08-01T10:00:00Z',
                remaining_boxes: 4,
                product: {
                    id: 10,
                    name: 'Widgets',
                    ar_name: 'ودجات',
                    image_url: 'https://cdn.example.com/widgets.png',
                    boxes_count: 10,
                    active: true,
                },
                location: { row_letter: 'A', cell_number: 3, flat_number: 2 },
            }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[0].text()).toContain('Widgets');
        expect(cells[0].find('img').attributes('src')).toBe(
            'https://cdn.example.com/widgets.png',
        );
        expect(cells[1].text()).toBe(t('cellLog.states.opened'));
        expect(cells[2].text()).toContain('A3·2');
        expect(cells[3].text()).not.toBe('—');
        expect(cells[4].text()).not.toBe('—');
        expect(cells[5].text()).toBe('4');
    });

    it('marks an inactive product and an out-of-service cell, and only those', () => {
        const pallet = matchingPallet();
        const wrapper = mountPage([
            pallet,
            matchingPallet({
                id: 2,
                product: { ...pallet.product, id: 11, active: false },
                cell_active: false,
            }),
        ]);
        const rows = wrapper.findAll('tbody tr');
        const [activeProduct, activeLocation] = [0, 2].map(
            (i) => rows[0].findAll('td')[i],
        );
        const [inactiveProduct, inactiveLocation] = [0, 2].map(
            (i) => rows[1].findAll('td')[i],
        );

        expect(activeProduct.text()).toContain(t('products.status.active'));
        expect(
            activeLocation.find('[data-testid="cell-inactive-badge"]').exists(),
        ).toBe(false);
        expect(inactiveProduct.text()).toContain(t('products.status.inactive'));
        expect(
            inactiveLocation.get('[data-testid="cell-inactive-badge"]').text(),
        ).toBe(t('cells.inactiveBadge'));
    });

    it('links a pallet location to its row page with the matching cell/flat query', () => {
        const wrapper = mountPage([
            matchingPallet({
                location: { row_letter: 'A', cell_number: 3, flat_number: 2 },
            }),
        ]);

        const locationLink = rowCells(wrapper)[2].find('a');
        expect(locationLink.attributes('href')).toBe(
            rowShow.url(
                { row: 'A' },
                { query: { cell_number: '3', flat_number: '2' } },
            ),
        );
    });

    it('shows an em dash for a pallet with no expiration date and no add date', () => {
        const wrapper = mountPage([
            matchingPallet({ expiration_date: null, added_at: null }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[3].text()).toBe('—');
        expect(cells[4].text()).toBe('—');
    });

    function mapLink(wrapper: ReturnType<typeof mountPage>) {
        return wrapper
            .findAllComponents(Link)
            .find((link) => link.text().includes(t('pallets.viewOnMap')));
    }

    it('links the "view on map" button to the warehouse map, filtered to the same products and state', () => {
        const wrapper = mountPage([], {
            state: 'full',
            product_id: [3, 8],
            expires_within_days: 5,
            per_page: 50,
        });

        expect(mapLink(wrapper)?.props('href')).toBe(
            `${warehouseMapIndex.url()}#products=3,8&state=full`,
        );
    });

    it('links the "view on map" button to the whole warehouse map without a product or state filter', () => {
        const wrapper = mountPage([], { expired: true });

        expect(mapLink(wrapper)?.props('href')).toBe(warehouseMapIndex.url());
    });

    it('shows a chip for every active filter', () => {
        const wrapper = mountPage(
            [],
            {
                state: 'opened',
                expired: true,
                expires_within_days: 5,
                stale_after_days: 30,
            },
            [{ id: 1, name: 'Widgets', ar_name: 'ودجات', active: true }],
        );

        const text = wrapper.text();
        expect(text).toContain(t('pallets.filteredBy'));
        expect(text).toContain(t('cellLog.states.opened'));
        expect(text).toContain(t('dashboard.expiring.expired'));
        expect(text).toContain(t('dashboard.expiring.soon', { days: 5 }));
        expect(text).toContain(t('dashboard.stale.soon', { days: 30 }));
        expect(text).toContain('Widgets');
    });

    it('shows no filter-chip strip when no filter is active', () => {
        const wrapper = mountPage([], {});

        expect(wrapper.text()).not.toContain(t('pallets.filteredBy'));
    });

    it('requests the new page size, preserving the active filter, when the selector changes', async () => {
        const wrapper = mountPage([], { state: 'full', per_page: 20 });

        await wrapper.get('select').setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            palletsIndex.url(),
            { state: 'full', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

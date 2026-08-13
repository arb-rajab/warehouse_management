import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDate } from '@/lib/date';
import { t } from '@/lib/i18n';
import type {
    CellFilterOptions,
    CellFilters,
    CellWithLocation,
    Paginated,
} from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const LinkStub = defineComponent({
        props: ['href', 'as'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                    },
                    slots.default?.(),
                );
        },
    });

    return {
        Head: defineComponent({ render: () => null }),
        Link: LinkStub,
        usePage: usePageMock,
        router: { get: routerGetMock },
    };
});

function cell(overrides: Partial<CellWithLocation> = {}): CellWithLocation {
    return {
        id: 1,
        row_letter: 'A',
        cell_number: 3,
        flat_number: 2,
        state: 'full',
        pallet: {
            id: 55,
            product_name: 'Widgets',
            product_image_url: null,
            expiration_date: '2026-09-01',
            added_at: '2026-08-01T10:00:00Z',
        },
        ...overrides,
    };
}

function paginatedCells(
    cells: CellWithLocation[],
): Paginated<CellWithLocation> {
    return {
        data: cells,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: cells.length,
            from: cells.length ? 1 : null,
            to: cells.length,
            links: [],
        },
    };
}

const filterOptions: CellFilterOptions = {
    rows: [
        { id: 1, letter: 'A' },
        { id: 2, letter: 'B' },
    ],
    maxColumnNumber: 3,
    states: ['empty', 'full', 'opened'],
};

function mountPage(cells: CellWithLocation[], filters: CellFilters = {}) {
    usePageMock.mockReturnValue({
        url: '/admin/cells',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Index, {
        props: { cells: paginatedCells(cells), filters, filterOptions },
    });
}

function rowCells(wrapper: ReturnType<typeof mountPage>, rowIndex = 0) {
    return wrapper.findAll('tbody tr')[rowIndex].findAll('td');
}

async function openFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('cellLog.filters.title')));
    await trigger?.trigger('click');
}

describe('Cells Index', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerGetMock.mockReset();
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('cells.columns.location'),
            t('cells.columns.state'),
            t('cells.columns.product'),
            t('cells.columns.expires'),
        ]);
    });

    it('shows the empty message when there are no cells', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('cells.empty'));
    });

    it('opens the filter dialog when the Filters button is clicked', async () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await openFilters(wrapper);

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            t('cellLog.filters.title'),
        );
        expect(wrapper.find('#filter-state').exists()).toBe(true);
    });

    it("populates each filter select's options from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            wrapper
                .get('#filter-state')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([
            t('cellLog.filters.all'),
            t('cellLog.states.empty'),
            t('cellLog.states.full'),
            t('cellLog.states.opened'),
        ]);
        expect(
            wrapper
                .get('#filter-row')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellLog.filters.all'), 'A', 'B']);
        expect(
            wrapper
                .get('#filter-column')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellLog.filters.all'), '1', '2', '3']);
    });

    it('links the location cell to the row show page', () => {
        const wrapper = mountPage([
            cell({ row_letter: 'A', cell_number: 3, flat_number: 2 }),
        ]);

        const link = rowCells(wrapper)[0].get('a');
        expect(link.attributes('href')).toBe('/admin/rows/A');
        expect(link.text()).toContain('A3·2');
    });

    it('shows the state label', () => {
        const wrapper = mountPage([cell({ state: 'opened' })]);

        expect(rowCells(wrapper)[1].text()).toBe(t('cellLog.states.opened'));
    });

    it('renders the product name and image when the cell has a pallet', () => {
        const wrapper = mountPage([
            cell({
                pallet: {
                    id: 55,
                    product_name: 'Widgets',
                    product_image_url: '/img/widgets.png',
                    expiration_date: '2026-09-01',
                    added_at: '2026-08-01T10:00:00Z',
                },
            }),
        ]);

        const productCell = rowCells(wrapper)[2];
        expect(productCell.text()).toContain('Widgets');
        const img = productCell.get('img');
        expect(img.attributes('src')).toBe('/img/widgets.png');
        expect(img.attributes('alt')).toBe('Widgets');
    });

    it('shows a dash for product and expiration when the cell has no pallet', () => {
        const wrapper = mountPage([cell({ pallet: null })]);

        expect(rowCells(wrapper)[2].text()).toBe('—');
        expect(rowCells(wrapper)[3].text()).toBe('—');
    });

    it('renders the formatted expiration date when the cell has a pallet', () => {
        const wrapper = mountPage([
            cell({
                pallet: {
                    id: 55,
                    product_name: 'Widgets',
                    product_image_url: null,
                    expiration_date: '2026-09-01',
                    added_at: '2026-08-01T10:00:00Z',
                },
            }),
        ]);

        expect(rowCells(wrapper)[3].text()).toBe(formatDate('2026-09-01'));
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-state').setValue('opened');
        await wrapper.get('#filter-row').setValue('1');
        await wrapper
            .get('#filter-expiration-date-from')
            .setValue('2026-09-01');
        await wrapper.get('#filter-expiration-date-to').setValue('2026-09-10');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            expect.objectContaining({
                state: 'opened',
                row_id: 1,
                expiration_date_from: '2026-09-01',
                expiration_date_to: '2026-09-10',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            state: 'opened',
            expiration_date_from: '2026-09-01',
        });
        await openFilters(wrapper);

        const clearButton = wrapper
            .findAll('button')
            .find((button) => button.text() === t('cellLog.filters.clear'));
        await clearButton?.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            {},
            { preserveState: true, replace: true },
        );
        expect(
            (wrapper.get('#filter-state').element as HTMLSelectElement).value,
        ).toBe('');
    });

    it('applies a sort immediately when the expiration column header is clicked', async () => {
        const wrapper = mountPage([]);

        const expiresHeader = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('cells.columns.expires')));
        await expiresHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            expect.objectContaining({
                sort_by: 'expiration_date',
                sort_direction: 'asc',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('flips the sort direction when the same sortable header is clicked again', async () => {
        const wrapper = mountPage([], {
            sort_by: 'expiration_date',
            sort_direction: 'asc',
        });

        const expiresHeader = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('cells.columns.expires')));
        await expiresHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            expect.objectContaining({
                sort_by: 'expiration_date',
                sort_direction: 'desc',
            }),
            { preserveState: true, replace: true },
        );
    });
});

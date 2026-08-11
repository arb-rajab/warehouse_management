import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type {
    CellStatusLog,
    CellStatusLogFilterOptions,
    CellStatusLogFilters,
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

function cellLog(overrides: Partial<CellStatusLog> = {}): CellStatusLog {
    return {
        id: 1,
        action: 'stored',
        from_state: 'empty',
        to_state: 'full',
        note: 'Handle with care',
        cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        related_cell: null,
        product: { id: 10, name: 'Widgets', image_url: null },
        pallet: { id: 55, expiration_date: '2026-09-01' },
        user: { id: 7, name: 'Jane Doe' },
        created_at: '2026-08-01T10:00:00Z',
        ...overrides,
    };
}

function paginatedLogs(logs: CellStatusLog[]): Paginated<CellStatusLog> {
    return {
        data: logs,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: logs.length,
            from: logs.length ? 1 : null,
            to: logs.length,
            links: [],
        },
    };
}

const filterOptions: CellStatusLogFilterOptions = {
    rows: [
        { id: 1, letter: 'A' },
        { id: 2, letter: 'B' },
    ],
    maxColumnNumber: 3,
    products: [{ id: 10, name: 'Widgets' }],
    users: [{ id: 7, name: 'Jane Doe' }],
    actions: [
        'stored',
        'opened',
        'emptied',
        'transferred_out',
        'transferred_in',
    ],
};

function mountPage(logs: CellStatusLog[], filters: CellStatusLogFilters = {}) {
    usePageMock.mockReturnValue({
        url: '/admin/cell-logs',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Index, {
        props: { logs: paginatedLogs(logs), filters, filterOptions },
    });
}

function rowCells(wrapper: ReturnType<typeof mountPage>, rowIndex = 0) {
    return wrapper.findAll('tbody tr')[rowIndex].findAll('td');
}

describe('CellStatusLogs Index', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerGetMock.mockReset();
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('cellLog.columns.cell'),
            t('cellLog.columns.action'),
            t('cellLog.columns.product'),
            t('cellLog.columns.pallet'),
            t('cellLog.columns.note'),
            t('cellLog.columns.doneBy'),
            t('cellLog.columns.when'),
        ]);
    });

    it('shows the empty message when there are no logs', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('cellLog.empty'));
    });

    it("populates each filter select's options from filterOptions", () => {
        const wrapper = mountPage([]);

        expect(
            wrapper
                .get('#filter-product')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellLog.filters.all'), 'Widgets']);
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
        expect(
            wrapper
                .get('#filter-user')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellLog.filters.all'), 'Jane Doe']);
        expect(
            wrapper
                .get('#filter-action')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([
            t('cellLog.filters.all'),
            t('cellLog.actions.stored'),
            t('cellLog.actions.opened'),
            t('cellLog.actions.emptied'),
            t('cellLog.actions.transferred_out'),
            t('cellLog.actions.transferred_in'),
        ]);
    });

    it('renders only the from-cell link when the log has no related cell', () => {
        const wrapper = mountPage([
            cellLog({ action: 'stored', related_cell: null }),
        ]);

        const links = rowCells(wrapper)[0].findAll('a');
        expect(links).toHaveLength(1);
        expect(links[0].attributes('href')).toBe('/admin/rows/A');
        expect(links[0].text()).toContain('A3·2');
    });

    it('shows the related cell as the origin and the log cell as the destination for a transferred-in log', () => {
        const wrapper = mountPage([
            cellLog({
                action: 'transferred_in',
                cell: { row_letter: 'B', cell_number: 1, flat_number: 1 },
                related_cell: {
                    row_letter: 'A',
                    cell_number: 3,
                    flat_number: 2,
                },
            }),
        ]);

        const links = rowCells(wrapper)[0].findAll('a');
        expect(links.map((a) => a.attributes('href'))).toEqual([
            '/admin/rows/A',
            '/admin/rows/B',
        ]);
        expect(links.map((a) => a.text())).toEqual(['A3·2', 'B1·1']);
    });

    it('shows the log cell as the origin and the related cell as the destination for a transferred-out log', () => {
        const wrapper = mountPage([
            cellLog({
                action: 'transferred_out',
                cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
                related_cell: {
                    row_letter: 'B',
                    cell_number: 1,
                    flat_number: 1,
                },
            }),
        ]);

        const links = rowCells(wrapper)[0].findAll('a');
        expect(links.map((a) => a.attributes('href'))).toEqual([
            '/admin/rows/A',
            '/admin/rows/B',
        ]);
        expect(links.map((a) => a.text())).toEqual(['A3·2', 'B1·1']);
    });

    it('shows the action label and the from/to state transition', () => {
        const wrapper = mountPage([
            cellLog({
                action: 'opened',
                from_state: 'full',
                to_state: 'opened',
            }),
        ]);

        const actionCell = rowCells(wrapper)[1];
        expect(actionCell.text()).toContain(t('cellLog.actions.opened'));
        expect(actionCell.text()).toContain(t('cellLog.states.full'));
        expect(actionCell.text()).toContain(t('cellLog.states.opened'));
    });

    it('renders the product name and image when the log has a product', () => {
        const wrapper = mountPage([
            cellLog({
                product: {
                    id: 10,
                    name: 'Widgets',
                    image_url: '/img/widgets.png',
                },
            }),
        ]);

        const productCell = rowCells(wrapper)[2];
        expect(productCell.text()).toContain('Widgets');
        const img = productCell.get('img');
        expect(img.attributes('src')).toBe('/img/widgets.png');
        expect(img.attributes('alt')).toBe('Widgets');
    });

    it('shows a dash when the log has no product', () => {
        const wrapper = mountPage([cellLog({ product: null })]);

        const productCell = rowCells(wrapper)[2];
        expect(productCell.find('img').exists()).toBe(false);
        expect(productCell.text()).toBe('—');
    });

    it('renders the pallet id and expiration date when the log has a pallet', () => {
        const wrapper = mountPage([
            cellLog({ pallet: { id: 55, expiration_date: '2026-09-01' } }),
        ]);

        const palletCell = rowCells(wrapper)[3];
        expect(palletCell.text()).toContain('#55');
        expect(palletCell.text()).toContain(formatDate('2026-09-01'));
    });

    it('does not render an expiration date when the pallet has none', () => {
        const wrapper = mountPage([
            cellLog({ pallet: { id: 55, expiration_date: null } }),
        ]);

        const palletCell = rowCells(wrapper)[3];
        expect(palletCell.text()).toContain('#55');
        expect(palletCell.text()).not.toContain(t('cellLog.columns.expires'));
    });

    it('shows a dash when the log has no pallet', () => {
        const wrapper = mountPage([cellLog({ pallet: null })]);

        expect(rowCells(wrapper)[3].text()).toBe('—');
    });

    it('requests the filtered history for that pallet when its history button is clicked', async () => {
        const wrapper = mountPage([
            cellLog({ pallet: { id: 55, expiration_date: null } }),
        ]);

        await rowCells(wrapper)[3].get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            { pallet_id: 55 },
            { preserveState: true, replace: true },
        );
    });

    it('renders the note text', () => {
        const wrapper = mountPage([cellLog({ note: 'Handle with care' })]);

        expect(rowCells(wrapper)[4].text()).toBe('Handle with care');
    });

    it('shows a dash when the log has no note', () => {
        const wrapper = mountPage([cellLog({ note: null })]);

        expect(rowCells(wrapper)[4].text()).toBe('—');
    });

    it("links the doneBy cell to the user's edit page", () => {
        const wrapper = mountPage([
            cellLog({ user: { id: 7, name: 'Jane Doe' } }),
        ]);

        const link = rowCells(wrapper)[5].get('a');
        expect(link.attributes('href')).toBe('/admin/users/7/edit');
        expect(link.text()).toBe('Jane Doe');
    });

    it('renders the created_at timestamp formatted', () => {
        const wrapper = mountPage([
            cellLog({ created_at: '2026-08-01T10:00:00Z' }),
        ]);

        expect(rowCells(wrapper)[6].text()).toBe(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('#filter-action').setValue('opened');
        await wrapper.get('#filter-date-from').setValue('2026-08-01');
        await wrapper.get('#filter-date-to').setValue('2026-08-10');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({
                action: 'opened',
                date_from: '2026-08-01',
                date_to: '2026-08-10',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            action: 'opened',
            date_from: '2026-08-01',
        });

        const clearButton = wrapper
            .findAll('button')
            .find((button) => button.text() === t('cellLog.filters.clear'));
        await clearButton?.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            {},
            { preserveState: true, replace: true },
        );
        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .value,
        ).toBe('');
        expect(
            (wrapper.get('#filter-action').element as HTMLSelectElement).value,
        ).toBe('');
    });

    it('shows the pallet-history banner with the id when filtering by pallet', () => {
        const wrapper = mountPage([], { pallet_id: 55 });

        expect(wrapper.text()).toContain(
            t('cellLog.filters.palletHistory', { id: '55' }),
        );
    });

    it('does not show the pallet-history banner when there is no pallet filter', () => {
        const wrapper = mountPage([], {});

        expect(wrapper.find('.bg-blue-50').exists()).toBe(false);
    });

    it('clears the pallet filter when the banner Clear button is clicked', async () => {
        const wrapper = mountPage([], { pallet_id: 55 });

        const banner = wrapper.get('.bg-blue-50');
        await banner.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            {},
            { preserveState: true, replace: true },
        );
    });
});

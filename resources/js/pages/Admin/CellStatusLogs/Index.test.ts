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
        next_log_at: null,
        duration_seconds: 3600,
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

async function openFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('cellLog.filters.title')));
    await trigger?.trigger('click');
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
            t('cellLog.columns.duration'),
        ]);
    });

    it('shows the empty message when there are no logs', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('cellLog.empty'));
    });

    it('hides the filter fields until the Filters button is clicked', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('#filter-product').exists()).toBe(false);
    });

    it('opens the filter dialog when the Filters button is clicked', async () => {
        const wrapper = mountPage([]);

        await openFilters(wrapper);

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            t('cellLog.filters.title'),
        );
        expect(wrapper.find('#filter-product').exists()).toBe(true);
    });

    it('groups the filter fields under section headings', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.text()).toContain(t('cellLog.filters.sections.location'));
        expect(dialog.text()).toContain(t('cellLog.filters.sections.activity'));
        expect(dialog.text()).toContain(t('cellLog.filters.sections.date'));
        expect(dialog.text()).toContain(
            t('cellLog.filters.sections.expiration'),
        );
    });

    it('closes the filter dialog after Apply is clicked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('does not show a filter count badge when no filters are active', () => {
        const wrapper = mountPage([]);

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.filters.title')),
            );
        expect(trigger?.find('span').exists()).toBe(false);
    });

    it('shows a filter count badge for each distinct active filter', () => {
        const wrapper = mountPage([], {
            product_id: 10,
            action: ['opened'],
            date_from: '2026-08-01',
        });

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.filters.title')),
            );
        expect(trigger?.get('span').text()).toBe('3');
    });

    it("populates each filter select's options from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

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
    });

    it("populates the status filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-action').trigger('click');

        const labels = wrapper
            .get('[role="listbox"]')
            .findAll('label')
            .map((label) => label.text());
        expect(labels).toEqual([
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

    it('merges a transferred_out/transferred_in pair into a single row', () => {
        const transferredOut = cellLog({
            id: 1,
            action: 'transferred_out',
            from_state: 'full',
            to_state: 'empty',
            cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
            related_cell: { row_letter: 'B', cell_number: 1, flat_number: 1 },
            created_at: '2026-08-01T10:00:00Z',
        });
        const transferredIn = cellLog({
            id: 2,
            action: 'transferred_in',
            from_state: 'empty',
            to_state: 'full',
            cell: { row_letter: 'B', cell_number: 1, flat_number: 1 },
            related_cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
            created_at: '2026-08-01T10:00:00Z',
        });
        const wrapper = mountPage([transferredOut, transferredIn]);

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(1);

        const cells = rowCells(wrapper)[0];
        const links = cells.findAll('a');
        expect(links.map((a) => a.text())).toEqual(['A3·2', 'B1·1']);

        const actionCell = rowCells(wrapper)[1];
        expect(actionCell.text()).toContain(t('cellLog.actions.transferred'));
        expect(actionCell.text()).not.toContain(
            t('cellLog.actions.transferred_out'),
        );
        expect(actionCell.text()).toContain(t('cellLog.states.full'));
        expect(actionCell.text()).not.toContain(t('cellLog.states.empty'));
    });

    it('does not merge transferred logs for different pallets or times', () => {
        const wrapper = mountPage([
            cellLog({
                id: 1,
                action: 'transferred_out',
                pallet: { id: 55, expiration_date: null },
                cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
                related_cell: {
                    row_letter: 'B',
                    cell_number: 1,
                    flat_number: 1,
                },
                created_at: '2026-08-01T10:00:00Z',
            }),
            cellLog({
                id: 2,
                action: 'transferred_in',
                pallet: { id: 56, expiration_date: null },
                cell: { row_letter: 'B', cell_number: 1, flat_number: 1 },
                related_cell: {
                    row_letter: 'A',
                    cell_number: 3,
                    flat_number: 2,
                },
                created_at: '2026-08-01T10:00:00Z',
            }),
        ]);

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('does not merge a lone transferred_out log filtered to its own cell', () => {
        const wrapper = mountPage([
            cellLog({
                id: 1,
                action: 'transferred_out',
                from_state: 'full',
                to_state: 'empty',
                cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
                related_cell: {
                    row_letter: 'B',
                    cell_number: 1,
                    flat_number: 1,
                },
            }),
        ]);

        const actionCell = rowCells(wrapper)[1];
        expect(actionCell.text()).toContain(
            t('cellLog.actions.transferred_out'),
        );
        expect(actionCell.text()).toContain(t('cellLog.states.full'));
        expect(actionCell.text()).toContain(t('cellLog.states.empty'));
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

    it('shows the formatted duration when a next log exists', () => {
        const wrapper = mountPage([
            cellLog({
                next_log_at: '2026-08-01T11:01:00Z',
                duration_seconds: 3660,
            }),
        ]);

        const durationCell = rowCells(wrapper)[7];
        expect(durationCell.text()).toContain('1h 1m');
        expect(durationCell.text()).not.toContain(t('cellLog.columns.ongoing'));
    });

    it('shows the ongoing label alongside the duration when there is no next log', () => {
        const wrapper = mountPage([
            cellLog({ next_log_at: null, duration_seconds: 90 }),
        ]);

        const durationCell = rowCells(wrapper)[7];
        expect(durationCell.text()).toContain(t('cellLog.columns.ongoing'));
        expect(durationCell.text()).toContain('1m');
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-action').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);
        await wrapper.get('#filter-date-from').setValue('2026-08-01');
        await wrapper.get('#filter-date-to').setValue('2026-08-10');
        await wrapper
            .get('#filter-expiration-date-from')
            .setValue('2026-09-01');
        await wrapper.get('#filter-expiration-date-to').setValue('2026-09-10');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({
                action: ['opened'],
                date_from: '2026-08-01',
                date_to: '2026-08-10',
                expiration_date_from: '2026-09-01',
                expiration_date_to: '2026-09-10',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            action: ['opened'],
            date_from: '2026-08-01',
            expiration_date_from: '2026-09-01',
        });
        await openFilters(wrapper);

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
            (
                wrapper.get('#filter-expiration-date-from')
                    .element as HTMLInputElement
            ).value,
        ).toBe('');
        expect(wrapper.get('#filter-action').text()).toBe(
            t('cellLog.filters.all'),
        );
        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).value,
        ).toBe('');
    });

    it('requests created_within_days when it is filled in instead of a date range', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ created_within_days: 7 }),
            { preserveState: true, replace: true },
        );
    });

    it('disables the created_within_days field once a date range value is entered', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(false);

        await wrapper.get('#filter-date-from').setValue('2026-08-01');

        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
    });

    it('disables the date range fields once created_within_days is filled in', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(false);
        expect(
            (wrapper.get('#filter-date-to').element as HTMLInputElement)
                .disabled,
        ).toBe(false);

        await wrapper.get('#filter-created-within-days').setValue('7');

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(true);
        expect(
            (wrapper.get('#filter-date-to').element as HTMLInputElement)
                .disabled,
        ).toBe(true);
    });

    it('re-enables the date range fields once created_within_days is cleared', async () => {
        const wrapper = mountPage([], { created_within_days: 7 });
        await openFilters(wrapper);

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(true);

        await wrapper.get('#filter-created-within-days').setValue('');

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(false);
    });

    it('applies a sort immediately when a sortable column header is clicked, without waiting for Apply', async () => {
        const wrapper = mountPage([]);

        const whenHeader = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('cellLog.columns.when')));
        await whenHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({
                sort_by: 'created_at',
                sort_direction: 'asc',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('flips the sort direction when the same sortable header is clicked again', async () => {
        const wrapper = mountPage([], {
            sort_by: 'created_at',
            sort_direction: 'asc',
        });

        const whenHeader = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('cellLog.columns.when')));
        await whenHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({
                sort_by: 'created_at',
                sort_direction: 'desc',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('switches to ascending when a different sortable header is clicked', async () => {
        const wrapper = mountPage([], {
            sort_by: 'created_at',
            sort_direction: 'desc',
        });

        const palletHeader = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('cellLog.columns.pallet')));
        await palletHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({
                sort_by: 'expiration_date',
                sort_direction: 'asc',
            }),
            { preserveState: true, replace: true },
        );
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

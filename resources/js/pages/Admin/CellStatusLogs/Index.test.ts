import { Check, Clock, TriangleAlert, Filter, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { cellLog, paginated } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    CellStatusLog,
    CellStatusLogFilterOptions,
    CellStatusLogFilters,
} from '@/types/admin';
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
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) => options?.onSuccess?.(paginated(filterOptions.products, 20)),
        }),
    };
});

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
        props: defaultAuthProps(),
    });

    return mount(Index, {
        props: { logs: paginated(logs), filters, filterOptions },
    });
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
        resetMocks({ usePageMock, routerGetMock, routerPostMock });
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

        const applyButton = wrapper
            .get('form')
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.filters.apply')),
            );
        expect(applyButton?.findComponent(Check).exists()).toBe(true);

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
            product_id: [10],
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

    function columnHeader(
        wrapper: ReturnType<typeof mountPage>,
        label: string,
    ) {
        const header = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(label));

        if (!header) {
            throw new Error(`Column header "${label}" not found`);
        }

        return header;
    }

    function isColumnActive(
        wrapper: ReturnType<typeof mountPage>,
        label: string,
    ): boolean {
        return columnHeader(wrapper, label)
            .get('button[title]')
            .classes()
            .includes('text-blue-600');
    }

    it('shows a filter icon on every filterable column, none active, when no filters are active', () => {
        const wrapper = mountPage([]);

        for (const label of [
            t('cellLog.columns.cell'),
            t('cellLog.columns.action'),
            t('cellLog.columns.product'),
            t('cellLog.columns.pallet'),
            t('cellLog.columns.doneBy'),
            t('cellLog.columns.when'),
        ]) {
            expect(
                columnHeader(wrapper, label).findComponent(Filter).exists(),
            ).toBe(true);
            expect(isColumnActive(wrapper, label)).toBe(false);
        }

        expect(
            columnHeader(wrapper, t('cellLog.columns.note'))
                .findComponent(Filter)
                .exists(),
        ).toBe(false);
    });

    it('marks the product column active when a product filter is applied', () => {
        const wrapper = mountPage([], { product_id: [10] });

        expect(isColumnActive(wrapper, t('cellLog.columns.product'))).toBe(
            true,
        );
        expect(isColumnActive(wrapper, t('cellLog.columns.doneBy'))).toBe(
            false,
        );
    });

    it('marks the cell column active when a row filter is applied', () => {
        const wrapper = mountPage([], { row_id: 1 });

        expect(isColumnActive(wrapper, t('cellLog.columns.cell'))).toBe(true);
    });

    it('marks the pallet column active when an expiration filter is applied', () => {
        const wrapper = mountPage([], { expires_within_days: 7 });

        expect(isColumnActive(wrapper, t('cellLog.columns.pallet'))).toBe(true);
        expect(isColumnActive(wrapper, t('cellLog.columns.when'))).toBe(false);
    });

    it('marks the when column active when a date filter is applied', () => {
        const wrapper = mountPage([], { created_within_days: 7 });

        expect(isColumnActive(wrapper, t('cellLog.columns.when'))).toBe(true);
    });

    async function openColumnPopover(
        wrapper: ReturnType<typeof mountPage>,
        label: string,
    ): Promise<void> {
        await columnHeader(wrapper, label)
            .get('button[title]')
            .trigger('click');
    }

    it('opens the location popover with row/column fields when the Cell column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('cellLog.columns.cell'));

        expect(wrapper.find('#popover-filter-row').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-column').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-product').exists()).toBe(false);
    });

    it('opens the doneBy popover with the user select when the Done By column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('cellLog.columns.doneBy'));

        expect(wrapper.find('#popover-filter-user').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-row').exists()).toBe(false);
    });

    it('opens the expiration popover with the pallet expiration fields when the Pallet column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('cellLog.columns.pallet'));

        expect(
            wrapper.find('#popover-filter-expiration-date-from').exists(),
        ).toBe(true);
        expect(
            wrapper.find('#popover-filter-expires-within-days').exists(),
        ).toBe(true);
        expect(wrapper.find('#popover-filter-date-from').exists()).toBe(false);
    });

    it('auto-applies, debounced, when a field is changed inside an open column popover', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('cellLog.columns.doneBy'));
        await wrapper.get('#popover-filter-user').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(routerGetMock).not.toHaveBeenCalled();

        vi.advanceTimersByTime(400);

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ user_id: ['7'] }),
            { preserveState: true, replace: true },
        );
        vi.useRealTimers();
    });

    it('does not auto-apply a change made in the main Filters dialog', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');
        vi.advanceTimersByTime(400);

        expect(routerGetMock).not.toHaveBeenCalled();
        vi.useRealTimers();
    });

    it("populates the row and column filter select's options from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

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

    async function checkboxLabels(
        wrapper: ReturnType<typeof mountPage>,
        toggleId: string,
    ): Promise<string[]> {
        await wrapper.get(toggleId).trigger('click');

        return wrapper
            .get('[role="listbox"]')
            .findAll('label')
            .map((label) => label.text());
    }

    it("populates the product filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(await checkboxLabels(wrapper, '#filter-product')).toEqual([
            'Widgets',
        ]);
    });

    it("populates the user filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(await checkboxLabels(wrapper, '#filter-user')).toEqual([
            'Jane Doe',
        ]);
    });

    it("populates the status filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(await checkboxLabels(wrapper, '#filter-action')).toEqual([
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

    it('shows a flag badge for a flagged row and not for an unflagged one', () => {
        const wrapper = mountPage([
            cellLog({
                id: 1,
                flagged: true,
                flags: [{ id: 1, reason: 'off_hours', acknowledged: true }],
            }),
            cellLog({ id: 2, flagged: false, flags: [] }),
        ]);

        expect(
            rowCells(wrapper, 0)[1].findComponent(TriangleAlert).exists(),
        ).toBe(true);
        expect(
            rowCells(wrapper, 1)[1].findComponent(TriangleAlert).exists(),
        ).toBe(false);
    });

    it('shows the flag reason as visible text, not just an icon tooltip', () => {
        const wrapper = mountPage([
            cellLog({
                flagged: true,
                flags: [{ id: 1, reason: 'off_hours', acknowledged: true }],
            }),
        ]);

        expect(rowCells(wrapper, 0)[1].text()).toContain(
            t('cellLog.flags.reasons.off_hours'),
        );
    });

    it('shows every reason badge when a row has more than one flag', () => {
        const wrapper = mountPage([
            cellLog({
                flagged: true,
                flags: [
                    { id: 1, reason: 'off_hours', acknowledged: false },
                    { id: 2, reason: 'quick_flip', acknowledged: false },
                ],
            }),
        ]);

        const actionCell = rowCells(wrapper, 0)[1];
        expect(actionCell.text()).toContain(
            t('cellLog.flags.reasons.off_hours'),
        );
        expect(actionCell.text()).toContain(
            t('cellLog.flags.reasons.quick_flip'),
        );
    });

    it('shows an Acknowledge button when a flag is unacknowledged, and hides it once every flag is acknowledged', () => {
        const wrapper = mountPage([
            cellLog({
                id: 1,
                flagged: true,
                flags: [{ id: 1, reason: 'off_hours', acknowledged: false }],
            }),
            cellLog({
                id: 2,
                flagged: true,
                flags: [{ id: 2, reason: 'off_hours', acknowledged: true }],
            }),
        ]);

        expect(rowCells(wrapper, 0)[1].text()).toContain(
            t('cellLog.flags.acknowledge'),
        );
        expect(rowCells(wrapper, 1)[1].text()).not.toContain(
            t('cellLog.flags.acknowledge'),
        );
    });

    it('posts to the acknowledge-flags endpoint when the Acknowledge button is clicked', async () => {
        const wrapper = mountPage([
            cellLog({
                id: 42,
                flagged: true,
                flags: [
                    { id: 1, reason: 'rapid_actions', acknowledged: false },
                ],
            }),
        ]);

        const acknowledgeButton = rowCells(wrapper, 0)[1]
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.flags.acknowledge')),
            );
        await acknowledgeButton?.trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/admin/cell-logs/42/acknowledge-flags',
            {},
            { preserveScroll: true },
        );
    });

    it('preserves the current page and filters when acknowledging a flag', async () => {
        window.history.pushState(
            {},
            '',
            '/admin/cell-logs?page=6&flagged=true',
        );

        const wrapper = mountPage([
            cellLog({
                id: 42,
                flagged: true,
                flags: [
                    { id: 1, reason: 'rapid_actions', acknowledged: false },
                ],
            }),
        ]);

        const acknowledgeButton = rowCells(wrapper, 0)[1]
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.flags.acknowledge')),
            );
        await acknowledgeButton?.trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/admin/cell-logs/42/acknowledge-flags?page=6&flagged=true',
            {},
            { preserveScroll: true },
        );

        window.history.pushState({}, '', '/');
    });

    it('requests the flagged filter when the Flagged only checkbox is checked and submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-flagged').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ flagged: true }),
            { preserveState: true, replace: true },
        );
    });

    it('omits the flagged filter entirely when the checkbox is left unchecked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('form').trigger('submit');

        const [, sentFilters] = routerGetMock.mock.calls[0];
        expect(sentFilters).not.toHaveProperty('flagged');
    });

    it('counts the flagged filter towards the active filter count', () => {
        const wrapper = mountPage([], { flagged: true });

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.filters.title')),
            );
        expect(trigger?.get('span').text()).toBe('1');
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
                    boxes_count: 10,
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

    it('renders the boxes_count alongside the pallet', () => {
        const wrapper = mountPage([cellLog({ boxes_count: 6 })]);

        const palletCell = rowCells(wrapper)[3];
        expect(palletCell.text()).toContain(t('cellLog.columns.boxes'));
        expect(palletCell.text()).toContain('6');
    });

    it('omits the boxes line when the log has no boxes_count', () => {
        const wrapper = mountPage([cellLog({ boxes_count: null })]);

        expect(rowCells(wrapper)[3].text()).not.toContain(
            t('cellLog.columns.boxes'),
        );
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

        expect(rowCells(wrapper)[6].text()).toContain(
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

        const whenCell = rowCells(wrapper)[6];
        expect(whenCell.text()).toContain('1h 1m');
        expect(whenCell.text()).not.toContain(t('cellLog.columns.ongoing'));
    });

    it('shows the ongoing label alongside the duration when there is no next log', () => {
        const wrapper = mountPage([
            cellLog({ next_log_at: null, duration_seconds: 90 }),
        ]);

        const whenCell = rowCells(wrapper)[6];
        expect(whenCell.text()).toContain(t('cellLog.columns.ongoing'));
        expect(whenCell.text()).toContain('1m');
        expect(whenCell.findComponent(Clock).exists()).toBe(true);
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

    it('requests the selected products when the product filter is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-product').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ product_id: ['10'] }),
            { preserveState: true, replace: true },
        );
    });

    it('requests the selected users when the user filter is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-user').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ user_id: ['7'] }),
            { preserveState: true, replace: true },
        );
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            product_id: [10],
            user_id: [7],
            action: ['opened'],
            date_from: '2026-08-01',
            expiration_date_from: '2026-09-01',
        });
        await openFilters(wrapper);

        const clearButton = wrapper
            .findAll('button')
            .find((button) => button.text() === t('cellLog.filters.clear'));
        expect(clearButton?.findComponent(X).exists()).toBe(true);
        await clearButton?.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            { per_page: 20 },
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
        expect(wrapper.get('#filter-product').text()).toBe(
            t('cellLog.filters.all'),
        );
        expect(wrapper.get('#filter-user').text()).toBe(
            t('cellLog.filters.all'),
        );
        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).value,
        ).toBe('');
        expect(
            (
                wrapper.get('#filter-expires-within-days')
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

    it('requests expires_within_days when it is filled in instead of an expiration date range', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-expires-within-days').setValue('5');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ expires_within_days: 5 }),
            { preserveState: true, replace: true },
        );
    });

    it('disables the expires_within_days field once an expiration date range value is entered', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            (
                wrapper.get('#filter-expires-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(false);

        await wrapper
            .get('#filter-expiration-date-from')
            .setValue('2026-09-01');

        expect(
            (
                wrapper.get('#filter-expires-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
    });

    it('disables the expiration date range fields once expires_within_days is filled in', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            (
                wrapper.get('#filter-expiration-date-from')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(false);
        expect(
            (
                wrapper.get('#filter-expiration-date-to')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(false);

        await wrapper.get('#filter-expires-within-days').setValue('5');

        expect(
            (
                wrapper.get('#filter-expiration-date-from')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
        expect(
            (
                wrapper.get('#filter-expiration-date-to')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
    });

    it('re-enables the expiration date range fields once expires_within_days is cleared', async () => {
        const wrapper = mountPage([], { expires_within_days: 5 });
        await openFilters(wrapper);

        expect(
            (
                wrapper.get('#filter-expiration-date-from')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);

        await wrapper.get('#filter-expires-within-days').setValue('');

        expect(
            (
                wrapper.get('#filter-expiration-date-from')
                    .element as HTMLInputElement
            ).disabled,
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
        const clearButton = banner.get('button');
        expect(clearButton.findComponent(X).exists()).toBe(true);
        await clearButton.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            { per_page: 20 },
            { preserveState: true, replace: true },
        );
    });

    it('preselects the current per-page value in the page-size selector', () => {
        const wrapper = mountPage([], { per_page: 50 });

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '50',
        );
    });

    it('requests the new page size when the selector changes', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('select').setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-logs',
            expect.objectContaining({ per_page: 50 }),
            { preserveState: true, replace: true },
        );
    });
});

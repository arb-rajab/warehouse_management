import { Check, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CellVerificationRoundStatusBadge from '@/components/CellVerificationRoundStatusBadge.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { rowCells } from '@/testing/dom';
import {
    cellVerificationReport,
    cellVerificationRound,
    paginated,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    CellVerificationReport,
    CellVerificationReportFilterOptions,
    CellVerificationReportFilters,
    CellVerificationRound,
} from '@/types/admin';
import Show from './Show.vue';

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
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) => options?.onSuccess?.(paginated(filterOptions.products, 20)),
        }),
    };
});

const filterOptions: CellVerificationReportFilterOptions = {
    rows: [
        { id: 1, letter: 'A' },
        { id: 2, letter: 'B' },
    ],
    maxColumnNumber: 3,
    products: [{ id: 10, name: 'Widgets', ar_name: 'ودجات' }],
};

function mountPage(
    round: CellVerificationRound,
    reports: CellVerificationReport[],
    filters: CellVerificationReportFilters = {},
) {
    usePageMock.mockReturnValue({
        url: `/admin/cell-verification-rounds/${round.id}`,
        props: defaultAuthProps(),
    });

    return mount(Show, {
        props: { round, reports: paginated(reports), filters, filterOptions },
    });
}

async function openFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) =>
            button.text().includes(t('cellVerificationReport.filters.title')),
        );
    await trigger?.trigger('click');
}

describe('CellVerificationRounds Show', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock });
    });

    it('renders the round id in the page heading', () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), []);

        expect(wrapper.get('h1').text()).toBe(
            `${t('cellVerificationRound.title')} #42`,
        );
    });

    it('links back to the rounds list', () => {
        const wrapper = mountPage(cellVerificationRound(), []);

        const backLink = wrapper
            .findAll('a')
            .find((a) =>
                a.text().includes(t('cellVerificationRound.show.backToList')),
            );
        expect(backLink?.attributes('href')).toBe(
            '/admin/cell-verification-rounds',
        );
    });

    it('links the worker to their show page when the round has one', () => {
        const wrapper = mountPage(
            cellVerificationRound({ user: { id: 7, name: 'Jane Doe' } }),
            [],
        );

        const link = wrapper
            .findAll('a')
            .find((a) => a.attributes('href') === '/admin/users/7');
        expect(link?.text()).toContain('Jane Doe');
    });

    it('shows an em-dash for the worker summary field when the round has none', () => {
        const wrapper = mountPage(
            cellVerificationRound({ user: undefined }),
            [],
        );

        expect(
            wrapper.findAll('a').some((a) => a.text().includes('Jane Doe')),
        ).toBe(false);

        const label = wrapper
            .findAll('div')
            .find((div) =>
                div.text().includes(t('cellVerificationRound.columns.user')),
            );
        expect(label?.text()).toContain('—');
    });

    it('shows the formatted started_at timestamp', () => {
        const wrapper = mountPage(
            cellVerificationRound({ started_at: '2026-08-01T10:00:00Z' }),
            [],
        );

        expect(wrapper.text()).toContain(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
    });

    it('shows the formatted completed_at timestamp when the round is completed', () => {
        const wrapper = mountPage(
            cellVerificationRound({ completed_at: '2026-08-01T11:00:00Z' }),
            [],
        );

        expect(wrapper.text()).toContain(
            formatDateTime('2026-08-01T11:00:00Z'),
        );
        expect(wrapper.text()).not.toContain(
            t('cellVerificationRound.status.inProgress'),
        );
        expect(
            wrapper.findComponent(CellVerificationRoundStatusBadge).props(),
        ).toMatchObject({ completedAt: '2026-08-01T11:00:00Z' });
    });

    it('shows an in-progress badge when the round has no completed_at', () => {
        const wrapper = mountPage(
            cellVerificationRound({ completed_at: null }),
            [],
        );

        expect(wrapper.text()).toContain(
            t('cellVerificationRound.status.inProgress'),
        );
    });

    it('shows the letters of the rows the round covers, under its own label', () => {
        const wrapper = mountPage(
            cellVerificationRound({
                rows: [
                    { id: 3, letter: 'A' },
                    { id: 9, letter: 'D' },
                ],
            }),
            [],
        );

        expect(wrapper.text()).toContain(t('cellVerificationRound.show.rows'));
        expect(wrapper.text()).toContain('A, D');
    });

    it('shows an em dash for the covered rows when the round has none', () => {
        const wrapper = mountPage(cellVerificationRound({ rows: [] }), []);

        expect(wrapper.text()).toContain(t('cellVerificationRound.show.rows'));
        expect(wrapper.text()).toContain('—');
    });

    it('renders an export link with the current filters in the query', () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), [], {
            product_id: [10],
        });

        const exportLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('cellVerificationReport.export')));
        const href = exportLink?.attributes('href') ?? '';

        expect(href).toContain('/admin/cell-verification-rounds/42/export');
        expect(href).toContain('product_id');
    });

    it('renders every column header', () => {
        const wrapper = mountPage(cellVerificationRound(), []);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('cellVerificationReport.columns.cell'),
            t('cellVerificationReport.columns.correctness'),
            t('cellVerificationReport.columns.expected'),
            t('cellVerificationReport.columns.reported'),
            t('cellVerificationReport.columns.note'),
            t('cellVerificationReport.columns.when'),
        ]);
    });

    it('shows the empty message when there are no reports', () => {
        const wrapper = mountPage(cellVerificationRound(), []);

        expect(wrapper.text()).toContain(t('cellVerificationReport.empty'));
    });

    it('hides the filter fields until the Filters button is clicked', () => {
        const wrapper = mountPage(cellVerificationRound(), []);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('#filter-product').exists()).toBe(false);
    });

    it('opens the filter dialog when the Filters button is clicked', async () => {
        const wrapper = mountPage(cellVerificationRound(), []);

        await openFilters(wrapper);

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            t('cellVerificationReport.filters.title'),
        );
        expect(wrapper.find('#filter-row').exists()).toBe(true);
        expect(wrapper.find('#filter-column').exists()).toBe(true);
        expect(wrapper.find('#filter-product').exists()).toBe(true);
        expect(wrapper.find('#filter-correctness').exists()).toBe(true);
        expect(wrapper.find('#filter-date-from').exists()).toBe(true);
    });

    it('groups the filter fields under section headings', async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.text()).toContain(
            t('cellVerificationReport.filters.sections.location'),
        );
        expect(dialog.text()).toContain(
            t('cellVerificationReport.filters.sections.activity'),
        );
        expect(dialog.text()).toContain(
            t('cellVerificationReport.filters.sections.date'),
        );
    });

    it('closes the filter dialog after Apply is clicked', async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        const applyButton = wrapper
            .get('form')
            .findAll('button')
            .find((button) =>
                button
                    .text()
                    .includes(t('cellVerificationReport.filters.apply')),
            );
        expect(applyButton?.findComponent(Check).exists()).toBe(true);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('does not show a filter count badge when no filters are active', () => {
        const wrapper = mountPage(cellVerificationRound(), []);

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button
                    .text()
                    .includes(t('cellVerificationReport.filters.title')),
            );
        expect(trigger?.find('span').exists()).toBe(false);
    });

    it('shows a filter count badge for each distinct active filter', () => {
        const wrapper = mountPage(cellVerificationRound(), [], {
            row_id: 1,
            product_id: [10],
            is_correct: false,
            date_from: '2026-08-01',
        });

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button
                    .text()
                    .includes(t('cellVerificationReport.filters.title')),
            );
        expect(trigger?.get('span').text()).toBe('4');
    });

    it("populates the row and column filter select's options from filterOptions", async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        expect(
            wrapper
                .get('#filter-row')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellVerificationReport.filters.all'), 'A', 'B']);
        expect(
            wrapper
                .get('#filter-column')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellVerificationReport.filters.all'), '1', '2', '3']);
    });

    it("populates the product filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        await wrapper.get('#filter-product').trigger('click');

        // A product option renders two lines — the locale's label and the
        // store's other name, since the search matches either column (see
        // .ai/rules/shared-database.md).
        expect(
            wrapper
                .get('[role="listbox"]')
                .findAll('[data-testid="product-option-name"]')
                .map((name) => name.text()),
        ).toEqual(['Widgets']);
        expect(
            wrapper
                .get('[role="listbox"]')
                .findAll('[data-testid="product-option-alternate-name"]')
                .map((name) => name.text()),
        ).toEqual(['ودجات']);
    });

    it("populates the correctness filter's options", async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        expect(
            wrapper
                .get('#filter-correctness')
                .findAll('option')
                .map((option) => option.text()),
        ).toEqual([
            t('cellVerificationReport.filters.correctnessAll'),
            t('cellVerificationReport.filters.correctnessCorrect'),
            t('cellVerificationReport.filters.correctnessIncorrect'),
        ]);
    });

    it('links the cell to its row show page', () => {
        const wrapper = mountPage(cellVerificationRound(), [
            cellVerificationReport({
                cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
            }),
        ]);

        const link = rowCells(wrapper)[0].get('a');
        expect(link.attributes('href')).toBe('/admin/rows/A');
        expect(link.text()).toContain(formatSlot('A', 3, 2));
    });

    it('renders the correctness badge for a correct and an incorrect report', () => {
        const wrapper = mountPage(cellVerificationRound(), [
            cellVerificationReport({ id: 1, is_correct: true }),
            cellVerificationReport({ id: 2, is_correct: false }),
        ]);

        expect(rowCells(wrapper, 0)[1].text()).toBe(
            t('cellVerificationReport.correct'),
        );
        expect(rowCells(wrapper, 1)[1].text()).toBe(
            t('cellVerificationReport.incorrect'),
        );
    });

    it('renders the expected and reported cell state as a translated label', () => {
        const wrapper = mountPage(cellVerificationRound(), [
            cellVerificationReport({
                expected: {
                    cell_state: 'full',
                    product: null,
                    boxes_count: null,
                    expiration_date: null,
                },
                reported: {
                    cell_state: 'empty',
                    product: null,
                    boxes_count: null,
                    expiration_date: null,
                },
            }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[2].text()).toContain(cellStateLabel('full'));
        expect(cells[2].text()).not.toContain('full');
        expect(cells[3].text()).toContain(cellStateLabel('empty'));
        expect(cells[3].text()).not.toContain('empty');
    });

    it('shows a dash for the reported state when the report has no reported snapshot state yet', () => {
        const wrapper = mountPage(cellVerificationRound(), [
            cellVerificationReport({
                reported: {
                    cell_state: null,
                    product: null,
                    boxes_count: null,
                    expiration_date: null,
                },
            }),
        ]);

        expect(rowCells(wrapper)[3].text()).toContain('—');
    });

    it('renders the expected and reported product/box count when present', () => {
        const wrapper = mountPage(cellVerificationRound(), [
            cellVerificationReport({
                expected: {
                    cell_state: 'full',
                    product: {
                        id: 10,
                        name: 'Widgets',
                        ar_name: 'ودجات',
                        image_url: null,
                        boxes_count: 10,
                    },
                    boxes_count: 5,
                    expiration_date: null,
                },
            }),
        ]);

        expect(rowCells(wrapper)[2].text()).toContain('Widgets');
        expect(rowCells(wrapper)[2].text()).toContain('5');
    });

    it('renders the note text or a dash', () => {
        const withNote = mountPage(cellVerificationRound(), [
            cellVerificationReport({ note: 'Looks off' }),
        ]);
        expect(rowCells(withNote)[4].text()).toBe('Looks off');

        const withoutNote = mountPage(cellVerificationRound(), [
            cellVerificationReport({ note: null }),
        ]);
        expect(rowCells(withoutNote)[4].text()).toBe('—');
    });

    it('renders the created_at timestamp formatted', () => {
        const wrapper = mountPage(cellVerificationRound(), [
            cellVerificationReport({ created_at: '2026-08-01T10:00:00Z' }),
        ]);

        expect(rowCells(wrapper)[5].text()).toBe(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), []);
        await openFilters(wrapper);

        await wrapper.get('#filter-row').setValue('1');
        await wrapper.get('#filter-column').setValue('2');
        await wrapper.get('#filter-correctness').setValue('true');
        await wrapper.get('#filter-date-from').setValue('2026-08-01');
        await wrapper.get('#filter-date-to').setValue('2026-08-10');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            expect.objectContaining({
                row_id: 1,
                column_number: 2,
                is_correct: true,
                date_from: '2026-08-01',
                date_to: '2026-08-10',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('requests the selected products when the product filter is submitted', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), []);
        await openFilters(wrapper);

        await wrapper.get('#filter-product').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            expect.objectContaining({ product_id: ['10'] }),
            { preserveState: true, replace: true },
        );
    });

    it('requests created_within_days when it is filled in instead of a date range', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), []);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            expect.objectContaining({ created_within_days: 7 }),
            { preserveState: true, replace: true },
        );
    });

    it('disables the created_within_days field once a date range value is entered', async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        await wrapper.get('#filter-date-from').setValue('2026-08-01');

        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
    });

    it('disables the date range fields once created_within_days is filled in', async () => {
        const wrapper = mountPage(cellVerificationRound(), []);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(true);
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), [], {
            row_id: 1,
            product_id: [10],
            is_correct: true,
            date_from: '2026-08-01',
        });
        await openFilters(wrapper);

        const clearButton = wrapper
            .findAll('button')
            .find(
                (button) =>
                    button.text() === t('cellVerificationReport.filters.clear'),
            );
        expect(clearButton?.findComponent(X).exists()).toBe(true);
        await clearButton?.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            { per_page: 20 },
            { preserveState: true, replace: true },
        );
        expect(
            (wrapper.get('#filter-row').element as HTMLSelectElement).value,
        ).toBe('');
        expect(wrapper.get('#filter-product').text()).toBe(
            t('cellVerificationReport.filters.all'),
        );
        expect(
            (wrapper.get('#filter-correctness').element as HTMLSelectElement)
                .value,
        ).toBe('');
    });

    it('applies a sort immediately when the sortable header is clicked, without waiting for Apply', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), []);

        const whenHeader = wrapper
            .findAll('thead th')
            .find((th) =>
                th.text().includes(t('cellVerificationReport.columns.when')),
            );
        await whenHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            expect.objectContaining({ sort_direction: 'asc' }),
            { preserveState: true, replace: true },
        );
    });

    it('flips the sort direction when the sortable header is clicked again', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), [], {
            sort_direction: 'asc',
        });

        const whenHeader = wrapper
            .findAll('thead th')
            .find((th) =>
                th.text().includes(t('cellVerificationReport.columns.when')),
            );
        await whenHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            expect.objectContaining({ sort_direction: 'desc' }),
            { preserveState: true, replace: true },
        );
    });

    it('preselects the current per-page value in the page-size selector', () => {
        const wrapper = mountPage(cellVerificationRound(), [], {
            per_page: 50,
        });

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '50',
        );
    });

    it('requests the new page size when the selector changes', async () => {
        const wrapper = mountPage(cellVerificationRound({ id: 42 }), []);

        await wrapper.get('select').setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds/42',
            expect.objectContaining({ per_page: 50 }),
            { preserveState: true, replace: true },
        );
    });
});

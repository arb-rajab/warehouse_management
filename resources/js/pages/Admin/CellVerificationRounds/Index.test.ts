import { Check, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { cellVerificationRound, paginated } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    CellVerificationRound,
    CellVerificationRoundFilterOptions,
    CellVerificationRoundFilters,
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

const filterOptions: CellVerificationRoundFilterOptions = {
    users: [{ id: 7, name: 'Jane Doe' }],
};

function mountPage(
    rounds: CellVerificationRound[],
    filters: CellVerificationRoundFilters = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin/cell-verification-rounds',
        props: defaultAuthProps(),
    });

    return mount(Index, {
        props: { rounds: paginated(rounds), filters, filterOptions },
    });
}

async function openFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) =>
            button.text().includes(t('cellVerificationRound.filters.title')),
        );
    await trigger?.trigger('click');
}

describe('CellVerificationRounds Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock });
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('cellVerificationRound.columns.id'),
            t('cellVerificationRound.columns.user'),
            t('cellVerificationRound.columns.startedAt'),
            t('cellVerificationRound.columns.completedAt'),
            t('cellVerificationRound.columns.reportsCount'),
        ]);
    });

    it('shows the empty message when there are no rounds', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('cellVerificationRound.empty'));
    });

    it('hides the filter fields until the Filters button is clicked', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('#filter-user').exists()).toBe(false);
    });

    it('opens the filter dialog when the Filters button is clicked', async () => {
        const wrapper = mountPage([]);

        await openFilters(wrapper);

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            t('cellVerificationRound.filters.title'),
        );
        expect(wrapper.find('#filter-user').exists()).toBe(true);
        expect(wrapper.find('#filter-completed').exists()).toBe(true);
        expect(wrapper.find('#filter-date-from').exists()).toBe(true);
        expect(wrapper.find('#filter-date-to').exists()).toBe(true);
        expect(wrapper.find('#filter-created-within-days').exists()).toBe(true);
    });

    it('closes the filter dialog after Apply is clicked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const applyButton = wrapper
            .get('form')
            .findAll('button')
            .find((button) =>
                button
                    .text()
                    .includes(t('cellVerificationRound.filters.apply')),
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
                button
                    .text()
                    .includes(t('cellVerificationRound.filters.title')),
            );
        expect(trigger?.find('span').exists()).toBe(false);
    });

    it('shows a filter count badge for each distinct active filter', () => {
        const wrapper = mountPage([], {
            user_id: [7],
            completed: true,
            date_from: '2026-08-01',
        });

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button
                    .text()
                    .includes(t('cellVerificationRound.filters.title')),
            );
        expect(trigger?.get('span').text()).toBe('3');
    });

    it("populates the worker filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-user').trigger('click');
        const labels = wrapper
            .get('[role="listbox"]')
            .findAll('label')
            .map((label) => label.text());
        expect(labels).toEqual(['Jane Doe']);
    });

    it("populates the status filter's options", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            wrapper
                .get('#filter-completed')
                .findAll('option')
                .map((option) => option.text()),
        ).toEqual([
            t('cellVerificationRound.filters.completedAll'),
            t('cellVerificationRound.filters.completedYes'),
            t('cellVerificationRound.filters.completedNo'),
        ]);
    });

    it('links the round id to its show page', () => {
        const wrapper = mountPage([cellVerificationRound({ id: 42 })]);

        const link = rowCells(wrapper)[0].get('a');
        expect(link.attributes('href')).toBe(
            '/admin/cell-verification-rounds/42',
        );
        expect(link.text()).toContain('42');
    });

    it("links the worker cell to the user's show page", () => {
        const wrapper = mountPage([
            cellVerificationRound({ user: { id: 7, name: 'Jane Doe' } }),
        ]);

        const link = rowCells(wrapper)[1].get('a');
        expect(link.attributes('href')).toBe('/admin/users/7');
        expect(link.text()).toBe('Jane Doe');
    });

    it('renders an empty worker cell when the round has no user', () => {
        const wrapper = mountPage([cellVerificationRound({ user: undefined })]);

        expect(rowCells(wrapper)[1].find('a').exists()).toBe(false);
    });

    it('renders the started_at timestamp formatted', () => {
        const wrapper = mountPage([
            cellVerificationRound({ started_at: '2026-08-01T10:00:00Z' }),
        ]);

        expect(rowCells(wrapper)[2].text()).toBe(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
    });

    it('shows a completed badge when the round has completed_at', () => {
        const wrapper = mountPage([
            cellVerificationRound({ completed_at: '2026-08-01T11:00:00Z' }),
        ]);

        expect(rowCells(wrapper)[3].text()).toBe(
            t('cellVerificationRound.status.completed'),
        );
    });

    it('shows an in-progress badge when the round has no completed_at', () => {
        const wrapper = mountPage([
            cellVerificationRound({ completed_at: null }),
        ]);

        expect(rowCells(wrapper)[3].text()).toBe(
            t('cellVerificationRound.status.inProgress'),
        );
    });

    it('renders the reports_count', () => {
        const wrapper = mountPage([
            cellVerificationRound({ reports_count: 12 }),
        ]);

        expect(rowCells(wrapper)[4].text()).toBe('12');
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-user').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
        await wrapper.get('#filter-completed').setValue('true');
        await wrapper.get('#filter-date-from').setValue('2026-08-01');
        await wrapper.get('#filter-date-to').setValue('2026-08-10');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds',
            expect.objectContaining({
                user_id: ['7'],
                completed: true,
                date_from: '2026-08-01',
                date_to: '2026-08-10',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('requests created_within_days when it is filled in instead of a date range', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds',
            expect.objectContaining({ created_within_days: 7 }),
            { preserveState: true, replace: true },
        );
    });

    it('disables the created_within_days field once a date range value is entered', async () => {
        const wrapper = mountPage([]);
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
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(true);
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            user_id: [7],
            completed: true,
            date_from: '2026-08-01',
        });
        await openFilters(wrapper);

        const clearButton = wrapper
            .findAll('button')
            .find(
                (button) =>
                    button.text() === t('cellVerificationRound.filters.clear'),
            );
        expect(clearButton?.findComponent(X).exists()).toBe(true);
        await clearButton?.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds',
            { per_page: 20 },
            { preserveState: true, replace: true },
        );
        expect(wrapper.get('#filter-user').text()).toBe(
            t('cellVerificationRound.filters.all'),
        );
        expect(
            (wrapper.get('#filter-completed').element as HTMLSelectElement)
                .value,
        ).toBe('');
        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .value,
        ).toBe('');
    });

    it('applies a sort immediately when the sortable header is clicked, without waiting for Apply', async () => {
        const wrapper = mountPage([]);

        const startedHeader = wrapper
            .findAll('thead th')
            .find((th) =>
                th
                    .text()
                    .includes(t('cellVerificationRound.columns.startedAt')),
            );
        await startedHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds',
            expect.objectContaining({ sort_direction: 'asc' }),
            { preserveState: true, replace: true },
        );
    });

    it('flips the sort direction when the sortable header is clicked again', async () => {
        const wrapper = mountPage([], { sort_direction: 'asc' });

        const startedHeader = wrapper
            .findAll('thead th')
            .find((th) =>
                th
                    .text()
                    .includes(t('cellVerificationRound.columns.startedAt')),
            );
        await startedHeader?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cell-verification-rounds',
            expect.objectContaining({ sort_direction: 'desc' }),
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
            '/admin/cell-verification-rounds',
            expect.objectContaining({ per_page: 50 }),
            { preserveState: true, replace: true },
        );
    });
});

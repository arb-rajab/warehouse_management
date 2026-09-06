import { TriangleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import {
    cellLog,
    cellVerificationReport,
    paginated,
    user,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    CellStatusLog,
    CellVerificationReport,
    User,
} from '@/types/admin';
import Show from './Show.vue';

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
    logs: CellStatusLog[],
    userOverrides: Partial<User> = {},
    perPage = 20,
    reports: CellVerificationReport[] = [],
    reportsPerPage = 20,
) {
    usePageMock.mockReturnValue({
        url: '/admin/users/7',
        props: defaultAuthProps({ auth: { user: { name: 'Admin', id: 1 } } }),
    });

    return mount(Show, {
        props: {
            user: user(userOverrides),
            logs: paginated(logs, perPage),
            reports: paginated(reports, reportsPerPage),
            filters: { per_page: perPage, reports_per_page: reportsPerPage },
        },
    });
}

describe('Users Show', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, routerPostMock });
    });

    it('shows the users name in the title', () => {
        const wrapper = mountPage([], { name: 'Bob Mover' });

        expect(wrapper.text()).toContain(
            t('users.show.title', { name: 'Bob Mover' }),
        );
    });

    it("links to the user's edit page", () => {
        const wrapper = mountPage([], { id: 9 });

        const editLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('users.show.editUser')));
        expect(editLink?.attributes('href')).toBe('/admin/users/9/edit');
    });

    it('renders every action column header, excluding Done By', () => {
        const wrapper = mountPage([]);

        const headers = wrapper
            .findAll('table')[0]
            .findAll('thead th')
            .map((th) => th.text());
        expect(headers).toEqual([
            t('cellLog.columns.cell'),
            t('cellLog.columns.action'),
            t('cellLog.columns.product'),
            t('cellLog.columns.pallet'),
            t('cellLog.columns.note'),
            t('cellLog.columns.when'),
        ]);
    });

    it('shows the empty message when there are no actions', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('users.show.empty'));
    });

    it('renders the cell, action, product, pallet, boxes, note, when, and duration for an entry', () => {
        const wrapper = mountPage([
            cellLog({
                action: 'opened',
                from_state: 'full',
                to_state: 'opened',
                boxes_count: 6,
            }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[0].text()).toContain('A3·2');
        expect(cells[1].text()).toContain(t('cellLog.actions.opened'));
        expect(cells[2].text()).toContain('Widgets');
        expect(cells[3].text()).toContain('#55');
        expect(cells[3].text()).toContain('6');
        expect(cells[4].text()).toBe('Handle with care');
        expect(cells[5].text()).toContain(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
        expect(cells[5].text()).toContain('1h');
    });

    it('shows a dash when the entry has no boxes_count', () => {
        const wrapper = mountPage([cellLog({ boxes_count: null })]);

        expect(rowCells(wrapper)[3].text()).not.toContain(
            t('cellLog.columns.boxes'),
        );
    });

    it('shows a flag badge for a flagged entry and not for an unflagged one', () => {
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

    it('posts to the acknowledge-flags endpoint with return_to=user when the Acknowledge button is clicked', async () => {
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
            { return_to: 'user' },
            { preserveScroll: true },
        );
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

        expect(wrapper.findAll('table')[0].findAll('tbody tr')).toHaveLength(1);
        const actionCell = rowCells(wrapper)[1];
        expect(actionCell.text()).toContain(t('cellLog.actions.transferred'));
    });

    it('navigates to the filtered cell log when the pallet history button is clicked', async () => {
        const wrapper = mountPage([
            cellLog({ pallet: { id: 55, expiration_date: null } }),
        ]);

        await rowCells(wrapper)[3].get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith('/admin/cell-logs', {
            pallet_id: 55,
        });
    });

    it('shows a dash when the entry has no pallet', () => {
        const wrapper = mountPage([cellLog({ pallet: null })]);

        expect(rowCells(wrapper)[3].text()).toBe('—');
    });

    it('renders the pallet expiration date', () => {
        const wrapper = mountPage([
            cellLog({ pallet: { id: 55, expiration_date: '2026-09-01' } }),
        ]);

        expect(rowCells(wrapper)[3].text()).toContain(formatDate('2026-09-01'));
    });

    it('preselects the current per-page value in the actions page-size selector', () => {
        const wrapper = mountPage([], { id: 7 }, 50);

        expect(
            (wrapper.findAll('select')[0].element as HTMLSelectElement).value,
        ).toBe('50');
    });

    it('requests the new page size when the actions selector changes, preserving the reports page size', async () => {
        const wrapper = mountPage([], { id: 7 }, 25, [], 15);

        await wrapper.findAll('select')[0].setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/users/7',
            { per_page: 50, reports_per_page: 15 },
            { preserveState: true, replace: true },
        );
    });

    it('renders every reports column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper
            .findAll('table')[1]
            .findAll('thead th')
            .map((th) => th.text());
        expect(headers).toEqual([
            t('cellVerificationReport.columns.round'),
            t('cellVerificationReport.columns.cell'),
            t('cellVerificationReport.columns.correctness'),
            t('cellVerificationReport.columns.expected'),
            t('cellVerificationReport.columns.reported'),
            t('cellVerificationReport.columns.note'),
            t('cellVerificationReport.columns.when'),
        ]);
    });

    it('shows the empty message when there are no verification reports', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('cellVerificationReport.empty'));
    });

    it('links a report row to its round and cell, and shows its correctness/expected/reported/note/when', () => {
        const wrapper = mountPage([], {}, 20, [
            cellVerificationReport({
                id: 3,
                cell_verification_round_id: 42,
                is_correct: false,
                cell: { row_letter: 'C', cell_number: 4, flat_number: 1 },
                note: 'Missing boxes',
                created_at: '2026-08-02T09:00:00Z',
            }),
        ]);

        const reportRow = wrapper.findAll('table')[1].findAll('tbody tr')[0];
        const cells = reportRow.findAll('td');

        const roundLink = cells[0].get('a');
        expect(roundLink.attributes('href')).toBe(
            '/admin/cell-verification-rounds/42',
        );
        expect(roundLink.text()).toContain('#42');

        const cellLink = cells[1].get('a');
        expect(cellLink.attributes('href')).toBe('/admin/rows/C');
        expect(cellLink.text()).toContain('C4·1');

        expect(cells[2].text()).toContain(
            t('cellVerificationReport.incorrect'),
        );
        expect(cells[5].text()).toBe('Missing boxes');
        expect(cells[6].text()).toContain(
            formatDateTime('2026-08-02T09:00:00Z'),
        );
    });

    it('preselects the current per-page value in the reports page-size selector', () => {
        const wrapper = mountPage([], {}, 20, [], 50);

        expect(
            (wrapper.findAll('select')[1].element as HTMLSelectElement).value,
        ).toBe('50');
    });

    it('requests the new page size when the reports selector changes, preserving the actions page size', async () => {
        const wrapper = mountPage([], { id: 7 }, 25, [], 15);

        await wrapper.findAll('select')[1].setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/users/7',
            { per_page: 25, reports_per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

import { TriangleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { CellStatusLog, Paginated, User } from '@/types/admin';
import Show from './Show.vue';

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

function user(overrides: Partial<User> = {}): User {
    return {
        id: 7,
        name: 'Jane Doe',
        email: 'jane@example.com',
        is_admin: false,
        ...overrides,
    };
}

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
        flagged: false,
        flags: [],
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

function mountPage(logs: CellStatusLog[], userOverrides: Partial<User> = {}) {
    usePageMock.mockReturnValue({
        url: '/admin/users/7',
        props: { locale: 'en', auth: { user: { name: 'Admin', id: 1 } } },
    });

    return mount(Show, {
        props: { user: user(userOverrides), logs: paginatedLogs(logs) },
    });
}

function rowCells(wrapper: ReturnType<typeof mountPage>, rowIndex = 0) {
    return wrapper.findAll('tbody tr')[rowIndex].findAll('td');
}

describe('Users Show', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerGetMock.mockReset();
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

    it('renders every column header, excluding Done By', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('cellLog.columns.cell'),
            t('cellLog.columns.action'),
            t('cellLog.columns.product'),
            t('cellLog.columns.pallet'),
            t('cellLog.columns.note'),
            t('cellLog.columns.when'),
            t('cellLog.columns.duration'),
        ]);
    });

    it('shows the empty message when there are no actions', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('users.show.empty'));
    });

    it('renders the cell, action, product, pallet, note, when, and duration for an entry', () => {
        const wrapper = mountPage([
            cellLog({
                action: 'opened',
                from_state: 'full',
                to_state: 'opened',
            }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[0].text()).toContain('A3·2');
        expect(cells[1].text()).toContain(t('cellLog.actions.opened'));
        expect(cells[2].text()).toContain('Widgets');
        expect(cells[3].text()).toContain('#55');
        expect(cells[4].text()).toBe('Handle with care');
        expect(cells[5].text()).toBe(formatDateTime('2026-08-01T10:00:00Z'));
        expect(cells[6].text()).toContain('1h');
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

    it('does not show an Acknowledge action, even for an unacknowledged flag', () => {
        const wrapper = mountPage([
            cellLog({
                flagged: true,
                flags: [{ id: 1, reason: 'off_hours', acknowledged: false }],
            }),
        ]);

        expect(wrapper.text()).not.toContain(t('cellLog.flags.acknowledge'));
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

        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
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
});

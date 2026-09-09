import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cellStateLabel } from '@/lib/cellStateColor';
import { cellLogActionLabel } from '@/lib/cellStatusLogDisplay';
import { formatDate, formatDateTime, formatDuration } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { cellLog } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { CellStatusLog } from '@/types/admin';
import CellLogFlagBadges from './CellLogFlagBadges.vue';
import CellStatusLogRowCells from './CellStatusLogRowCells.vue';
import TableLink from './TableLink.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function mountCells(
    log: CellStatusLog,
    props: { showUserColumn?: boolean; returnTo?: 'user' } = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin/cell-logs',
        props: defaultAuthProps(),
    });

    return mount(CellStatusLogRowCells, { props: { log, ...props } });
}

function noteText(note: string | null): string {
    return mountCells(cellLog({ note })).findAll('td')[4].text();
}

describe('CellStatusLogRowCells', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('renders one cell per column, without the user column by default', () => {
        const wrapper = mountCells(cellLog());

        expect(wrapper.findAll('td')).toHaveLength(6);
    });

    it('renders the user column when asked, in the doneBy column position', () => {
        const wrapper = mountCells(cellLog(), { showUserColumn: true });

        const cells = wrapper.findAll('td');
        expect(cells).toHaveLength(7);
        expect(cells[5].text()).toBe('Jane Doe');
    });

    it('renders the source slot, and no arrow when the log is not a transfer', () => {
        const wrapper = mountCells(cellLog());

        expect(wrapper.findAll('td')[0].text()).toBe(formatSlot('A', 3, 2));
        expect(wrapper.findAllComponents(TableLink)).toHaveLength(1);
    });

    it('renders both slots for a transfer, source first then destination', () => {
        const wrapper = mountCells(
            cellLog({
                action: 'transferred_out',
                related_cell: {
                    row_letter: 'B',
                    cell_number: 1,
                    flat_number: 4,
                },
            }),
        );

        const links = wrapper.findAllComponents(TableLink);
        expect(links).toHaveLength(2);
        expect(links[0].text()).toBe(formatSlot('A', 3, 2));
        expect(links[1].text()).toBe(formatSlot('B', 1, 4));
    });

    it('renders the action label and the from/to state transition', () => {
        const wrapper = mountCells(cellLog({ action: 'opened' }));

        const actionCell = wrapper.findAll('td')[1];
        expect(actionCell.text()).toContain(cellLogActionLabel('opened'));
        expect(actionCell.text()).toContain(cellStateLabel('empty'));
        expect(actionCell.text()).toContain(cellStateLabel('full'));
    });

    it('always passes the log flags to CellLogFlagBadges', () => {
        const flags: CellStatusLog['flags'] = [
            { id: 1, reason: 'off_hours', acknowledged: false },
        ];
        const wrapper = mountCells(cellLog({ flags }));

        expect(wrapper.findComponent(CellLogFlagBadges).props('flags')).toEqual(
            flags,
        );
    });

    it('acknowledges flags with a null return_to by default', async () => {
        const wrapper = mountCells(
            cellLog({
                id: 42,
                flags: [{ id: 1, reason: 'off_hours', acknowledged: false }],
            }),
        );

        await wrapper.findAll('td')[1].get('button').trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/admin/cell-logs/42/acknowledge-flags',
            { return_to: null },
            { preserveScroll: true },
        );
    });

    it('passes returnTo through when acknowledging flags', async () => {
        const wrapper = mountCells(
            cellLog({
                id: 42,
                flags: [{ id: 1, reason: 'off_hours', acknowledged: false }],
            }),
            { returnTo: 'user' },
        );

        await wrapper.findAll('td')[1].get('button').trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/admin/cell-logs/42/acknowledge-flags',
            { return_to: 'user' },
            { preserveScroll: true },
        );
    });

    it('hides the acknowledge button once every flag is acknowledged', () => {
        const wrapper = mountCells(
            cellLog({
                flags: [{ id: 1, reason: 'off_hours', acknowledged: true }],
            }),
        );

        expect(wrapper.findAll('td')[1].find('button').exists()).toBe(false);
    });

    it('renders the product name and image when the log has a product', () => {
        const wrapper = mountCells(
            cellLog({
                product: {
                    id: 10,
                    name: 'Widgets',
                    ar_name: 'ودجات',
                    image_url: '/img/widgets.png',
                    boxes_count: 10,
                },
            }),
        );

        const productCell = wrapper.findAll('td')[2];
        expect(productCell.text()).toContain('Widgets');
        expect(productCell.get('img').attributes('src')).toBe(
            '/img/widgets.png',
        );
    });

    it('falls back to a dash when the log has no product', () => {
        const wrapper = mountCells(cellLog({ product: null }));

        expect(wrapper.findAll('td')[2].text()).toBe('—');
    });

    it('emits view-pallet-history with the pallet id when the pallet is clicked', async () => {
        const wrapper = mountCells(cellLog());

        await wrapper.findAll('td')[3].get('button').trigger('click');

        expect(wrapper.emitted('view-pallet-history')).toEqual([[55]]);
    });

    it('renders the boxes count and expiry alongside the pallet id', () => {
        const wrapper = mountCells(cellLog({ boxes_count: 4 }));

        const palletCell = wrapper.findAll('td')[3];
        expect(palletCell.text()).toContain('#55');
        expect(palletCell.text()).toContain(t('cellLog.columns.boxes'));
        expect(palletCell.text()).toContain('4');
        expect(palletCell.text()).toContain(formatDate('2026-09-01'));
    });

    it('falls back to a dash when the log has no pallet', () => {
        const wrapper = mountCells(cellLog({ pallet: null }));

        expect(wrapper.findAll('td')[3].text()).toBe('—');
    });

    it('renders the note, and a dash when there is none', () => {
        expect(noteText('Handle with care')).toBe('Handle with care');
        expect(noteText(null)).toBe('—');
    });

    it('renders the timestamp and duration in the when column', () => {
        const wrapper = mountCells(cellLog());

        const whenCell = wrapper.findAll('td')[5];
        expect(whenCell.text()).toContain(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
        expect(whenCell.text()).toContain(formatDuration(3600));
    });

    it('marks a log with no next_log_at as still ongoing', () => {
        const ongoing = mountCells(cellLog({ next_log_at: null }));
        const finished = mountCells(
            cellLog({ next_log_at: '2026-08-01T11:00:00Z' }),
        );

        expect(ongoing.findAll('td')[5].text()).toContain(
            t('cellLog.columns.ongoing'),
        );
        expect(finished.findAll('td')[5].text()).not.toContain(
            t('cellLog.columns.ongoing'),
        );
    });

    it("renders the product's Arabic name and alt text when the locale is Arabic", () => {
        i18n.global.locale.value = 'ar';

        const wrapper = mountCells(
            cellLog({
                product: {
                    id: 10,
                    name: 'Widgets',
                    ar_name: 'ودجات',
                    image_url: '/img/widgets.png',
                    boxes_count: 10,
                },
            }),
        );

        const productCell = wrapper.findAll('td')[2];
        expect(productCell.text()).toContain('ودجات');
        expect(productCell.text()).not.toContain('Widgets');
        expect(productCell.get('img').attributes('alt')).toBe('ودجات');
    });

    it('falls back to the base product name in Arabic when the store never translated it', () => {
        i18n.global.locale.value = 'ar';

        const wrapper = mountCells(
            cellLog({
                product: {
                    id: 10,
                    name: 'Widgets',
                    ar_name: '',
                    image_url: '/img/widgets.png',
                    boxes_count: 10,
                },
            }),
        );

        const productCell = wrapper.findAll('td')[2];
        expect(productCell.text()).toContain('Widgets');
        expect(productCell.get('img').attributes('alt')).toBe('Widgets');
    });
});

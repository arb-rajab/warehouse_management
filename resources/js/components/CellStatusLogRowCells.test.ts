import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as cellLogsIndex,
    acknowledgeFlags,
} from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { cellStateLabel } from '@/lib/cellStateColor';
import { cellLogActionLabel } from '@/lib/cellStatusLogDisplay';
import { formatDate, formatDateTime, formatDuration } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { cellLog } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { CellStatusLog } from '@/types/admin';
import CellLogFlagBadges from './CellLogFlagBadges.vue';
import CellStatusLogRowCells from './CellStatusLogRowCells.vue';
import ProductActiveBadge from './ProductActiveBadge.vue';
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
    props: {
        showUserColumn?: boolean;
        returnTo?: 'user';
        multipleWarehouses?: boolean;
    } = {},
) {
    usePageMock.mockReturnValue({
        url: cellLogsIndex.url(),
        props: defaultAuthProps(),
    });

    return mount(CellStatusLogRowCells, { props: { log, ...props } });
}

function noteText(note: string | null): string {
    return mountCells(cellLog({ note })).findAll('td')[4].text();
}

describe('CellStatusLogRowCells', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock });
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

    describe('warehouse name', () => {
        const main = { id: 1, name: 'Main' };
        const cold = { id: 2, name: 'Cold store' };
        const transfer = (from: typeof main, to: typeof main) =>
            cellLog({
                action: 'transferred_out',
                cell: {
                    row_letter: 'A',
                    cell_number: 3,
                    flat_number: 2,
                    warehouse: from,
                },
                related_cell: {
                    row_letter: 'B',
                    cell_number: 1,
                    flat_number: 4,
                    warehouse: to,
                },
            });
        const names = (wrapper: ReturnType<typeof mountCells>) =>
            wrapper
                .findAll('[data-testid="cell-warehouse"]')
                .map((node) => node.text());

        it('hides the warehouse in a single-warehouse system', () => {
            expect(names(mountCells(transfer(main, cold)))).toEqual([]);
            expect(
                names(
                    mountCells(transfer(main, cold), {
                        multipleWarehouses: false,
                    }),
                ),
            ).toEqual([]);
        });

        it('names the warehouse under the slot of a normal row', () => {
            const wrapper = mountCells(
                cellLog({
                    cell: {
                        row_letter: 'A',
                        cell_number: 3,
                        flat_number: 2,
                        warehouse: main,
                    },
                }),
                { multipleWarehouses: true },
            );

            expect(names(wrapper)).toEqual(['Main']);
        });

        it('names both warehouses of a cross-warehouse transfer, source first', () => {
            const wrapper = mountCells(transfer(main, cold), {
                multipleWarehouses: true,
            });

            expect(names(wrapper)).toEqual(['Main', 'Cold store']);
        });

        it('names the warehouse on both sides of a same-warehouse transfer', () => {
            const wrapper = mountCells(transfer(main, main), {
                multipleWarehouses: true,
            });

            expect(names(wrapper)).toEqual(['Main', 'Main']);
        });

        it('puts the origin first for a transferred_in entry', () => {
            const log = transfer(cold, main);
            const wrapper = mountCells(
                { ...log, action: 'transferred_in' },
                { multipleWarehouses: true },
            );

            expect(names(wrapper)).toEqual(['Main', 'Cold store']);
        });

        it('shows nothing when the payload carries no warehouse', () => {
            const wrapper = mountCells(cellLog(), {
                multipleWarehouses: true,
            });

            expect(names(wrapper)).toEqual([]);
        });
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
            acknowledgeFlags.url({ cellStatusLog: 42 }),
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
            acknowledgeFlags.url({ cellStatusLog: 42 }),
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
                    active: true,
                },
            }),
        );

        const productCell = wrapper.findAll('td')[2];
        expect(productCell.text()).toContain('Widgets');
        expect(productCell.get('img').attributes('src')).toBe(
            '/img/widgets.png',
        );
    });

    it("marks whether the log's product is still sold", () => {
        const product = cellLog().product!;
        const active = mountCells(cellLog());
        const inactive = mountCells(
            cellLog({ product: { ...product, active: false } }),
        );

        expect(
            active
                .findAll('td')[2]
                .getComponent(ProductActiveBadge)
                .props('active'),
        ).toBe(true);
        expect(active.findAll('td')[2].text()).toContain(
            t('products.status.active'),
        );
        expect(
            inactive
                .findAll('td')[2]
                .getComponent(ProductActiveBadge)
                .props('active'),
        ).toBe(false);
        expect(inactive.findAll('td')[2].text()).toContain(
            t('products.status.inactive'),
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
                    active: true,
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
                    active: true,
                },
            }),
        );

        const productCell = wrapper.findAll('td')[2];
        expect(productCell.text()).toContain('Widgets');
        expect(productCell.get('img').attributes('alt')).toBe('Widgets');
    });

    describe('record corrections', () => {
        const noteChange = (overrides: Partial<CellStatusLog> = {}) =>
            cellLog({
                action: 'note_changed',
                batch_id: '0b5e1d6e-2f43-4a7c-9a39-2f0c3f8f9d10',
                old_note: 'Olive oil 5L',
                new_note: 'Olive oil 5L, Brand X',
                ...overrides,
            });

        it('says the pallet has no cell yet instead of a slot link', () => {
            const wrapper = mountCells(
                noteChange({ cell: null, from_state: null, to_state: null }),
            );
            const cellColumn = wrapper.findAll('td')[0];

            expect(
                cellColumn.get('[data-testid="cell-log-no-cell"]').text(),
            ).toBe(t('cellLog.columns.noCell'));
            expect(cellColumn.findComponent(TableLink).exists()).toBe(false);
        });

        it('shows no state transition when the entry has no states', () => {
            const wrapper = mountCells(
                noteChange({ cell: null, from_state: null, to_state: null }),
            );
            const actionCell = wrapper.findAll('td')[1];

            expect(actionCell.text()).toBe(cellLogActionLabel('note_changed'));
            expect(actionCell.text()).not.toContain('→');
        });

        it('labels the note_changed action', () => {
            expect(cellLogActionLabel('note_changed')).toBe(
                t('cellLog.actions.note_changed'),
            );
            expect(t('cellLog.actions.note_changed')).not.toBe(
                'cellLog.actions.note_changed',
            );
        });

        it('shows the old and new pallet note', () => {
            const cell = mountCells(noteChange()).get(
                '[data-testid="cell-log-correction"]',
            );

            expect(cell.text()).toContain(t('cellLog.correction.palletNote'));
            expect(cell.text()).toContain('Olive oil 5L');
            expect(cell.text()).toContain('Olive oil 5L, Brand X');
        });

        it('names a cleared note as none', () => {
            const cell = mountCells(noteChange({ new_note: null })).get(
                '[data-testid="cell-log-correction"]',
            );

            expect(cell.text()).toContain(t('cellLog.correction.noNote'));
        });

        it('shows the reason and the previous product of a product change', () => {
            const wrapper = mountCells(
                cellLog({
                    action: 'product_changed',
                    reason: 'Arrived as DIVERSEN',
                    old_product: {
                        id: 101,
                        name: 'DIVERSEN',
                        ar_name: 'متنوع',
                    },
                    new_product: { id: 10, name: 'Widgets', ar_name: 'ودجات' },
                    old_note: 'Olive oil 5L',
                    new_note: null,
                }),
            );

            expect(
                wrapper.get('[data-testid="cell-log-old-product"]').text(),
            ).toBe(`${t('cellLog.correction.wasProduct')}: DIVERSEN`);
            const correction = wrapper.get(
                '[data-testid="cell-log-correction"]',
            );
            expect(correction.text()).toContain(
                `${t('cellLog.correction.reason')}: Arrived as DIVERSEN`,
            );
            expect(correction.text()).toContain('Olive oil 5L');
        });

        it('leaves the note line out of a product change that touched no note', () => {
            const correction = mountCells(
                cellLog({ action: 'product_changed', note: 'Fixed by admin' }),
            ).get('[data-testid="cell-log-correction"]');

            expect(correction.text()).toBe('Fixed by admin');
        });

        it('shows no previous product on other actions', () => {
            expect(
                mountCells(cellLog())
                    .find('[data-testid="cell-log-old-product"]')
                    .exists(),
            ).toBe(false);
        });
    });
});

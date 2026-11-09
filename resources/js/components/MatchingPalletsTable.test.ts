import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { show as showReceipt } from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { show as showRow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { t } from '@/lib/i18n';
import { matchingPallet, unplacedMatchingPallet } from '@/testing/factories';
import MatchingPalletsTable from './MatchingPalletsTable.vue';
import TableLink from './TableLink.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

function mountTable(pallets = [matchingPallet()]) {
    return mount(MatchingPalletsTable, {
        props: { pallets, emptyMessage: 'Nothing' },
    });
}

describe('MatchingPalletsTable', () => {
    it('links a placed pallet to its row and shows its cell state, with no unplaced badge', () => {
        const wrapper = mountTable();

        expect(wrapper.find('[data-testid="unplaced-badge"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain('A3·2');
        expect(wrapper.getComponent(TableLink).props('href')).toEqual(
            showRow(
                { letter: 'A' },
                { query: { cell_number: 3, flat_number: 2 } },
            ),
        );
    });

    it('shows an unplaced pallet with an Unplaced badge, its warehouse and a link to its receipt', () => {
        const wrapper = mountTable([unplacedMatchingPallet()]);

        expect(wrapper.get('[data-testid="unplaced-badge"]').text()).toBe(
            t('pallets.unplacedBadge'),
        );
        expect(wrapper.get('[data-testid="unplaced-warehouse"]').text()).toBe(
            'North',
        );
        expect(
            wrapper.getComponent('[data-testid="unplaced-receipt-link"]'),
        ).toBeDefined();
        expect(wrapper.getComponent(TableLink).props('href')).toEqual(
            showReceipt(12),
        );
        expect(wrapper.text()).toContain(t('pallets.receiptLink', { id: 12 }));
    });

    it('links an unplaced pallet to nothing but its receipt, and shows no inactive-cell badge', () => {
        const wrapper = mountTable([unplacedMatchingPallet()]);

        expect(wrapper.findAllComponents(TableLink)).toHaveLength(1);
        expect(
            wrapper.find('[data-testid="cell-inactive-badge"]').exists(),
        ).toBe(false);
    });

    it('shows a dash for an unplaced pallet that names neither a warehouse nor a receipt', () => {
        const wrapper = mountTable([
            unplacedMatchingPallet({ warehouse: null, receipt_id: null }),
        ]);

        expect(wrapper.findAllComponents(TableLink)).toHaveLength(0);
        expect(
            wrapper.find('[data-testid="unplaced-warehouse"]').exists(),
        ).toBe(false);
    });

    it('renders placed and unplaced rows side by side', () => {
        const wrapper = mountTable([
            matchingPallet({ id: 1 }),
            unplacedMatchingPallet({ id: 2 }),
        ]);

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.findAll('[data-testid="unplaced-badge"]')).toHaveLength(
            1,
        );
    });
});

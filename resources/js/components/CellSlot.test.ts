import { CircleDashed, Inbox, PackageOpen } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';
import CellSlot from './CellSlot.vue';

function cell(overrides: Partial<Cell> = {}): Cell {
    return {
        id: 1,
        cell_number: 1,
        flat_number: 1,
        state: 'empty',
        pallet: null,
        ...overrides,
    };
}

function mountSlot(
    props: Partial<InstanceType<typeof CellSlot>['$props']> = {},
) {
    return mount(CellSlot, {
        props: {
            cell: cell(),
            label: 'A1·1',
            highlighted: false,
            ...props,
        },
    });
}

describe('CellSlot', () => {
    it('renders the given label', () => {
        const wrapper = mountSlot({ label: 'B3·2' });

        expect(wrapper.text()).toContain('B3·2');
    });

    it('shows the empty label with dashed styling when there is no cell record', () => {
        const wrapper = mountSlot({ cell: null });

        expect(wrapper.text()).toContain(t('rows.show.empty'));
        expect(wrapper.classes()).toContain('border-dashed');
    });

    it('shows the empty label without dashed styling for an existing empty cell', () => {
        const wrapper = mountSlot({ cell: cell({ state: 'empty' }) });

        expect(wrapper.text()).toContain(t('rows.show.empty'));
        expect(wrapper.classes()).not.toContain('border-dashed');
    });

    it('applies a distinct background and border class per cell state', () => {
        const full = mountSlot({ cell: cell({ state: 'full' }) });
        expect(full.classes()).toContain('bg-green-50');
        expect(full.classes()).toContain('border-green-500');

        const opened = mountSlot({ cell: cell({ state: 'opened' }) });
        expect(opened.classes()).toContain('bg-orange-50');
        expect(opened.classes()).toContain('border-orange-500');

        const empty = mountSlot({ cell: cell({ state: 'empty' }) });
        expect(empty.classes()).toContain('bg-gray-100');
        expect(empty.classes()).toContain('border-gray-400');
    });

    it('shows a distinct icon per cell state', () => {
        expect(
            mountSlot({
                cell: cell({ state: 'full' }),
            })
                .findComponent(Inbox)
                .exists(),
        ).toBe(true);
        expect(
            mountSlot({
                cell: cell({ state: 'opened' }),
            })
                .findComponent(PackageOpen)
                .exists(),
        ).toBe(true);
        expect(
            mountSlot({
                cell: cell({ state: 'empty' }),
            })
                .findComponent(CircleDashed)
                .exists(),
        ).toBe(true);
        expect(
            mountSlot({ cell: null }).findComponent(CircleDashed).exists(),
        ).toBe(false);
    });

    it('shows pallet details when the cell holds one', () => {
        const wrapper = mountSlot({
            cell: cell({
                state: 'full',
                pallet: {
                    id: 9,
                    product_id: 1,
                    product_name: 'Widgets',
                    product_image_url: '/img/widgets.png',
                    expiration_date: '2026-09-01',
                    added_at: '2026-08-01T10:00:00Z',
                    is_stale: null,
                },
            }),
        });

        expect(wrapper.text()).toContain('Widgets');
        expect(wrapper.text()).toContain(formatDate('2026-09-01'));
        expect(wrapper.text()).toContain(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
        const img = wrapper.get('img');
        expect(img.attributes('src')).toBe('/img/widgets.png');
    });

    it('does not draw an expiry border or badge for an expired pallet, absent a highlight filter', () => {
        const expired = mountSlot({
            cell: cell({
                state: 'full',
                pallet: {
                    id: 1,
                    product_id: 1,
                    product_name: 'Widgets',
                    product_image_url: null,
                    expiration_date: '2026-08-01',
                    added_at: '2026-07-01T10:00:00Z',
                    is_stale: null,
                },
            }),
        });
        expect(expired.classes()).not.toContain('border-red-500');
        expect(expired.find('[data-testid="expiry-badge"]').exists()).toBe(
            false,
        );
    });

    it('draws a highlight ring only when told to', () => {
        expect(mountSlot({ highlighted: false }).classes()).not.toContain(
            'ring-blue-500',
        );
        expect(mountSlot({ highlighted: true }).classes()).toContain(
            'ring-blue-500',
        );
    });
});

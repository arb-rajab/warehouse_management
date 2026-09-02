import { CircleDashed, Inbox, PackageOpen } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { cell } from '@/testing/factories';
import CellSlot from './CellSlot.vue';

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

    it('always shows the QR reprint link for an existing cell, not just on hover, since touch devices have no hover state', () => {
        const wrapper = mountSlot({ cell: cell({ id: 7 }) });

        const link = wrapper.find(
            'a[title="' + t('rows.show.reprintQr') + '"]',
        );
        expect(link.exists()).toBe(true);
        expect(link.classes()).not.toContain('opacity-0');
        expect(link.attributes('href')).toContain('/7/');
    });

    it('does not show a QR reprint link when there is no cell record', () => {
        const wrapper = mountSlot({ cell: null });

        expect(
            wrapper
                .find('a[title="' + t('rows.show.reprintQr') + '"]')
                .exists(),
        ).toBe(false);
    });

    it('marks an inactive cell with a red border, dimmed opacity, and an inactive badge, regardless of its occupancy', () => {
        const inactiveFull = mountSlot({
            cell: cell({ state: 'full', is_active: false }),
        });
        expect(inactiveFull.classes()).toContain('border-red-500');
        expect(inactiveFull.classes()).toContain('opacity-60');
        expect(
            inactiveFull.find(`[title="${t('cells.inactiveBadge')}"]`).exists(),
        ).toBe(true);

        const active = mountSlot({ cell: cell({ state: 'full' }) });
        expect(active.classes()).not.toContain('border-red-500');
        expect(active.classes()).not.toContain('opacity-60');
        expect(
            active.find(`[title="${t('cells.inactiveBadge')}"]`).exists(),
        ).toBe(false);
    });

    it('does not show a toggle-active button unless toggleable is set', () => {
        const wrapper = mountSlot({ cell: cell() });

        expect(
            wrapper
                .find(`[title="${t('cells.toggleActive.deactivateLabel')}"]`)
                .exists(),
        ).toBe(false);
    });

    it('shows a toggle-active button with a label matching the cells current status when toggleable', () => {
        const active = mountSlot({
            cell: cell({ is_active: true }),
            toggleable: true,
        });
        expect(
            active
                .find(`[title="${t('cells.toggleActive.deactivateLabel')}"]`)
                .exists(),
        ).toBe(true);

        const inactive = mountSlot({
            cell: cell({ is_active: false }),
            toggleable: true,
        });
        expect(
            inactive
                .find(`[title="${t('cells.toggleActive.reactivateLabel')}"]`)
                .exists(),
        ).toBe(true);
    });

    it('emits toggle-active with the cell when the toggle button is clicked', async () => {
        const targetCell = cell({ id: 42, is_active: true });
        const wrapper = mountSlot({ cell: targetCell, toggleable: true });

        await wrapper
            .find(`[title="${t('cells.toggleActive.deactivateLabel')}"]`)
            .trigger('click');

        expect(wrapper.emitted('toggle-active')).toEqual([[targetCell]]);
    });
});

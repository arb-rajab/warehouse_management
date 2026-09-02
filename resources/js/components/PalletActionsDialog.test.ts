import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CellMapRow, CellWithLocation } from '@/types/admin';
import PalletActionsDialog from './PalletActionsDialog.vue';

const { routerPostMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock },
}));

const rows: CellMapRow[] = [
    { id: 1, letter: 'A', cells_count: 5, flats_count: 1 },
];

function openedCell(remainingBoxes: number): CellWithLocation {
    return {
        id: 7,
        row_letter: 'A',
        cell_number: 2,
        flat_number: 1,
        state: 'opened',
        is_active: true,
        pallet: {
            id: 42,
            product_id: 1,
            product_name: 'Widgets',
            product_image_url: null,
            expiration_date: '2026-12-01',
            added_at: '2026-01-01',
            is_stale: false,
            remaining_boxes: remainingBoxes,
        },
    };
}

function mountDialog(cell: CellWithLocation | null) {
    return mount(PalletActionsDialog, {
        props: {
            cell,
            label: 'A2·1',
            rows,
            open: true,
            'onUpdate:open': () => {},
        },
    });
}

describe('PalletActionsDialog', () => {
    beforeEach(() => {
        routerPostMock.mockReset();
    });

    it('does not offer the confirm-empty checkbox while boxes_count is below remaining_boxes', async () => {
        const wrapper = mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
    });

    it('offers the confirm-empty checkbox once boxes_count equals remaining_boxes', async () => {
        const wrapper = mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
    });

    it('offers the confirm-empty checkbox once boxes_count exceeds remaining_boxes', async () => {
        const wrapper = mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('9');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
    });

    it('posts confirm_empty false when removing fewer boxes than remain', async () => {
        const wrapper = mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            boxes_count: '4',
            confirm_empty: false,
        });
    });

    it('posts confirm_empty true when the checkbox is checked for an exact boxes_count match', async () => {
        const wrapper = mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            boxes_count: '6',
            confirm_empty: true,
        });
    });

    it('resets the checked confirm_empty state when boxes_count is edited back down', async () => {
        const wrapper = mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');
        await wrapper.get('input[type="checkbox"]').setValue(true);

        await wrapper.get('#pallet-action-boxes-count').setValue('3');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
    });
});

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CellMapRow, CellWithLocation } from '@/types/admin';
import PalletActionsDialog from './PalletActionsDialog.vue';
import SubmitButton from './SubmitButton.vue';

const { routerPostMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock },
    useHttp: () => ({
        get: (
            _url: string,
            options?: { onSuccess?: (response: unknown) => void },
        ) =>
            options?.onSuccess?.({
                data: [],
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 0,
                    from: null,
                    to: 0,
                    links: [],
                },
            }),
    }),
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

function emptyCell(): CellWithLocation {
    return {
        id: 8,
        row_letter: 'A',
        cell_number: 3,
        flat_number: 1,
        state: 'empty',
        is_active: true,
        pallet: null,
    };
}

function fullCell(remainingBoxes: number): CellWithLocation {
    return {
        id: 9,
        row_letter: 'A',
        cell_number: 4,
        flat_number: 1,
        state: 'full',
        is_active: true,
        pallet: {
            id: 43,
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

/**
 * Mounts closed and transitions to open, matching how the dialog is always
 * used in the real app (Cells/Index.vue, Rows/Show.vue start it at
 * `open: false` and flip it to `true` later) — `selectedAction`'s default
 * is only set by the `watch(open, ...)` handler, which needs a false→true
 * transition to fire (see FilterDialog.test.ts for the same pattern).
 */
async function mountDialog(cell: CellWithLocation | null) {
    const wrapper = mount(PalletActionsDialog, {
        props: {
            cell,
            label: 'A2·1',
            rows,
            open: false,
            'onUpdate:open': () => {},
        },
    });

    await wrapper.setProps({ open: true });

    return wrapper;
}

describe('PalletActionsDialog', () => {
    beforeEach(() => {
        routerPostMock.mockReset();
    });

    it('does not offer the confirm-empty checkbox while boxes_count is below remaining_boxes', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
    });

    it('offers the confirm-empty checkbox once boxes_count equals remaining_boxes', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
    });

    it('offers the confirm-empty checkbox once boxes_count exceeds remaining_boxes', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('9');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true);
    });

    it('posts confirm_empty false when removing fewer boxes than remain', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        // The static type="number" input auto-casts through v-model (see
        // js.md's FilterNumberField note) — boxes_count posts as a number
        // despite boxesCount being declared as a string ref.
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            boxes_count: 4,
            confirm_empty: false,
        });
    });

    it('posts confirm_empty true when the checkbox is checked for an exact boxes_count match', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            boxes_count: 6,
            confirm_empty: true,
        });
    });

    it('resets the checked confirm_empty state when boxes_count is edited back down', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');
        await wrapper.get('input[type="checkbox"]').setValue(true);

        await wrapper.get('#pallet-action-boxes-count').setValue('3');

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
    });

    it('offers only the store tab for an empty cell, and posts the product/expiration/note fields on submit', async () => {
        const wrapper = await mountDialog(emptyCell());

        expect(
            wrapper.findAll('[data-testid="pallet-action-tab"]'),
        ).toHaveLength(0);
        expect(wrapper.find('#pallet-action-expiration').exists()).toBe(true);

        await wrapper.get('#pallet-action-expiration').setValue('2026-12-25');
        await wrapper.get('#pallet-action-note').setValue('Fragile');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            product_id: null,
            expiration_date: '2026-12-25',
            note: 'Fragile',
            return_to: null,
        });
    });

    it('defaults to the open tab for a full cell and posts boxes_count/confirm_empty on submit', async () => {
        const wrapper = await mountDialog(fullCell(6));

        const tabs = wrapper.findAll('[data-testid="pallet-action-tab"]');
        expect(tabs).toHaveLength(3);
        expect(tabs[0].attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('#pallet-action-boxes-count').exists()).toBe(true);

        await wrapper.get('#pallet-action-boxes-count').setValue('6');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            boxes_count: 6,
            confirm_empty: true,
        });
    });

    it('switches tabs and resets the fields entered on the previously selected tab', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');
        expect(
            (
                wrapper.get('#pallet-action-boxes-count')
                    .element as HTMLInputElement
            ).value,
        ).toBe('4');

        const tabs = wrapper.findAll('[data-testid="pallet-action-tab"]');
        await tabs[1].trigger('click');
        expect(tabs[1].attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('#pallet-action-boxes-count').exists()).toBe(false);

        await tabs[0].trigger('click');
        expect(wrapper.find('#pallet-action-boxes-count').exists()).toBe(true);
        expect(
            (
                wrapper.get('#pallet-action-boxes-count')
                    .element as HTMLInputElement
            ).value,
        ).toBe('');
    });

    it('submits the empty action with only a note, for a full cell', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[1]
            .trigger('click');
        await wrapper.get('#pallet-action-note').setValue('Damaged boxes');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toEqual({
            note: 'Damaged boxes',
            return_to: null,
        });
    });

    it('submits the transfer action with the chosen destination row/cell/flat', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[2]
            .trigger('click');

        await wrapper.get('#pallet-action-to-row').setValue('A');
        await wrapper.get('#pallet-action-to-cell').setValue('3');
        await wrapper.get('#pallet-action-to-flat').setValue('2');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            row_letter: 'A',
            cell_number: 3,
            flat_number: 2,
        });
    });

    it('closes the dialog on success and stops the submit button processing state on finish', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );

        const options = routerPostMock.mock.calls[0][2] as {
            onSuccess: () => void;
            onFinish: () => void;
        };
        options.onSuccess();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);

        options.onFinish();
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            false,
        );
    });
});

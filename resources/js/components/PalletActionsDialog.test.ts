import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CellMapRow, CellWithLocation } from '@/types/admin';
import PalletActionsDialog from './PalletActionsDialog.vue';
import SubmitButton from './SubmitButton.vue';

const { routerPostMock, routerPutMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
    routerPutMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock, put: routerPutMock },
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
            product_ar_name: 'ودجات',
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
            product_ar_name: 'ودجات',
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
        routerPutMock.mockReset();
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

    it('shows the remove-boxes submit label by default for an opened cell', async () => {
        const wrapper = await mountDialog(openedCell(6));

        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            'Remove boxes',
        );
    });

    it('renders the dialog title interpolated with the cell location', async () => {
        const wrapper = await mountDialog(emptyCell());

        expect(wrapper.get('h2').text()).toBe('Manage pallet — A2·1');
    });

    it('offers only the store tab for an empty cell, and posts the product/expiration/note fields on submit', async () => {
        const wrapper = await mountDialog(emptyCell());

        expect(
            wrapper.findAll('[data-testid="pallet-action-tab"]'),
        ).toHaveLength(0);
        expect(wrapper.find('#pallet-action-expiration').exists()).toBe(true);
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            'Store pallet',
        );

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

    it('does not require an expiration date to store a pallet', async () => {
        const wrapper = await mountDialog(emptyCell());

        expect(
            wrapper.get('#pallet-action-expiration').attributes('required'),
        ).toBeUndefined();

        await wrapper.get('#pallet-action-note').setValue('No label on box');
        await wrapper.get('form').trigger('submit');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][1]).toMatchObject({
            product_id: null,
            expiration_date: '',
            note: 'No label on box',
        });
    });

    it('requires a product to be selected to store a pallet', async () => {
        const wrapper = await mountDialog(emptyCell());

        const guard = wrapper.get('#pallet-action-product-guard')
            .element as HTMLInputElement;
        expect(guard.required).toBe(true);
    });

    it('requires a product to be selected to edit a pallet', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[3]
            .trigger('click');

        const guard = wrapper.get('#pallet-action-edit-product-guard')
            .element as HTMLInputElement;
        expect(guard.required).toBe(true);
    });

    it('shows a pointer cursor on every action tab, selected or not', async () => {
        const wrapper = await mountDialog(fullCell(6));

        const tabs = wrapper.findAll('[data-testid="pallet-action-tab"]');
        expect(tabs.length).toBeGreaterThan(1);
        for (const tab of tabs) {
            expect(tab.classes()).toContain('cursor-pointer');
        }
    });

    it('defaults to the open tab for a full cell and posts boxes_count/confirm_empty on submit', async () => {
        const wrapper = await mountDialog(fullCell(6));

        const tabs = wrapper.findAll('[data-testid="pallet-action-tab"]');
        expect(tabs).toHaveLength(4);
        expect(tabs[0].attributes('aria-pressed')).toBe('true');
        expect(wrapper.find('#pallet-action-boxes-count').exists()).toBe(true);
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            'Open pallet',
        );

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
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            'Empty pallet',
        );
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

        expect(wrapper.get('label[for="pallet-action-to-row"]').text()).toBe(
            'Destination row',
        );
        expect(wrapper.get('label[for="pallet-action-to-cell"]').text()).toBe(
            'Destination cell number',
        );
        expect(wrapper.get('label[for="pallet-action-to-flat"]').text()).toBe(
            'Destination level',
        );
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            'Transfer pallet',
        );

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

    it("prefills the edit tab with the pallet's current product, expiration date, and remaining boxes, and hides the note field", async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[3]
            .trigger('click');

        expect(
            (
                wrapper.get('#pallet-action-edit-expiration')
                    .element as HTMLInputElement
            ).value,
        ).toBe('2026-12-01');
        expect(
            (
                wrapper.get('#pallet-action-edit-boxes')
                    .element as HTMLInputElement
            ).value,
        ).toBe('6');
        expect(wrapper.find('#pallet-action-note').exists()).toBe(false);
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            'Save changes',
        );
    });

    it('submits the edit action via PUT with the updated product/expiration/remaining boxes and no note', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[3]
            .trigger('click');

        await wrapper
            .get('#pallet-action-edit-expiration')
            .setValue('2027-01-01');
        await wrapper.get('#pallet-action-edit-boxes').setValue('4');
        await wrapper.get('form').trigger('submit');

        expect(routerPutMock).toHaveBeenCalledTimes(1);
        expect(routerPutMock.mock.calls[0][1]).toEqual({
            product_id: 1,
            expiration_date: '2027-01-01',
            remaining_boxes: 4,
            confirm_empty: false,
            return_to: null,
        });
    });

    it('hides the confirm_empty checkbox on the edit tab while remaining boxes is above zero', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[3]
            .trigger('click');

        expect(wrapper.find('#pallet-action-edit-boxes').exists()).toBe(true);
        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
    });

    it('requires confirm_empty when editing remaining boxes down to zero, and posts it once checked', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[3]
            .trigger('click');

        await wrapper.get('#pallet-action-edit-boxes').setValue('0');

        const checkbox = wrapper.get('input[type="checkbox"]');
        await checkbox.setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerPutMock).toHaveBeenCalledTimes(1);
        expect(routerPutMock.mock.calls[0][1]).toMatchObject({
            remaining_boxes: 0,
            confirm_empty: true,
        });
    });

    it('allows clearing the expiration date on the edit tab', async () => {
        const wrapper = await mountDialog(fullCell(6));

        await wrapper
            .findAll('[data-testid="pallet-action-tab"]')[3]
            .trigger('click');

        expect(
            wrapper
                .get('#pallet-action-edit-expiration')
                .attributes('required'),
        ).toBeUndefined();

        await wrapper.get('#pallet-action-edit-expiration').setValue('');
        await wrapper.get('form').trigger('submit');

        expect(routerPutMock).toHaveBeenCalledTimes(1);
        expect(routerPutMock.mock.calls[0][1]).toMatchObject({
            expiration_date: '',
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

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    empty as emptyPallet,
    open as openPallet,
    removeBoxes,
    store,
    transfer,
    update as updatePallet,
} from '@/actions/App/Http/Controllers/Admin/PalletController';
import FilterDialog from '@/components/FilterDialog.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { cellPallet, cellWithLocation } from '@/testing/factories';
import type { CellMapRow, CellWithLocation } from '@/types/admin';
import ActionErrorBanner from './ActionErrorBanner.vue';
import PalletActionsDialog from './PalletActionsDialog.vue';
import SubmitButton from './SubmitButton.vue';

const { submitMock, formState } = vi.hoisted(() => ({
    submitMock: vi.fn(),
    formState: {
        processing: false,
        errors: {} as Record<string, string>,
    },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createUseFormStub } = await import('@/testing/inertiaStubs');
    const { paginated } = await import('@/testing/factories');

    return {
        useForm: createUseFormStub(submitMock, () => formState),
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) => options?.onSuccess?.(paginated([])),
        }),
    };
});

const rows: CellMapRow[] = [
    { id: 1, letter: 'A', cells_count: 5, flats_count: 1 },
];

function openedCell(remainingBoxes: number): CellWithLocation {
    return cellWithLocation({
        id: 7,
        cell_number: 2,
        state: 'opened',
        pallet: cellPallet({ remaining_boxes: remainingBoxes }),
    });
}

function emptyCell(): CellWithLocation {
    return cellWithLocation({ id: 8, cell_number: 3 });
}

function fullCell(remainingBoxes: number): CellWithLocation {
    return cellWithLocation({
        id: 9,
        cell_number: 4,
        state: 'full',
        pallet: cellPallet({ id: 43, remaining_boxes: remainingBoxes }),
    });
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
        submitMock.mockReset();
        formState.processing = false;
        formState.errors = {};
    });

    it('labels its close button with the shared action-dialog close text', async () => {
        const wrapper = await mountDialog(openedCell(6));

        expect(t('common.close')).toBe('Close');
        expect(wrapper.findComponent(FilterDialog).props('closeLabel')).toBe(
            t('common.close'),
        );
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        // The static type="number" input auto-casts through v-model (see
        // js.md's FilterNumberField note) — boxes_count posts as a number
        // despite boxesCount being declared as a string ref.
        expect(submitMock.mock.calls[0][1]).toMatchObject({
            boxes_count: 4,
            confirm_empty: false,
        });
    });

    it('posts confirm_empty true when the checkbox is checked for an exact boxes_count match', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('6');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
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

    it('shows the pallet details when the cell holds one', async () => {
        const wrapper = await mountDialog(fullCell(6));

        const details = wrapper.get('[data-testid="pallet-action-details"]');
        expect(details.text()).toContain('Widgets');
        expect(details.text()).toContain('6 boxes remaining');
        expect(details.text()).toContain(formatDate('2026-12-01'));
        expect(details.text()).toContain(formatDateTime('2026-01-01'));
    });

    it('does not show pallet details for an empty cell', async () => {
        const wrapper = await mountDialog(emptyCell());

        expect(
            wrapper.find('[data-testid="pallet-action-details"]').exists(),
        ).toBe(false);
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
            boxes_count: 6,
            confirm_empty: true,
        });
    });

    it('does not require a boxes count to open a pallet, and posts null when left blank', async () => {
        const wrapper = await mountDialog(fullCell(6));

        expect(
            wrapper.get('#pallet-action-boxes-count').attributes('required'),
        ).toBeUndefined();

        await wrapper.get('form').trigger('submit');

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
            boxes_count: null,
        });
    });

    it('requires a boxes count to remove boxes from an opened pallet', async () => {
        const wrapper = await mountDialog(openedCell(6));

        expect(
            wrapper.get('#pallet-action-boxes-count').attributes('required'),
        ).toBeDefined();
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toEqual({
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toEqual({
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
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

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][1]).toMatchObject({
            expiration_date: '',
        });
    });

    it('closes the dialog when the request succeeds, preserving scroll', async () => {
        const wrapper = await mountDialog(openedCell(6));

        await wrapper.get('#pallet-action-boxes-count').setValue('4');
        await wrapper.get('form').trigger('submit');

        const options = submitMock.mock.calls[0][2] as {
            preserveScroll: boolean;
            onSuccess: () => void;
        };
        expect(options.preserveScroll).toBe(true);
        expect(wrapper.emitted('update:open')).toBeUndefined();

        options.onSuccess();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('passes the form’s processing state to the submit button', async () => {
        formState.processing = true;
        const wrapper = await mountDialog(openedCell(6));

        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });

    it('shows the form’s first server error in a banner', async () => {
        formState.errors = { boxes_count: 'Too many boxes' };
        const wrapper = await mountDialog(openedCell(6));

        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            'Too many boxes',
        );
    });

    it('posts each action to its own route with its own method', async () => {
        const full = fullCell(6);
        const expected: [number, unknown][] = [
            [0, openPallet(43, { mergeQuery: {} })],
            [1, emptyPallet(43, { mergeQuery: {} })],
            [2, transfer(43, { mergeQuery: {} })],
            [3, updatePallet(43, { mergeQuery: {} })],
        ];

        for (const [tabIndex, action] of expected) {
            submitMock.mockReset();
            const wrapper = await mountDialog(full);
            await wrapper
                .findAll('[data-testid="pallet-action-tab"]')
                [tabIndex].trigger('click');
            await wrapper.get('form').trigger('submit');

            expect(submitMock.mock.calls[0][0]).toEqual(action);
        }

        submitMock.mockReset();
        const storeWrapper = await mountDialog(emptyCell());
        await storeWrapper.get('form').trigger('submit');
        expect(submitMock.mock.calls[0][0]).toEqual(
            store(8, { mergeQuery: {} }),
        );

        submitMock.mockReset();
        const removeWrapper = await mountDialog(openedCell(6));
        await removeWrapper.get('form').trigger('submit');
        expect(submitMock.mock.calls[0][0]).toEqual(
            removeBoxes(42, { mergeQuery: {} }),
        );
    });

    it('sends return_to when the dialog was opened from a row page', async () => {
        const wrapper = mount(PalletActionsDialog, {
            props: {
                cell: emptyCell(),
                label: 'A2·1',
                rows,
                returnTo: 'row',
                open: false,
                'onUpdate:open': () => {},
            },
        });
        await wrapper.setProps({ open: true });
        await wrapper.get('form').trigger('submit');

        expect(submitMock.mock.calls[0][1]).toMatchObject({
            return_to: 'row',
        });
    });

    it('does not submit when there is no cell', async () => {
        const wrapper = await mountDialog(null);

        await wrapper.get('form').trigger('submit');

        expect(submitMock).not.toHaveBeenCalled();
    });
});

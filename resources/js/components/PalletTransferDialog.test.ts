import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { transfer } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { t } from '@/lib/i18n';
import type { PalletTransferTargets } from '@/types/admin';
import ActionErrorBanner from './ActionErrorBanner.vue';
import FilterDialog from './FilterDialog.vue';
import PalletTransferDialog from './PalletTransferDialog.vue';
import type { PalletTransferDialogTarget } from './PalletTransferDialog.vue';
import PalletTransferFields from './PalletTransferFields.vue';
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

    return { useForm: createUseFormStub(submitMock, () => formState) };
});

const targets: PalletTransferTargets = {
    warehouses: [
        { id: 1, name: 'Main' },
        { id: 4, name: 'Annex' },
    ],
    rows: [
        { id: 1, letter: 'A', cells_count: 5, flats_count: 1, warehouse_id: 1 },
        { id: 3, letter: 'C', cells_count: 3, flats_count: 2, warehouse_id: 4 },
    ],
};

const target: PalletTransferDialogTarget = {
    palletId: 43,
    location: 'A3·2',
    productName: 'Olive oil',
    warehouseId: 1,
};

async function mountDialog(
    props: Partial<{
        target: PalletTransferDialogTarget | null;
        targets: PalletTransferTargets | null;
        returnWarehouseIds: number[] | null;
    }> = {},
) {
    const wrapper = mount(PalletTransferDialog, {
        props: {
            target,
            targets,
            returnWarehouseIds: null,
            ...props,
            open: false,
            'onUpdate:open': () => {},
        },
    });

    await wrapper.setProps({ open: true });

    return wrapper;
}

async function fillDestination(
    wrapper: Awaited<ReturnType<typeof mountDialog>>,
): Promise<void> {
    await wrapper.get('#pallet-transfer-to-warehouse').setValue('4');
    await wrapper.get('#pallet-transfer-to-row').setValue('C');
    await wrapper.get('#pallet-transfer-to-cell').setValue('3');
    await wrapper.get('#pallet-transfer-to-flat').setValue('2');
}

describe('PalletTransferDialog', () => {
    beforeEach(() => {
        submitMock.mockReset();
        formState.processing = false;
        formState.errors = {};
    });

    it('names the pallet and its slot, and closes with the shared label', async () => {
        const wrapper = await mountDialog();
        const dialog = wrapper.getComponent(FilterDialog);

        expect(dialog.props('title')).toBe(
            t('cells.palletActions.transfer.title', { location: 'A3·2' }),
        );
        expect(dialog.props('closeLabel')).toBe(t('common.close'));
        expect(
            wrapper.get('[data-testid="pallet-transfer-product"]').text(),
        ).toBe('Olive oil');
        expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
            t('cells.palletActions.transfer.submit'),
        );
    });

    it('waits for the destinations before showing the fields', async () => {
        const wrapper = await mountDialog({ targets: null });

        expect(
            wrapper.find('[data-testid="pallet-transfer-loading"]').text(),
        ).toBe(t('cells.palletActions.transfer.loading'));
        expect(wrapper.findComponent(PalletTransferFields).exists()).toBe(
            false,
        );
        expect(
            wrapper.getComponent(SubmitButton).attributes('disabled'),
        ).toBeDefined();

        await wrapper.setProps({ targets });

        expect(wrapper.findComponent(PalletTransferFields).exists()).toBe(true);
        expect(
            (
                wrapper.get('#pallet-transfer-to-warehouse')
                    .element as HTMLSelectElement
            ).value,
        ).toBe('1');
    });

    it("starts on the pallet's own warehouse, or the first one when the map did not name it", async () => {
        const own = await mountDialog({
            target: { ...target, warehouseId: 4 },
        });

        expect(
            (
                own.get('#pallet-transfer-to-warehouse')
                    .element as HTMLSelectElement
            ).value,
        ).toBe('4');

        const unnamed = await mountDialog({
            target: { ...target, warehouseId: null },
        });

        expect(
            (
                unnamed.get('#pallet-transfer-to-warehouse')
                    .element as HTMLSelectElement
            ).value,
        ).toBe('1');
    });

    it('posts the destination in another warehouse back to the map, keeping its filter', async () => {
        const wrapper = await mountDialog({ returnWarehouseIds: [1, 4] });

        await fillDestination(wrapper);
        await wrapper.get('textarea').setValue('Moving to the annex');
        await wrapper.get('form').trigger('submit');

        expect(submitMock).toHaveBeenCalledTimes(1);

        const [action, payload, options] = submitMock.mock.calls[0];

        expect(action).toEqual(transfer(43));
        expect(payload).toMatchObject({
            warehouse_id: 4,
            row_letter: 'C',
            cell_number: 3,
            flat_number: 2,
            note: 'Moving to the annex',
            return_to: 'map',
            warehouse_ids: [1, 4],
        });
        expect(options).toMatchObject({
            preserveScroll: true,
            preserveState: true,
        });
    });

    it('sends no warehouse filter while the map shows every warehouse, and a blank note as null', async () => {
        const wrapper = await mountDialog();

        await fillDestination(wrapper);
        await wrapper.get('form').trigger('submit');

        const payload = submitMock.mock.calls[0][1];

        expect(payload).not.toHaveProperty('warehouse_ids');
        expect(payload.note).toBeNull();
    });

    it('closes and tells the page once the transfer succeeded', async () => {
        const wrapper = await mountDialog();

        await fillDestination(wrapper);
        await wrapper.get('form').trigger('submit');
        submitMock.mock.calls[0][2].onSuccess();

        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
        expect(wrapper.emitted('transferred')).toHaveLength(1);
    });

    it('holds back a transfer with a missing destination and marks the fields', async () => {
        const wrapper = await mountDialog();

        await wrapper.get('form').trigger('submit');

        expect(submitMock).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('common.formValidation.blocked'),
        );
        expect(
            Object.keys(
                wrapper
                    .getComponent(PalletTransferFields)
                    .props('errors') as Record<string, string>,
            ).sort(),
        ).toEqual(['cell_number', 'flat_number', 'row_letter']);
        expect(wrapper.get('form').attributes('novalidate')).toBeDefined();
        expect(wrapper.text()).toContain(t('common.requiredLegend'));
    });

    it('shows a refused action in the banner and a field error beside its field', async () => {
        formState.errors = {
            row_letter: 'The selected row letter is invalid.',
            action: 'The destination is not empty.',
        };
        const wrapper = await mountDialog();

        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            'The destination is not empty.',
        );
        expect(
            (
                wrapper
                    .getComponent(PalletTransferFields)
                    .props('errors') as Record<string, string>
            ).row_letter,
        ).toBe('The selected row letter is invalid.');
    });

    it('does not submit without a pallet', async () => {
        const wrapper = await mountDialog({ target: null });

        await wrapper.get('form').trigger('submit');

        expect(submitMock).not.toHaveBeenCalled();
    });
});

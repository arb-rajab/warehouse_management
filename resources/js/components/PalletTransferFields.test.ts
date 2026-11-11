import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import type { CellMapRow, WarehouseSummary } from '@/types/admin';
import PalletTransferFields from './PalletTransferFields.vue';

const warehouses: WarehouseSummary[] = [
    { id: 1, name: 'Main' },
    { id: 4, name: 'Annex' },
];

const rows: CellMapRow[] = [
    { id: 1, letter: 'B', cells_count: 5, flats_count: 1, warehouse_id: 1 },
    { id: 2, letter: 'A', cells_count: 2, flats_count: 2, warehouse_id: 1 },
    { id: 3, letter: 'A', cells_count: 3, flats_count: 1, warehouse_id: 4 },
];

function mountFields(
    props: Partial<{
        warehouses: WarehouseSummary[];
        rows: CellMapRow[];
        errors: Record<string, string>;
        warehouseId: number | null;
        rowLetter: string;
    }> = {},
) {
    const wrapper = mount(PalletTransferFields, {
        props: {
            warehouses,
            rows,
            warehouseId: 1,
            rowLetter: '',
            cellNumber: '',
            flatNumber: '',
            idPrefix: 'test-',
            ...props,
            'onUpdate:warehouseId': (value: number | null) =>
                wrapper.setProps({ warehouseId: value }),
            'onUpdate:rowLetter': (value: string) =>
                wrapper.setProps({ rowLetter: value }),
            'onUpdate:cellNumber': (value: string) =>
                wrapper.setProps({ cellNumber: value }),
            'onUpdate:flatNumber': (value: string) =>
                wrapper.setProps({ flatNumber: value }),
        },
    });

    return wrapper;
}

function rowOptions(wrapper: ReturnType<typeof mountFields>): string[] {
    return wrapper
        .findAll('#test-row option:not([disabled])')
        .map((option) => option.text());
}

describe('PalletTransferFields', () => {
    it('offers every warehouse, starting on the given one', () => {
        const wrapper = mountFields();
        const select = wrapper.get('#test-warehouse');

        expect(wrapper.get('label[for="test-warehouse"]').text()).toBe(
            t('cells.palletActions.transfer.warehouseLabel'),
        );
        expect(select.findAll('option').map((option) => option.text())).toEqual(
            ['Main', 'Annex'],
        );
        expect((select.element as HTMLSelectElement).value).toBe('1');
        expect(select.attributes('name')).toBe('warehouse_id');
    });

    it('hides the warehouse select while there is only one warehouse', () => {
        const wrapper = mountFields({ warehouses: [warehouses[0]] });

        expect(wrapper.find('#test-warehouse').exists()).toBe(false);
        expect(rowOptions(wrapper)).toEqual(['A', 'B']);
    });

    it("lists the chosen warehouse's rows and follows a change of warehouse", async () => {
        const wrapper = mountFields();

        expect(rowOptions(wrapper)).toEqual(['A', 'B']);

        await wrapper.get('#test-warehouse').setValue('4');

        expect(wrapper.emitted('update:warehouseId')?.at(-1)).toEqual([4]);
        expect(rowOptions(wrapper)).toEqual(['A']);
    });

    it('clears a row letter the new warehouse does not have, and keeps one it has', async () => {
        const kept = mountFields({ rowLetter: 'A' });

        await kept.get('#test-warehouse').setValue('4');
        expect(kept.emitted('update:rowLetter')).toBeUndefined();

        const cleared = mountFields({ rowLetter: 'B' });

        await cleared.get('#test-warehouse').setValue('4');
        expect(cleared.emitted('update:rowLetter')?.at(-1)).toEqual(['']);
    });

    it('marks the row, cell and level required, mirroring the server rules, and not the warehouse', () => {
        const wrapper = mountFields();

        for (const [id, name] of [
            ['test-row', 'row_letter'],
            ['test-cell', 'cell_number'],
            ['test-flat', 'flat_number'],
        ]) {
            const control = wrapper.get(`#${id}`);

            expect(control.attributes('required')).toBeDefined();
            expect(control.attributes('name')).toBe(name);
            expect(
                wrapper
                    .find(`label[for="${id}"] [data-required-mark]`)
                    .exists(),
            ).toBe(true);
        }

        for (const id of ['test-cell', 'test-flat']) {
            expect(wrapper.get(`#${id}`).attributes('min')).toBe('1');
            expect(wrapper.get(`#${id}`).attributes('type')).toBe('number');
        }

        expect(
            wrapper.get('#test-warehouse').attributes('required'),
        ).toBeUndefined();
        expect(
            wrapper
                .find('label[for="test-warehouse"] [data-required-mark]')
                .exists(),
        ).toBe(false);
    });

    it('shows each error under its field', () => {
        const wrapper = mountFields({
            errors: {
                warehouse_id: 'Pick a warehouse',
                row_letter: 'Pick a row',
                cell_number: 'No such slot',
                flat_number: 'Too low',
            },
        });

        expect(wrapper.text()).toContain('Pick a warehouse');
        expect(wrapper.text()).toContain('Pick a row');
        expect(wrapper.text()).toContain('No such slot');
        expect(wrapper.text()).toContain('Too low');
    });

    it('emits what is typed in the cell and level fields', async () => {
        const wrapper = mountFields();

        await wrapper.get('#test-cell').setValue('3');
        await wrapper.get('#test-flat').setValue('2');

        expect(wrapper.emitted('update:cellNumber')?.at(-1)).toEqual([3]);
        expect(wrapper.emitted('update:flatNumber')?.at(-1)).toEqual([2]);
    });
});

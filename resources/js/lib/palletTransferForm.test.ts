import { describe, expect, it } from 'vitest';
import {
    parseToken,
    readForm,
    supportedTokens,
    validateForm,
} from '@/lib/formValidation';
import {
    nonFieldErrors,
    palletTransferValidation,
    rowsInWarehouse,
} from '@/lib/palletTransferForm';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';
import type { CellMapRow } from '@/types/admin';

function transferForm(values: Record<string, string>): HTMLFormElement {
    const form = document.createElement('form');

    for (const [name, value] of Object.entries(values)) {
        const input = document.createElement('input');

        input.name = name;
        input.value = value;
        form.append(input);
    }

    return form;
}

describe('palletTransferValidation', () => {
    it('checks the destination and the note; the warehouse and the note are optional', () => {
        expect(Object.keys(palletTransferValidation.rules)).toEqual([
            'warehouse_id',
            'row_letter',
            'cell_number',
            'flat_number',
            'note',
        ]);
        expect(palletTransferValidation.rules.row_letter).toContain('required');
        expect(palletTransferValidation.rules.cell_number).toContain(
            'required',
        );
        expect(palletTransferValidation.rules.flat_number).toContain(
            'required',
        );
        expect(palletTransferValidation.rules.warehouse_id).toContain(
            'nullable',
        );
        expect(palletTransferValidation.rules.note).toContain('nullable');
    });

    it('uses only rule tokens the engine checks', () => {
        const supported: readonly string[] = supportedTokens;

        for (const tokens of Object.values(palletTransferValidation.rules)) {
            for (const token of tokens) {
                expect(supported).toContain(parseToken(token)[0]);
            }
        }
    });

    it('refuses a missing row and a cell or level below 1', () => {
        const errors = validateForm(
            readForm(
                transferForm({
                    row_letter: '',
                    cell_number: '0',
                    flat_number: '',
                }),
            ),
            palletTransferValidation,
        );

        expect(Object.keys(errors).sort()).toEqual([
            'cell_number',
            'flat_number',
            'row_letter',
        ]);
    });

    it('accepts a complete destination without a warehouse control', () => {
        const errors = validateForm(
            readForm(
                transferForm({
                    row_letter: 'B',
                    cell_number: '2',
                    flat_number: '1',
                    note: '',
                }),
            ),
            palletTransferValidation,
        );

        expect(errors).toEqual({});
    });

    it.each([
        ['en', en],
        ['ar', ar],
    ])('has the server wording of every rule in %s', (_locale, messages) => {
        const wording = messages.validation.messages as Record<string, unknown>;

        expect(wording.required).toBeTypeOf('string');
        expect(wording.string).toBeTypeOf('string');
        expect(wording.integer).toBeTypeOf('string');
        expect((wording.max as Record<string, string>).string).toBeTypeOf(
            'string',
        );
        expect((wording.min as Record<string, string>).numeric).toBeTypeOf(
            'string',
        );
    });
});

describe('rowsInWarehouse', () => {
    const rows: CellMapRow[] = [
        { id: 1, letter: 'B', cells_count: 1, flats_count: 1, warehouse_id: 1 },
        { id: 2, letter: 'A', cells_count: 1, flats_count: 1, warehouse_id: 2 },
        { id: 3, letter: 'A', cells_count: 1, flats_count: 1, warehouse_id: 1 },
    ];

    it('lists the rows of one warehouse in letter order', () => {
        expect(rowsInWarehouse(rows, 1).map((row) => row.id)).toEqual([3, 1]);
        expect(rowsInWarehouse(rows, 2).map((row) => row.id)).toEqual([2]);
    });

    it('lists every row while no warehouse is chosen', () => {
        expect(rowsInWarehouse(rows, null)).toHaveLength(3);
    });

    it('does not reorder the rows it was given', () => {
        rowsInWarehouse(rows, 1);

        expect(rows.map((row) => row.id)).toEqual([1, 2, 3]);
    });
});

describe('nonFieldErrors', () => {
    it('keeps only the errors no transfer field shows', () => {
        expect(
            nonFieldErrors({
                row_letter: 'Required',
                cell_number: 'No such slot',
                action: 'The destination is not empty',
            }),
        ).toEqual({ action: 'The destination is not empty' });
    });
});

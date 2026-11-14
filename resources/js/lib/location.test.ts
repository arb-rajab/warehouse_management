import { describe, expect, it } from 'vitest';
import {
    formatRowLetters,
    formatSlot,
    rowLinkOptions,
    rowOptionLabel,
} from './location';

describe('formatSlot', () => {
    it('joins the row letter, cell number and flat number into one label', () => {
        expect(formatSlot('A', 3, 2)).toBe('A3·2');
    });

    it('separates only the cell and flat numbers, keeping the letter flush', () => {
        expect(formatSlot('ZZ', 12, 4)).toBe('ZZ12·4');
    });
});

describe('formatRowLetters', () => {
    it('joins the row letters in the order given', () => {
        expect(
            formatRowLetters([
                { letter: 'A' },
                { letter: 'C' },
                { letter: 'ZZ' },
            ]),
        ).toBe('A, C, ZZ');
    });

    it('renders a single row without a separator', () => {
        expect(formatRowLetters([{ letter: 'B' }])).toBe('B');
    });

    it('falls back to an em dash for an empty or absent list', () => {
        expect(formatRowLetters([])).toBe('—');
        expect(formatRowLetters(undefined)).toBe('—');
    });
});

describe('rowOptionLabel', () => {
    it('is the bare letter when the payload names no warehouse', () => {
        expect(rowOptionLabel({ letter: 'A' })).toBe('A');
    });

    it('follows the letter with its warehouse when the payload names one', () => {
        expect(
            rowOptionLabel({ letter: 'A', warehouse: { name: 'Annex' } }),
        ).toBe('A - Annex');
    });
});

describe('formatRowLetters with warehouses', () => {
    it('names the warehouse of each row that carries one', () => {
        expect(
            formatRowLetters([
                { letter: 'A', warehouse: { name: 'Main' } },
                { letter: 'A', warehouse: { name: 'Annex' } },
            ]),
        ).toBe('A - Main, A - Annex');
    });
});

describe('rowLinkOptions', () => {
    it('adds warehouse_id to the other query parameters', () => {
        expect(rowLinkOptions({ id: 7 }, { cell_number: 3 })).toEqual({
            query: { cell_number: 3, warehouse_id: 7 },
        });
    });

    it('leaves the query alone when the payload names no warehouse', () => {
        expect(rowLinkOptions(undefined, { cell_number: 3 })).toEqual({
            query: { cell_number: 3 },
        });
        expect(rowLinkOptions(undefined)).toEqual({ query: {} });
    });
});

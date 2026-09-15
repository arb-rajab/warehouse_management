import { describe, expect, it } from 'vitest';
import { formatRowLetters, formatSlot } from './location';

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

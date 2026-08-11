import { describe, expect, it } from 'vitest';
import { formatSlot } from './location';

describe('formatSlot', () => {
    it('joins the row letter, cell number and flat number into one label', () => {
        expect(formatSlot('A', 3, 2)).toBe('A3·2');
    });

    it('separates only the cell and flat numbers, keeping the letter flush', () => {
        expect(formatSlot('ZZ', 12, 4)).toBe('ZZ12·4');
    });
});

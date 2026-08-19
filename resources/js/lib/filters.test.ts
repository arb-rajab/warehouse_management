import { describe, expect, it } from 'vitest';
import { columnNumberOptions, countActive } from './filters';

describe('columnNumberOptions', () => {
    it('returns 1..maxColumnNumber', () => {
        expect(columnNumberOptions(3)).toEqual([1, 2, 3]);
    });

    it('returns an empty array when maxColumnNumber is 0', () => {
        expect(columnNumberOptions(0)).toEqual([]);
    });
});

describe('countActive', () => {
    it('counts how many flags are true', () => {
        expect(countActive([true, false, true, true])).toBe(3);
    });

    it('returns 0 when no flags are true', () => {
        expect(countActive([false, false])).toBe(0);
    });

    it('returns 0 for an empty array', () => {
        expect(countActive([])).toBe(0);
    });
});

import { describe, expect, it } from 'vitest';
import { distanceBetween } from './geometry';

describe('distanceBetween', () => {
    it('computes the straight-line distance between two points', () => {
        expect(distanceBetween({ x: 0, y: 0 }, { x: 3, y: 4 })).toBe(5);
    });

    it('returns zero for coincident points', () => {
        expect(distanceBetween({ x: 10, y: -5 }, { x: 10, y: -5 })).toBe(0);
    });
});

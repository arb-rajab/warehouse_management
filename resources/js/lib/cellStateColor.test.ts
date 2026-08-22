import { describe, expect, it } from 'vitest';
import { CELL_STATE_COLOR } from './cellStateColor';

describe('CELL_STATE_COLOR', () => {
    it('defines a background class, border class, and hex color for every cell state', () => {
        const states: Array<keyof typeof CELL_STATE_COLOR> = [
            'empty',
            'full',
            'opened',
        ];

        for (const state of states) {
            const color = CELL_STATE_COLOR[state];
            expect(color.backgroundClass).toBeTruthy();
            expect(color.borderClass).toBeTruthy();
            expect(color.hex).toBeGreaterThanOrEqual(0);
            expect(color.hex).toBeLessThanOrEqual(0xffffff);
        }
    });

    it('gives every state a distinct hex color', () => {
        const hexValues = Object.values(CELL_STATE_COLOR).map((c) => c.hex);

        expect(new Set(hexValues).size).toBe(hexValues.length);
    });
});

import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { CELL_STATE_COLOR, cellStateLabel } from './cellStateColor';

describe('CELL_STATE_COLOR', () => {
    it('defines a background class, border class, hex color, and icon for every cell state', () => {
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
            expect(color.icon).toBeTruthy();
        }
    });

    it('gives every state a distinct icon', () => {
        const icons = Object.values(CELL_STATE_COLOR).map((c) => c.icon);

        expect(new Set(icons).size).toBe(icons.length);
    });

    it('gives every state a distinct hex color', () => {
        const hexValues = Object.values(CELL_STATE_COLOR).map((c) => c.hex);

        expect(new Set(hexValues).size).toBe(hexValues.length);
    });
});

describe('cellStateLabel', () => {
    it('translates the state key', () => {
        expect(cellStateLabel('full')).toBe(t('cellLog.states.full'));
    });
});

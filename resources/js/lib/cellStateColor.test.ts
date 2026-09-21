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

    it('pins the exact hex color for every cell state, so the 3D map matches the 2D map and the hand-mirrored Flutter literals', () => {
        expect(CELL_STATE_COLOR.empty.hex).toBe(0x9ca3af);
        expect(CELL_STATE_COLOR.full.hex).toBe(0x22c55e);
        expect(CELL_STATE_COLOR.opened.hex).toBe(0xf97316);
    });

    it('pins the exact background and border classes for every cell state', () => {
        expect(CELL_STATE_COLOR.empty.backgroundClass).toBe(
            'bg-gray-100 dark:bg-neutral-900',
        );
        expect(CELL_STATE_COLOR.empty.borderClass).toBe(
            'border-gray-400 dark:border-neutral-700',
        );
        expect(CELL_STATE_COLOR.full.backgroundClass).toBe(
            'bg-green-50 dark:bg-green-950',
        );
        expect(CELL_STATE_COLOR.full.borderClass).toBe(
            'border-green-500 dark:border-green-700',
        );
        expect(CELL_STATE_COLOR.opened.backgroundClass).toBe(
            'bg-orange-50 dark:bg-orange-950',
        );
        expect(CELL_STATE_COLOR.opened.borderClass).toBe(
            'border-orange-500 dark:border-orange-700',
        );
    });
});

describe('cellStateLabel', () => {
    it('translates the state key', () => {
        expect(cellStateLabel('full')).toBe(t('cellLog.states.full'));
    });
});

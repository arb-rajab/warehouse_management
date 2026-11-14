import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { CELL_STATE_COLOR, cellStateLabel } from './cellStateColor';

describe('CELL_STATE_COLOR', () => {
    it('defines a background class, border class, and icon for every cell state', () => {
        const states: Array<keyof typeof CELL_STATE_COLOR> = [
            'empty',
            'full',
            'opened',
        ];

        for (const state of states) {
            const color = CELL_STATE_COLOR[state];
            expect(color.backgroundClass).toBeTruthy();
            expect(color.borderClass).toBeTruthy();
            expect(color.icon).toBeTruthy();
        }
    });

    it('gives every state a distinct icon', () => {
        const icons = Object.values(CELL_STATE_COLOR).map((c) => c.icon);

        expect(new Set(icons).size).toBe(icons.length);
    });

    it('pins the exact background and border classes for every cell state', () => {
        expect(CELL_STATE_COLOR.empty.backgroundClass).toBe(
            'bg-cell-empty dark:bg-cell-empty-dark',
        );
        expect(CELL_STATE_COLOR.empty.borderClass).toBe(
            'border-cell-empty-line dark:border-cell-empty-line-dark',
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

/**
 * The back office remaps the stock gray/neutral scales to the Direction A
 * neutrals (resources/css/app.css). The cell-state palette is a functional
 * legend mirrored in the Flutter app, so its colours must stay exactly the
 * Tailwind stock values they had before the restyle.
 */
describe('cell-state palette', () => {
    // Vitest runs from the project root (vitest.config.ts).
    const appCss = readFileSync(
        resolve(process.cwd(), 'resources/css/app.css'),
        'utf8',
    );
    const stockTheme = readFileSync(
        resolve(process.cwd(), 'node_modules/tailwindcss/theme.css'),
        'utf8',
    );

    function declared(css: string, name: string): string | undefined {
        return new RegExp(`--${name}:\\s*([^;]+);`).exec(css)?.[1].trim();
    }

    it('pins the empty state to the stock gray-100/400 and neutral-900/700 values', () => {
        const pins: Record<string, string> = {
            'color-cell-empty': 'color-gray-100',
            'color-cell-empty-line': 'color-gray-400',
            'color-cell-empty-dark': 'color-neutral-900',
            'color-cell-empty-line-dark': 'color-neutral-700',
        };

        for (const [pin, stock] of Object.entries(pins)) {
            const stockValue = declared(stockTheme, stock);

            expect(stockValue).toBeTruthy();
            expect(declared(appCss, pin)).toBe(stockValue);
        }
    });

    it('never remaps the green and orange scales the full and opened states read', () => {
        expect(appCss).not.toMatch(/--color-(green|orange)-/);
        expect(
            [
                CELL_STATE_COLOR.full.backgroundClass,
                CELL_STATE_COLOR.full.borderClass,
                CELL_STATE_COLOR.opened.backgroundClass,
                CELL_STATE_COLOR.opened.borderClass,
            ].join(' '),
        ).not.toMatch(/gray|neutral|blue|red/);
    });

    it('uses no class from a remapped scale for any state', () => {
        for (const color of Object.values(CELL_STATE_COLOR)) {
            expect(`${color.backgroundClass} ${color.borderClass}`).not.toMatch(
                /-(gray|neutral|blue|red)-/,
            );
        }
    });
});

describe('cellStateLabel', () => {
    it('translates the state key', () => {
        expect(cellStateLabel('full')).toBe(t('cellLog.states.full'));
    });
});

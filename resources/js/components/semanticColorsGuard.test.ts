import { describe, expect, it } from 'vitest';

/**
 * The ratchet of .ai/rules/js.md ("Back-office colours come from the
 * Direction A tokens"): the shared components, layouts, lib classes and every
 * back-office page use the semantic utilities from resources/css/app.css
 * (`bg-surface`, `text-muted`, `border-line`, `bg-danger-soft`, `text-info`,
 * `shadow-pop`, ...), which switch for dark mode by themselves. A raw palette
 * class (`text-gray-600`, `dark:bg-neutral-800`, `bg-blue-100`), a white or
 * black utility, a stock shadow size or a hex colour must not come back.
 *
 * The warehouse map's own code (resources/js/warehouse-map, resources/css/
 * warehouse-map) and the tests are outside the globs. Keep the allow-list
 * short and explicit.
 */
const sources: Record<string, string> = {
    ...import.meta.glob<string>('../components/**/*.vue', {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
    ...import.meta.glob<string>('../layouts/**/*.vue', {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
    ...import.meta.glob<string>(['../lib/**/*.ts', '!../lib/**/*.test.ts'], {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
    ...import.meta.glob<string>('../pages/**/*.vue', {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
};

const PALETTES =
    'gray|neutral|slate|zinc|stone|blue|red|green|amber|orange|yellow|lime|emerald|teal|cyan|sky|indigo|violet|purple|fuchsia|pink|rose';
const UTILITIES =
    'bg|text|border|border-[xytblrse]|ring|ring-offset|divide|outline|fill|stroke|placeholder|from|via|to|accent|decoration|shadow|caret';

/** Every banned family; the lookbehind keeps `has-text-` style words out. */
const BANNED: Record<string, RegExp> = {
    'raw palette class': new RegExp(
        `(?<![\\w-])(?:${UTILITIES})-(?:${PALETTES})-\\d{2,3}(?:/\\d+)?(?![\\w-])`,
        'g',
    ),
    'white or black utility':
        /(?<![\w-])(?:bg|text|border|ring|divide|outline|fill|stroke)-(?:white|black)(?:\/\d+)?(?![\w-])/g,
    'stock shadow size':
        /(?<![\w-])shadow-(?:xs|sm|md|lg|xl|2xl|inner)(?![\w-])/g,
    'hex colour':
        /(?<=['"`:\s(])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3})(?![\w-])/g,
};

/**
 * Where a family is deliberately allowed.
 * - `lib/cellStateColor.ts`: the cell-state legend is pinned to Tailwind's
 *   stock green/orange (cell-state-visuals.md, mirrored in the Flutter app).
 * - `pages/Admin/Help/CellLogs.vue`: `'#123'` is a sample pallet id, not a
 *   colour.
 * - the three components below hold the value or placeholder of a native
 *   `<input type="color">` / hex text field, which only accepts `#rrggbb`;
 *   that is data, not styling.
 */
const ALLOWED: Record<string, string[]> = {
    'lib/cellStateColor.ts': [
        'raw palette class',
        'white or black utility',
        'stock shadow size',
        'hex colour',
    ],
    'pages/Admin/Help/CellLogs.vue': ['hex colour'],
    'components/StoreRichTextField.vue': ['hex colour'],
    'components/StoreWebsiteAppearanceFormFields.vue': ['hex colour'],
    'components/StoreFlashDealFormFields.vue': ['hex colour'],
};

function findings(source: string): [string, string][] {
    return Object.entries(BANNED).flatMap(([family, pattern]) =>
        [...source.matchAll(pattern)].map(
            (match) => [family, match[0]] as [string, string],
        ),
    );
}

describe('semantic back-office colours', () => {
    it('scans the shared components, layouts, lib classes and pages', () => {
        const files = Object.keys(sources);

        expect(files.length).toBeGreaterThan(250);
        expect(files).toContain('../components/DataTable.vue');
        expect(files).toContain('../layouts/AdminLayout.vue');
        expect(files).toContain('../lib/filters.ts');
        expect(files).toContain('../pages/Auth/Login.vue');
        expect(files.some((file) => file.endsWith('.test.ts'))).toBe(false);
    });

    it('recognises every banned family (the matcher itself works)', () => {
        const samples: Record<string, string> = {
            'raw palette class':
                'class="text-gray-600 dark:bg-neutral-800 hover:bg-blue-100 ring-red-500/30"',
            'white or black utility': 'class="bg-white text-black/50"',
            'stock shadow size': 'class="shadow-lg"',
            'hex colour': "color: '#2563eb'; background: #fff;",
        };

        for (const [family, sample] of Object.entries(samples)) {
            expect(
                findings(sample).some(([name]) => name === family),
                family,
            ).toBe(true);
        }

        expect(
            findings(
                'class="bg-surface text-muted border-line shadow-pop text-link bg-info-soft" href="#main" #default="{ a }" #row',
            ),
        ).toEqual([]);
    });

    it('has no raw palette class, white/black utility, stock shadow or hex colour', () => {
        const offences = Object.entries(sources).flatMap(([file, source]) => {
            const key = file.replace('../', '');
            const allowed = ALLOWED[key] ?? [];

            return findings(source)
                .filter(([family]) => !allowed.includes(family))
                .map(([family, text]) => `${key}: ${family} "${text}"`);
        });

        expect(offences).toEqual([]);
    });

    it('keeps the allow-list short and pointing at real files', () => {
        expect(Object.keys(ALLOWED).length).toBeLessThanOrEqual(5);

        for (const file of Object.keys(ALLOWED)) {
            expect(Object.keys(sources)).toContain(`../${file}`);
        }
    });
});

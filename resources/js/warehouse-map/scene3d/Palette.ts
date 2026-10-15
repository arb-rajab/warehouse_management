/** Colours the 3D scene draws with, as 0xRRGGBB numbers. */
export interface ScenePalette {
    floor: number;
    /** Floor grid line, as a CSS colour (it is painted on a canvas). */
    grid: string;
    glow: number;
    lane: number;
    laneAlt: number;
    foot: number;
    back: number;
    upright: number;
    beam: number;
    wood: number;
    full: number;
    opened: number;
    empty: number;
    target: number;
    aisleLine: number;
    ceil: number;
    ceilLight: number;
    background: number;
    danger: number;
    primary: number;
}

export const DEFAULT_PALETTE: ScenePalette = {
    floor: 0x131d31,
    grid: '#1E2D4A',
    glow: 0x1e3a8a,
    lane: 0x1c2b47,
    laneAlt: 0x16233b,
    foot: 0x0d1524,
    back: 0x1b2638,
    upright: 0x475569,
    beam: 0xc2410c,
    wood: 0x8d6e63,
    full: 0x10b981,
    opened: 0xf59e0b,
    empty: 0x334155,
    target: 0xfacc15,
    aisleLine: 0xeab308,
    ceil: 0x0a101c,
    ceilLight: 0xe2e8f0,
    background: 0x0b1120,
    danger: 0xe11d48,
    primary: 0x2563eb,
};

/** The CSS custom properties (declared in tokens.css) that drive the palette. */
const TOKEN_FOR: Partial<Record<keyof ScenePalette, string>> = {
    full: '--wms-success',
    opened: '--wms-warning',
    target: '--wms-target',
    danger: '--wms-danger',
    primary: '--wms-primary',
    background: '--wms-canvas',
    upright: '--wms-steel',
    beam: '--wms-beam',
};

/** `#RRGGBB` / `#RGB` to a number; null when it is not a hex colour. */
export function parseHexColor(value: string): number | null {
    const text = value.trim();
    const match = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(text);

    if (!match) {
        return null;
    }

    const hex =
        match[1].length === 3
            ? match[1]
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : match[1];

    return Number.parseInt(hex, 16);
}

/**
 * The brand colours live in CSS (one place to change them); the scene reads
 * them once when it is built and keeps the defaults for anything missing.
 */
export function readPalette(root: Element): ScenePalette {
    const styles = getComputedStyle(root);
    const palette: ScenePalette = { ...DEFAULT_PALETTE };

    for (const [key, token] of Object.entries(TOKEN_FOR) as Array<
        [keyof ScenePalette, string]
    >) {
        const parsed = parseHexColor(styles.getPropertyValue(token));

        if (parsed !== null && key !== 'grid') {
            (palette[key] as number) = parsed;
        }
    }

    return palette;
}

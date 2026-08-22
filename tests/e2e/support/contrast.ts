/** WCAG relative luminance for an [r, g, b] (0-255) color. */
export function relativeLuminance([r, g, b]: number[]): number {
    const channel = (c: number) => {
        const s = c / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/** WCAG contrast ratio between two [r, g, b] (0-255) colors. */
export function contrastRatio(a: number[], b: number[]): number {
    const [lighter, darker] = [relativeLuminance(a), relativeLuminance(b)].sort(
        (x, y) => y - x,
    );

    return (lighter + 0.05) / (darker + 0.05);
}

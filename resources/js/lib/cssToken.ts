/**
 * The current value of a Direction A CSS token (`--brand`, `--line`, ...) on
 * the document root, for code that cannot read a CSS variable itself, such
 * as Chart.js on a canvas (resources/css/brand/backoffice.css switches the
 * values for dark mode). An unset token reads as `fallback`.
 */
export function readCssToken(name: string, fallback = ''): string {
    if (typeof window === 'undefined' || !window.getComputedStyle) {
        return fallback;
    }

    const value = window
        .getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    return value || fallback;
}

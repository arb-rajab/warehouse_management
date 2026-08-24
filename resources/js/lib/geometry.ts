/**
 * Tiny screen-space geometry helpers shared by anything that tracks pointer
 * positions for drag/pinch gestures — mapViewport.ts (2D pan/pinch-zoom) and
 * mapWalker.ts (3D orbit pinch-zoom).
 */
export interface Point {
    x: number;
    y: number;
}

export function distanceBetween(a: Point, b: Point): number {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

/** Wraps a degree value into [0, 360) — e.g. -10 becomes 350, 370 becomes 10. */
export function normalizeDegrees(degrees: number): number {
    return ((degrees % 360) + 360) % 360;
}

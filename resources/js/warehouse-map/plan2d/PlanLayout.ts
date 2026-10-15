import { THRESHOLDS } from '../config';
import { clamp } from '../utils/math';

export type Orientation = 'v' | 'h';
export interface Size {
    w: number;
    h: number;
}

/** The plan sits between the toolbars: this much is left free at the top and the bottom. */
export const PLAN_INSETS = { top: 66, bottom: 30 } as const;

/** A match is drawn larger the further out the map is zoomed (the ring does the rest). */
export function highlightScale(zoom: number): number {
    if (zoom >= 0.5) {
        return 1.2;
    }

    return zoom >= 0.25 ? 1.4 : 1.5;
}

/** A zoomed-out plan drops shadows, gradients and digits: unreadable there and costly to paint. */
export function isCoarse(zoom: number): boolean {
    return THRESHOLDS.planTilePx * zoom < THRESHOLDS.planLodTilePx;
}

export interface View2D {
    x: number;
    y: number;
    k: number;
}

export interface Fit {
    orientation: Orientation;
    /** Zoom that fits the whole plan on screen (with a little air around it). */
    zoom: number;
    /** Where the plan goes, so it sits centred between the toolbars. */
    view: View2D;
    size: Size;
}

/**
 * Fits the plan to the screen. The plan chooses its own orientation: the
 * vertical one (the way the 3D is laid out) wins unless the one lying down
 * fits clearly better (a 44-column warehouse is much longer than it is wide).
 */
export function fitPlan(
    viewport: Size,
    sizes: Record<Orientation, Size>,
    preference: Orientation | 'auto',
): Fit | null {
    const available = viewport.h - (PLAN_INSETS.top + PLAN_INSETS.bottom);
    const candidates: Orientation[] =
        preference === 'auto' ? ['v', 'h'] : [preference];
    let best: { orientation: Orientation; k: number } | null = null;

    for (const orientation of candidates) {
        const size = sizes[orientation];

        if (!size.w || !size.h) {
            return null;
        }

        const k = Math.min(viewport.w / size.w, available / size.h);

        if (!best || k > best.k * 1.12) {
            best = { orientation, k };
        }
    }

    if (!best) {
        return null;
    }

    const size = sizes[best.orientation];
    const zoom = best.k * 0.97;

    return {
        orientation: best.orientation,
        zoom,
        size,
        view: {
            x: (viewport.w - size.w * zoom) / 2,
            y: (available - size.h * zoom) / 2 + PLAN_INSETS.top,
            k: zoom,
        },
    };
}

export function zoomLimits(fitZoom: number): { min: number; max: number } {
    return {
        min: Math.min(fitZoom, THRESHOLDS.planMinZoom),
        max: THRESHOLDS.planMaxZoom,
    };
}

/** Zooms by `factor` keeping the point (cx, cy) of the screen where it is. */
export function zoomAbout(
    view: View2D,
    factor: number,
    cx: number,
    cy: number,
    limits: { min: number; max: number },
): View2D {
    const k = clamp(view.k * factor, limits.min, limits.max);
    const f = k / view.k;

    return { x: cx - (cx - view.x) * f, y: cy - (cy - view.y) * f, k };
}

/** Puts a point of the plan (in plan pixels at the current zoom) in the middle of the screen. */
export function centerOnPoint(
    viewport: Size,
    pointInPlan: { x: number; y: number },
    zoom: number,
    fitZoom: number,
): View2D {
    const k = Math.max(fitZoom, zoom);

    return {
        x: viewport.w / 2 - pointInPlan.x * k,
        y: viewport.h / 2 - pointInPlan.y * k,
        k,
    };
}

export interface Pointer2D {
    x: number;
    y: number;
}

export interface PinchState {
    cx: number;
    cy: number;
    /** Distance between the two fingers (1 for a single finger). */
    distance: number;
}

export function pinchState(points: readonly Pointer2D[]): PinchState | null {
    if (points.length === 0) {
        return null;
    }

    if (points.length === 1) {
        return { cx: points[0].x, cy: points[0].y, distance: 1 };
    }

    const [a, b] = points;

    return {
        cx: (a.x + b.x) / 2,
        cy: (a.y + b.y) / 2,
        distance: Math.hypot(a.x - b.x, a.y - b.y) || 1,
    };
}

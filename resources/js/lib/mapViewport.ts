import { reactive, readonly } from 'vue';
import { distanceBetween, normalizeDegrees } from '@/lib/geometry';
import type { Point } from '@/lib/geometry';

export const ZOOM_MIN = 0.5;
export const ZOOM_MAX = 2.5;
const ZOOM_STEP = 0.2;
const WHEEL_ZOOM_SENSITIVITY = 0.001;
const ROTATION_STEP_DEGREES = 90;

interface MapViewportState {
    panX: number;
    panY: number;
    zoom: number;
    rotation: number;
}

function initialViewportState(): MapViewportState {
    return { panX: 0, panY: 0, zoom: 1, rotation: 0 };
}

export function clampZoom(zoom: number): number {
    return Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoom));
}

/**
 * Wraps a rotation into [0, 360) — e.g. -90 becomes 270, 450 becomes 90.
 */
export const normalizeRotation = normalizeDegrees;

export function zoomFromWheelDelta(
    currentZoom: number,
    deltaY: number,
): number {
    return clampZoom(currentZoom - deltaY * WHEEL_ZOOM_SENSITIVITY);
}

export function zoomFromPinch(
    zoomAtPinchStart: number,
    startDistance: number,
    currentDistance: number,
): number {
    if (startDistance === 0) {
        return clampZoom(zoomAtPinchStart);
    }

    return clampZoom(zoomAtPinchStart * (currentDistance / startDistance));
}

/**
 * The CSS transform for the pannable/zoomable map content. `translate` is the outermost
 * function, so it composes in final screen-pixel space — panX/panY are plain screen-pixel
 * offsets regardless of the current zoom. Rotation is deliberately not painted here — see
 * `mapOrientation` — since spinning the content via CSS would leave cell text sideways or
 * upside-down; instead rotation drives an actual axis swap in how the grid is laid out.
 */
export function viewportTransform(state: MapViewportState): string {
    return `translate(${state.panX}px, ${state.panY}px) scale(${state.zoom})`;
}

/**
 * Describes how the cells map's rows (one band per row letter) and their cells (the item
 * axis, by cell-number) should be laid out for a given rotation, so that rotating swaps
 * between stacking bands as rows vs. side-by-side columns (with labels re-anchored to the
 * correct edge) instead of spinning text via CSS.
 *
 * A row's band is always identified by its letter and always appears in `rows` order —
 * rotation never reorders which row comes before which other, so the row headers stay
 * beside each other in the same sequence no matter which direction you rotate. Only the
 * band axis's direction (stacked rows vs. side-by-side columns), the cell-number order
 * within each band, and which edge the band label sits on change with rotation.
 */
interface MapOrientation {
    /** Bands are laid out as side-by-side columns instead of stacked rows. */
    bandsAsColumns: boolean;
    /** Cell numbers within each band are listed high-to-low instead of low-to-high. */
    reverseNumbers: boolean;
    /**
     * Each band's letter label sits at the trailing edge of the band (right when stacked as
     * rows, bottom when arranged as columns) instead of the leading edge (left / top).
     */
    bandLabelAtEnd: boolean;
}

const MAP_ORIENTATIONS: MapOrientation[] = [
    { bandsAsColumns: false, reverseNumbers: false, bandLabelAtEnd: false }, // 0deg
    { bandsAsColumns: true, reverseNumbers: true, bandLabelAtEnd: true }, // 90deg
    { bandsAsColumns: false, reverseNumbers: true, bandLabelAtEnd: true }, // 180deg
    { bandsAsColumns: true, reverseNumbers: false, bandLabelAtEnd: false }, // 270deg
];

export function mapOrientation(rotationDegrees: number): MapOrientation {
    return MAP_ORIENTATIONS[normalizeRotation(rotationDegrees) / 90];
}

/**
 * Pan/zoom/rotate state for the warehouse map, driven by mouse wheel (zoom), pointer drag
 * (pan), two-pointer pinch (zoom), and explicit button calls (zoom/rotate/reset/panBy).
 */
export function useMapViewport() {
    const state = reactive(initialViewportState());

    const pointers = new Map<number, Point>();
    let panAnchor: Point | null = null;
    let pinchStartDistance: number | null = null;
    let zoomAtPinchStart = 1;

    function zoomIn(): void {
        state.zoom = clampZoom(state.zoom + ZOOM_STEP);
    }

    function zoomOut(): void {
        state.zoom = clampZoom(state.zoom - ZOOM_STEP);
    }

    function rotateLeft(): void {
        state.rotation = normalizeRotation(
            state.rotation - ROTATION_STEP_DEGREES,
        );
        state.panX = 0;
        state.panY = 0;
    }

    function rotateRight(): void {
        state.rotation = normalizeRotation(
            state.rotation + ROTATION_STEP_DEGREES,
        );
        state.panX = 0;
        state.panY = 0;
    }

    /**
     * Resets magnification and position only — rotation is left as-is, since
     * "reset view" should recenter without discarding the user's chosen
     * orientation of the map.
     */
    function reset(): void {
        state.panX = 0;
        state.panY = 0;
        state.zoom = 1;
    }

    function panBy(deltaX: number, deltaY: number): void {
        state.panX += deltaX;
        state.panY += deltaY;
    }

    function onWheel(event: WheelEvent): void {
        state.zoom = zoomFromWheelDelta(state.zoom, event.deltaY);
    }

    function onPointerDown(event: PointerEvent): void {
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (pointers.size === 1) {
            panAnchor = { x: event.clientX, y: event.clientY };
        } else if (pointers.size === 2) {
            panAnchor = null;
            const [a, b] = [...pointers.values()];
            pinchStartDistance = distanceBetween(a, b);
            zoomAtPinchStart = state.zoom;
        }
    }

    function onPointerMove(event: PointerEvent): void {
        if (!pointers.has(event.pointerId)) {
            return;
        }

        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (pointers.size === 1 && panAnchor) {
            panBy(event.clientX - panAnchor.x, event.clientY - panAnchor.y);
            panAnchor = { x: event.clientX, y: event.clientY };

            return;
        }

        if (pointers.size === 2 && pinchStartDistance !== null) {
            const [a, b] = [...pointers.values()];
            state.zoom = zoomFromPinch(
                zoomAtPinchStart,
                pinchStartDistance,
                distanceBetween(a, b),
            );
        }
    }

    function onPointerUp(event: PointerEvent): void {
        pointers.delete(event.pointerId);
        pinchStartDistance = null;

        if (pointers.size === 1) {
            const [remaining] = [...pointers.values()];
            panAnchor = remaining;
        } else {
            panAnchor = null;
        }
    }

    return {
        state: readonly(state),
        zoomIn,
        zoomOut,
        rotateLeft,
        rotateRight,
        reset,
        panBy,
        onWheel,
        onPointerDown,
        onPointerMove,
        onPointerUp,
    };
}

import { reactive, readonly } from 'vue';
import { distanceBetween, normalizeDegrees } from '@/lib/geometry';
import type { Point } from '@/lib/geometry';

export const ZOOM_MIN = 0.5;
export const ZOOM_MAX = 2.5;
const ZOOM_STEP = 0.2;
const WHEEL_ZOOM_SENSITIVITY = 0.001;
const ROTATION_STEP_DEGREES = 90;

/**
 * The minimum number of pixels of the map content that must stay overlapping
 * the viewport container on each axis. Keeps panning finite — the map can be
 * dragged/panned far enough to reach any edge or corner, but never so far
 * that it's pushed entirely into blank space with nothing left to grab.
 */
const MIN_PAN_OVERLAP_PX = 80;

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

export interface Size {
    width: number;
    height: number;
}

/**
 * The zoom that fits `contentSize` entirely inside `containerSize` (shrinking
 * only — never zooms past 100% just because the content is smaller than the
 * container), clamped to the same [ZOOM_MIN, ZOOM_MAX] bounds as every other
 * zoom change.
 */
export function zoomToFit(contentSize: Size, containerSize: Size): number {
    if (contentSize.width <= 0 || contentSize.height <= 0) {
        return clampZoom(1);
    }

    return clampZoom(
        Math.min(
            1,
            containerSize.width / contentSize.width,
            containerSize.height / contentSize.height,
        ),
    );
}

/**
 * Clamps a single pan axis so the (scaled) content box can't be pushed
 * further than `MIN_PAN_OVERLAP_PX` clear of the container on that axis.
 * `contentSize`/`displayedSize` are that axis's unscaled/scaled content
 * length; the content box is assumed to start centered on the container's
 * own top-left-flow position (transform-origin: center on an element that
 * otherwise sits at the container's origin), matching how `viewportTransform`
 * applies translate on top of a center-origin scale.
 */
export function clampPanAxis(
    pan: number,
    contentSize: number,
    displayedSize: number,
    containerSize: number,
): number {
    const centerScreen = contentSize / 2 + pan;
    const minCenter = MIN_PAN_OVERLAP_PX - displayedSize / 2;
    const maxCenter = containerSize - MIN_PAN_OVERLAP_PX + displayedSize / 2;

    return (
        Math.min(maxCenter, Math.max(minCenter, centerScreen)) - contentSize / 2
    );
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
    let bounds: { container: Size; content: Size } | null = null;

    /**
     * Re-clamps the current pan against the last-known container/content
     * sizes. A no-op until `setBounds()` has been called at least once (e.g.
     * in tests that exercise pan/zoom without ever measuring real DOM
     * elements) — unbounded pan/zoom behavior is unchanged for callers that
     * never opt in.
     */
    function clampPanToBounds(): void {
        if (!bounds) {
            return;
        }

        const displayedWidth = bounds.content.width * state.zoom;
        const displayedHeight = bounds.content.height * state.zoom;

        state.panX = clampPanAxis(
            state.panX,
            bounds.content.width,
            displayedWidth,
            bounds.container.width,
        );
        state.panY = clampPanAxis(
            state.panY,
            bounds.content.height,
            displayedHeight,
            bounds.container.height,
        );
    }

    /**
     * Records the container/content sizes pan is clamped against, and
     * re-clamps immediately (e.g. after the container is resized). Sizes
     * should be unscaled/layout sizes — a `ResizeObserver`'s content-box
     * size, not a zoomed `getBoundingClientRect()`.
     */
    function setBounds(container: Size, content: Size): void {
        bounds = { container, content };
        clampPanToBounds();
    }

    function zoomIn(): void {
        state.zoom = clampZoom(state.zoom + ZOOM_STEP);
        clampPanToBounds();
    }

    function zoomOut(): void {
        state.zoom = clampZoom(state.zoom - ZOOM_STEP);
        clampPanToBounds();
    }

    function setZoom(zoom: number): void {
        state.zoom = clampZoom(zoom);
        clampPanToBounds();
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
        clampPanToBounds();
    }

    function onWheel(event: WheelEvent): void {
        state.zoom = zoomFromWheelDelta(state.zoom, event.deltaY);
        clampPanToBounds();
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
            clampPanToBounds();
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
        setBounds,
        zoomIn,
        zoomOut,
        setZoom,
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

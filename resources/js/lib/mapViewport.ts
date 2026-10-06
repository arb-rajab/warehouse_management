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
 * Clamps a single pan axis so the (scaled) content never leaves blank space inside the
 * container. `contentSize`/`displayedSize` are that axis's unscaled/scaled content length;
 * the content box is assumed to sit at the container's origin and scale around its own
 * center (transform-origin: center), matching how `viewportTransform` applies translate on
 * top of that scale. When the displayed content overflows the container its edges stay
 * flush with or beyond the container's; when it is smaller it stays flush with the leading
 * edge instead of drifting into the middle.
 */
export function clampPanAxis(
    pan: number,
    contentSize: number,
    displayedSize: number,
    containerSize: number,
): number {
    const originOffset = (contentSize - displayedSize) / 2;
    const leadingEdge = pan + originOffset;
    const clampedEdge =
        displayedSize <= containerSize
            ? 0
            : Math.min(0, Math.max(containerSize - displayedSize, leadingEdge));

    return clampedEdge - originOffset;
}

/**
 * The pan that keeps the content point currently under `anchor` (a position along the
 * container axis) under it after the zoom changes from `oldZoom` to `newZoom`, so
 * magnifying/minifying never drags the map.
 */
export function panKeepingAnchor(
    pan: number,
    contentSize: number,
    oldZoom: number,
    newZoom: number,
    anchor: number,
): number {
    const leadingEdge = pan + (contentSize * (1 - oldZoom)) / 2;
    const contentPoint = (anchor - leadingEdge) / oldZoom;
    const newLeadingEdge = anchor - newZoom * contentPoint;

    return newLeadingEdge - (contentSize * (1 - newZoom)) / 2;
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

/** Pointer travel (px) beyond which a press counts as a drag, not a tap. */
const DRAG_CLICK_THRESHOLD_PX = 8;

/**
 * Pan/zoom/rotate state for the warehouse map, driven by mouse wheel (zoom), pointer drag
 * (pan), two-pointer pinch (zoom), and explicit button calls (zoom/rotate/reset/panBy).
 */
export function useMapViewport() {
    const state = reactive(initialViewportState());

    const pointers = new Map<number, Point>();
    let panAnchor: Point | null = null;
    let gestureOrigin: Point | null = null;
    let gestureMoved = false;
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

    /**
     * Applies a new zoom while keeping the point under `anchor` (container-relative pixels,
     * defaulting to the container's center) fixed on screen, so zooming never drags the
     * map. Without known bounds there is nothing to anchor against, so only the zoom changes.
     */
    function zoomAround(zoom: number, anchor?: Point): void {
        const oldZoom = state.zoom;
        const newZoom = clampZoom(zoom);

        if (bounds) {
            state.panX = panKeepingAnchor(
                state.panX,
                bounds.content.width,
                oldZoom,
                newZoom,
                anchor?.x ?? bounds.container.width / 2,
            );
            state.panY = panKeepingAnchor(
                state.panY,
                bounds.content.height,
                oldZoom,
                newZoom,
                anchor?.y ?? bounds.container.height / 2,
            );
        }

        state.zoom = newZoom;
        clampPanToBounds();
    }

    function zoomIn(): void {
        zoomAround(state.zoom + ZOOM_STEP);
    }

    function zoomOut(): void {
        zoomAround(state.zoom - ZOOM_STEP);
    }

    function setZoom(zoom: number): void {
        zoomAround(zoom);
    }

    function rotateLeft(): void {
        state.rotation = normalizeRotation(
            state.rotation - ROTATION_STEP_DEGREES,
        );
    }

    function rotateRight(): void {
        state.rotation = normalizeRotation(
            state.rotation + ROTATION_STEP_DEGREES,
        );
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
        const container = (
            event.currentTarget as Element | null
        )?.getBoundingClientRect?.();

        zoomAround(
            zoomFromWheelDelta(state.zoom, event.deltaY),
            container
                ? {
                      x: event.clientX - container.left,
                      y: event.clientY - container.top,
                  }
                : undefined,
        );
    }

    function onPointerDown(event: PointerEvent): void {
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (pointers.size === 1) {
            panAnchor = { x: event.clientX, y: event.clientY };
            gestureOrigin = { x: event.clientX, y: event.clientY };
            gestureMoved = false;
        } else if (pointers.size === 2) {
            gestureMoved = true;
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

        if (
            gestureOrigin &&
            !gestureMoved &&
            distanceBetween(gestureOrigin, {
                x: event.clientX,
                y: event.clientY,
            }) > DRAG_CLICK_THRESHOLD_PX
        ) {
            gestureMoved = true;
        }

        if (pointers.size === 1 && panAnchor) {
            panBy(event.clientX - panAnchor.x, event.clientY - panAnchor.y);
            panAnchor = { x: event.clientX, y: event.clientY };

            return;
        }

        if (pointers.size === 2 && pinchStartDistance !== null) {
            const [a, b] = [...pointers.values()];
            zoomAround(
                zoomFromPinch(
                    zoomAtPinchStart,
                    pinchStartDistance,
                    distanceBetween(a, b),
                ),
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

    /**
     * Capture-phase click handler for the viewport element: swallows the click
     * that follows a pan/pinch gesture so releasing a drag over a pallet never
     * opens it, while a plain tap (movement within the threshold) passes through.
     */
    function onClickCapture(event: Event): void {
        if (!gestureMoved) {
            return;
        }

        gestureMoved = false;
        event.stopPropagation();
        event.preventDefault();
    }

    return {
        state: readonly(state),
        setBounds,
        onClickCapture,
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

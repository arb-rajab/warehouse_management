import { describe, expect, it } from 'vitest';
import {
    clampZoom,
    mapOrientation,
    normalizeRotation,
    useMapViewport,
    viewportTransform,
    zoomFromPinch,
    zoomFromWheelDelta,
    ZOOM_MAX,
    ZOOM_MIN,
} from './mapViewport';

function wheelEvent(deltaY: number): WheelEvent {
    return { deltaY } as WheelEvent;
}

function pointerEvent(pointerId: number, x: number, y: number): PointerEvent {
    return { pointerId, clientX: x, clientY: y } as PointerEvent;
}

describe('clampZoom', () => {
    it('leaves an in-range zoom untouched', () => {
        expect(clampZoom(1.2)).toBe(1.2);
    });

    it('clamps below the minimum', () => {
        expect(clampZoom(0.1)).toBe(ZOOM_MIN);
    });

    it('clamps above the maximum', () => {
        expect(clampZoom(10)).toBe(ZOOM_MAX);
    });
});

describe('normalizeRotation', () => {
    it('wraps a negative rotation into [0, 360)', () => {
        expect(normalizeRotation(-90)).toBe(270);
    });

    it('wraps a rotation past 360 back to the start', () => {
        expect(normalizeRotation(450)).toBe(90);
    });

    it('leaves an in-range rotation untouched', () => {
        expect(normalizeRotation(180)).toBe(180);
    });

    it('wraps exactly 360 to 0', () => {
        expect(normalizeRotation(360)).toBe(0);
    });
});

describe('zoomFromWheelDelta', () => {
    it('zooms in on a negative deltaY (scroll up)', () => {
        expect(zoomFromWheelDelta(1, -100)).toBeGreaterThan(1);
    });

    it('zooms out on a positive deltaY (scroll down)', () => {
        expect(zoomFromWheelDelta(1, 100)).toBeLessThan(1);
    });

    it('clamps the result to the zoom bounds', () => {
        expect(zoomFromWheelDelta(ZOOM_MIN, 100000)).toBe(ZOOM_MIN);
        expect(zoomFromWheelDelta(ZOOM_MAX, -100000)).toBe(ZOOM_MAX);
    });
});

describe('zoomFromPinch', () => {
    it('scales zoom proportionally to how far the pointers spread apart', () => {
        expect(zoomFromPinch(1, 100, 200)).toBe(2);
    });

    it('scales zoom down as pointers pinch together', () => {
        expect(zoomFromPinch(2, 200, 100)).toBe(1);
    });

    it('clamps the result to the zoom bounds', () => {
        expect(zoomFromPinch(ZOOM_MAX, 100, 1000)).toBe(ZOOM_MAX);
        expect(zoomFromPinch(ZOOM_MIN, 1000, 100)).toBe(ZOOM_MIN);
    });

    it('falls back to the starting zoom instead of dividing by zero', () => {
        expect(zoomFromPinch(1.5, 0, 50)).toBe(1.5);
    });
});

describe('viewportTransform', () => {
    it('renders translate and scale in that order, ignoring rotation', () => {
        expect(
            viewportTransform({ panX: 10, panY: -5, zoom: 1.5, rotation: 90 }),
        ).toBe('translate(10px, -5px) scale(1.5)');
    });
});

describe('mapOrientation', () => {
    it('lays bands out as rows with no reversal or edge changes at 0deg', () => {
        expect(mapOrientation(0)).toEqual({
            bandsAsColumns: false,
            reverseNumbers: false,
            bandLabelAtEnd: false,
        });
    });

    it('lays bands out as columns, numbers high-to-low, label at the end at 90deg', () => {
        expect(mapOrientation(90)).toEqual({
            bandsAsColumns: true,
            reverseNumbers: true,
            bandLabelAtEnd: true,
        });
    });

    it('keeps bands as rows but reverses the numbers and label edge at 180deg', () => {
        expect(mapOrientation(180)).toEqual({
            bandsAsColumns: false,
            reverseNumbers: true,
            bandLabelAtEnd: true,
        });
    });

    it('lays bands out as columns, numbers low-to-high, label at the start at 270deg', () => {
        expect(mapOrientation(270)).toEqual({
            bandsAsColumns: true,
            reverseNumbers: false,
            bandLabelAtEnd: false,
        });
    });

    it('wraps a negative rotation the same as its positive equivalent', () => {
        expect(mapOrientation(-90)).toEqual(mapOrientation(270));
    });
});

describe('useMapViewport', () => {
    it('reset() recenters pan and zoom but keeps the current rotation', () => {
        const viewport = useMapViewport();

        viewport.panBy(50, -20);
        viewport.zoomIn();
        viewport.rotateRight();
        viewport.reset();

        expect(viewport.state.panX).toBe(0);
        expect(viewport.state.panY).toBe(0);
        expect(viewport.state.zoom).toBe(1);
        expect(viewport.state.rotation).toBe(90);
    });

    it('rotateLeft()/rotateRight() recenter pan but keep the current zoom', () => {
        const viewport = useMapViewport();

        viewport.panBy(50, -20);
        viewport.zoomIn();
        viewport.rotateRight();

        expect(viewport.state.panX).toBe(0);
        expect(viewport.state.panY).toBe(0);
        expect(viewport.state.zoom).toBe(1.2);
        expect(viewport.state.rotation).toBe(90);

        viewport.panBy(15, 15);
        viewport.rotateLeft();

        expect(viewport.state.panX).toBe(0);
        expect(viewport.state.panY).toBe(0);
        expect(viewport.state.zoom).toBe(1.2);
        expect(viewport.state.rotation).toBe(0);
    });

    it('onWheel zooms via zoomFromWheelDelta', () => {
        const viewport = useMapViewport();

        viewport.onWheel(wheelEvent(-100));

        expect(viewport.state.zoom).toBe(zoomFromWheelDelta(1, -100));
    });

    it('a single pointer drag pans by the movement delta', () => {
        const viewport = useMapViewport();

        viewport.onPointerDown(pointerEvent(1, 100, 100));
        viewport.onPointerMove(pointerEvent(1, 130, 90));

        expect(viewport.state.panX).toBe(30);
        expect(viewport.state.panY).toBe(-10);

        // The anchor moves with the pointer, so a second move pans by the
        // next incremental delta, not the delta from the original start.
        viewport.onPointerMove(pointerEvent(1, 140, 90));

        expect(viewport.state.panX).toBe(40);
        expect(viewport.state.panY).toBe(-10);
    });

    it('ignores a pointermove for a pointer that never went down', () => {
        const viewport = useMapViewport();

        viewport.onPointerMove(pointerEvent(1, 130, 90));

        expect(viewport.state.panX).toBe(0);
        expect(viewport.state.panY).toBe(0);
    });

    it('a second pointer down starts a pinch instead of panning', () => {
        const viewport = useMapViewport();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerDown(pointerEvent(2, 100, 0));

        // The pinch-start distance is 100; moving the second pointer to 200
        // apart doubles the distance and so doubles the zoom.
        viewport.onPointerMove(pointerEvent(2, 200, 0));

        expect(viewport.state.zoom).toBe(zoomFromPinch(1, 100, 200));
        // A pinch in progress must not also pan.
        expect(viewport.state.panX).toBe(0);
        expect(viewport.state.panY).toBe(0);
    });

    it('lifting one finger of a pinch resumes panning from the remaining pointer', () => {
        const viewport = useMapViewport();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerDown(pointerEvent(2, 100, 0));
        viewport.onPointerUp(pointerEvent(2, 100, 0));

        viewport.onPointerMove(pointerEvent(1, 20, 5));

        expect(viewport.state.panX).toBe(20);
        expect(viewport.state.panY).toBe(5);
    });

    it('lifting the last pointer clears the pan anchor', () => {
        const viewport = useMapViewport();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerUp(pointerEvent(1, 0, 0));

        // The pointer is no longer tracked, so a further move for the same
        // id (e.g. a stray event) is ignored rather than panning.
        viewport.onPointerMove(pointerEvent(1, 50, 50));

        expect(viewport.state.panX).toBe(0);
        expect(viewport.state.panY).toBe(0);
    });

    it('lifting a pointer mid-pinch stops zooming from further movement of the other one', () => {
        const viewport = useMapViewport();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerDown(pointerEvent(2, 100, 0));
        viewport.onPointerUp(pointerEvent(1, 0, 0));

        const zoomAfterLift = viewport.state.zoom;

        // Only one pointer remains, so this becomes a pan, not a pinch —
        // zoom must not change from moving it further apart.
        viewport.onPointerMove(pointerEvent(2, 300, 0));

        expect(viewport.state.zoom).toBe(zoomAfterLift);
    });
});

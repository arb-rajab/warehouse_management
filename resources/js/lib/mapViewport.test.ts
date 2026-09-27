import { describe, expect, it } from 'vitest';
import {
    clampPanAxis,
    clampZoom,
    mapOrientation,
    normalizeRotation,
    useMapViewport,
    viewportTransform,
    zoomFromPinch,
    zoomFromWheelDelta,
    zoomToFit,
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

describe('zoomToFit', () => {
    it('shrinks to fit content that overflows the container on both axes', () => {
        expect(
            zoomToFit(
                { width: 1000, height: 800 },
                { width: 700, height: 600 },
            ),
        ).toBe(0.7);
    });

    it('shrinks by the more constraining axis when only one overflows', () => {
        expect(
            zoomToFit(
                { width: 1000, height: 200 },
                { width: 500, height: 500 },
            ),
        ).toBe(0.5);
    });

    it('never zooms in past 100% just because the content is smaller than the container', () => {
        expect(
            zoomToFit({ width: 100, height: 100 }, { width: 500, height: 500 }),
        ).toBe(1);
    });

    it('fits exactly when content and container are the same size', () => {
        expect(
            zoomToFit({ width: 400, height: 300 }, { width: 400, height: 300 }),
        ).toBe(1);
    });

    it('clamps the result to the minimum zoom', () => {
        expect(
            zoomToFit(
                { width: 10000, height: 10000 },
                { width: 100, height: 100 },
            ),
        ).toBe(ZOOM_MIN);
    });

    it('falls back to 100% zoom for a zero-sized content box', () => {
        expect(
            zoomToFit({ width: 0, height: 0 }, { width: 500, height: 500 }),
        ).toBe(1);
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

    it('setZoom() sets an arbitrary zoom, clamped to the zoom bounds', () => {
        const viewport = useMapViewport();

        viewport.setZoom(0.3);
        expect(viewport.state.zoom).toBe(ZOOM_MIN);

        viewport.setZoom(1.75);
        expect(viewport.state.zoom).toBe(1.75);
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

    describe('setBounds() — clamping pan to a finite range', () => {
        it('leaves panBy() unbounded when setBounds() was never called', () => {
            const viewport = useMapViewport();

            viewport.panBy(100000, -100000);

            expect(viewport.state.panX).toBe(100000);
            expect(viewport.state.panY).toBe(-100000);
        });

        it('clamps an existing out-of-range pan as soon as bounds are set', () => {
            const viewport = useMapViewport();

            viewport.panBy(100000, 100000);
            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );

            // content=1000, displayed=1000 (zoom 1), container=500 →
            // pan clamps to at most 920 - 500 = 420 (see clampPanAxis).
            expect(viewport.state.panX).toBe(420);
            expect(viewport.state.panY).toBe(420);
        });

        it('stops panBy() at the bound instead of letting it run past', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );

            viewport.panBy(-100000, -100000);
            expect(viewport.state.panX).toBe(-920);
            expect(viewport.state.panY).toBe(-920);

            viewport.panBy(100000000, 100000000);
            expect(viewport.state.panX).toBe(420);
            expect(viewport.state.panY).toBe(420);
        });

        it('stops a pointer drag at the bound the same way panBy() does', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );

            viewport.onPointerDown(pointerEvent(1, 0, 0));
            viewport.onPointerMove(pointerEvent(1, 100000, 100000));

            expect(viewport.state.panX).toBe(420);
            expect(viewport.state.panY).toBe(420);
        });

        it('re-clamps when zooming out shrinks the displayed content', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );
            viewport.panBy(420, 0);
            expect(viewport.state.panX).toBe(420);

            viewport.setZoom(0.5);

            // content=1000, displayed=500, container=500 → max pan is now
            // 670 - 500 = 170, tighter than the un-zoomed 420 it started at.
            expect(viewport.state.panX).toBe(170);
        });

        it('does not clamp a pan that already sits inside the bounds', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );
            viewport.panBy(50, -30);

            expect(viewport.state.panX).toBe(50);
            expect(viewport.state.panY).toBe(-30);
        });
    });
});

describe('clampPanAxis', () => {
    it('leaves an in-range pan untouched', () => {
        expect(clampPanAxis(50, 1000, 1000, 500)).toBe(50);
    });

    it('clamps a pan that would push the content past the max overlap', () => {
        expect(clampPanAxis(100000, 1000, 1000, 500)).toBe(420);
    });

    it('clamps a pan that would push the content past the min overlap', () => {
        expect(clampPanAxis(-100000, 1000, 1000, 500)).toBe(-920);
    });

    it('allows a wider range at a higher zoom (larger displayed size)', () => {
        // Same content/container: at zoom 1 (displayed 1000) the max pan is
        // 420 (see the earlier test); zoomed in to displayed 2000 the
        // content overhangs more, so more pan is needed before it loses
        // overlap — the max pan widens to 920.
        expect(clampPanAxis(1000, 1000, 2000, 500)).toBe(920);
        expect(clampPanAxis(2000, 1000, 2000, 500)).toBe(920);
    });
});

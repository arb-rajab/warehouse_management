import { describe, expect, it, vi } from 'vitest';
import {
    clampPanAxis,
    panKeepingAnchor,
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

    it('rotateLeft()/rotateRight() keep the current pan and zoom instead of re-centering', () => {
        const viewport = useMapViewport();

        viewport.panBy(50, -20);
        viewport.zoomIn();
        viewport.rotateRight();

        expect(viewport.state.panX).toBe(50);
        expect(viewport.state.panY).toBe(-20);
        expect(viewport.state.zoom).toBe(1.2);
        expect(viewport.state.rotation).toBe(90);

        viewport.rotateLeft();

        expect(viewport.state.panX).toBe(50);
        expect(viewport.state.panY).toBe(-20);
        expect(viewport.state.zoom).toBe(1.2);
        expect(viewport.state.rotation).toBe(0);
    });

    it('zoomIn()/zoomOut() leave pan alone when bounds were never set', () => {
        const viewport = useMapViewport();

        viewport.panBy(50, -20);
        viewport.zoomIn();
        viewport.zoomOut();

        expect(viewport.state.panX).toBe(50);
        expect(viewport.state.panY).toBe(-20);
    });

    it('zooming keeps the content point under the container center fixed', () => {
        const viewport = useMapViewport();
        viewport.setBounds(
            { width: 500, height: 500 },
            { width: 1000, height: 1000 },
        );
        viewport.panBy(-300, -200);

        // Content point under the container center before zooming.
        const pointUnderCenter = (zoom: number, pan: number, size: number) =>
            (250 - (pan + (size * (1 - zoom)) / 2)) / zoom;
        const before = pointUnderCenter(1, viewport.state.panX, 1000);

        viewport.zoomIn();

        expect(viewport.state.zoom).toBe(1.2);
        expect(pointUnderCenter(1.2, viewport.state.panX, 1000)).toBeCloseTo(
            before,
        );
    });

    it('wheel zoom keeps the content point under the cursor fixed', () => {
        const viewport = useMapViewport();
        viewport.setBounds(
            { width: 500, height: 500 },
            { width: 1000, height: 1000 },
        );
        viewport.panBy(-300, 0);

        const event = {
            deltaY: -200,
            clientX: 100,
            clientY: 0,
            currentTarget: {
                getBoundingClientRect: () => ({ left: 0, top: 0 }),
            },
        } as unknown as WheelEvent;
        const pointUnder = (zoom: number, pan: number) =>
            (100 - (pan + (1000 * (1 - zoom)) / 2)) / zoom;
        const before = pointUnder(1, viewport.state.panX);

        viewport.onWheel(event);

        expect(viewport.state.zoom).toBe(1.2);
        expect(pointUnder(1.2, viewport.state.panX)).toBeCloseTo(before);
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

    describe('setBounds() — clamping pan so no blank space surrounds the content', () => {
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

            // Content edges can't be dragged inside the container.
            expect(viewport.state.panX).toBe(0);
            expect(viewport.state.panY).toBe(0);
        });

        it('stops panBy() at the content edges instead of letting it run past', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );

            viewport.panBy(-100000, -100000);
            expect(viewport.state.panX).toBe(-500);
            expect(viewport.state.panY).toBe(-500);

            viewport.panBy(100000000, 100000000);
            expect(viewport.state.panX).toBe(0);
            expect(viewport.state.panY).toBe(0);
        });

        it('stops a pointer drag at the content edges the same way panBy() does', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );

            viewport.onPointerDown(pointerEvent(1, 0, 0));
            viewport.onPointerMove(pointerEvent(1, -100000, -100000));

            expect(viewport.state.panX).toBe(-500);
            expect(viewport.state.panY).toBe(-500);
        });

        it('keeps content flush with the leading edge when zooming out shrinks it below the container', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );
            viewport.panBy(-500, 0);
            expect(viewport.state.panX).toBe(-500);

            viewport.setZoom(0.5);

            // Displayed 500 == container 500, so the left edge sits at 0:
            // pan = 0 - (1000 - 500) / 2.
            expect(viewport.state.panX).toBe(-250);
        });

        it('does not clamp a pan that already sits inside the bounds', () => {
            const viewport = useMapViewport();

            viewport.setBounds(
                { width: 500, height: 500 },
                { width: 1000, height: 1000 },
            );
            viewport.panBy(-50, -30);

            expect(viewport.state.panX).toBe(-50);
            expect(viewport.state.panY).toBe(-30);
        });
    });
});

describe('clampPanAxis', () => {
    it('leaves an in-range pan untouched', () => {
        expect(clampPanAxis(-50, 1000, 1000, 500)).toBe(-50);
    });

    it('never lets the leading edge move inside the container', () => {
        expect(clampPanAxis(100000, 1000, 1000, 500)).toBe(0);
    });

    it('never lets the trailing edge move inside the container', () => {
        expect(clampPanAxis(-100000, 1000, 1000, 500)).toBe(-500);
    });

    it('allows a wider range at a higher zoom (larger displayed size)', () => {
        // displayed 2000 around a 1000 content: the leading edge starts at
        // -500, so pan ranges over [-1000, 500].
        expect(clampPanAxis(1000, 1000, 2000, 500)).toBe(500);
        expect(clampPanAxis(-5000, 1000, 2000, 500)).toBe(-1000);
    });

    it('pins content smaller than the container to the leading edge', () => {
        expect(clampPanAxis(300, 400, 400, 500)).toBe(0);
        expect(clampPanAxis(-300, 400, 400, 500)).toBe(0);
        // Shrunk by zoom: scale pivots on the center, so pan compensates.
        expect(clampPanAxis(0, 400, 200, 500)).toBe(-100);
    });
});

describe('panKeepingAnchor', () => {
    it('keeps the anchored content point at the same screen position', () => {
        const pan = panKeepingAnchor(-120, 1000, 1, 1.5, 200);
        const pointBefore = (200 - -120) / 1;
        const pointAfter = (200 - (pan + (1000 * (1 - 1.5)) / 2)) / 1.5;

        expect(pointAfter).toBeCloseTo(pointBefore);
    });

    it('is a no-op when the zoom does not change', () => {
        expect(panKeepingAnchor(-120, 1000, 1.2, 1.2, 300)).toBeCloseTo(-120);
    });
});

describe('useMapViewport click suppression after a drag', () => {
    function clickEvent() {
        return {
            stopPropagation: vi.fn(),
            preventDefault: vi.fn(),
        } as unknown as Event;
    }

    it('swallows the click that follows a drag beyond the threshold', () => {
        const viewport = useMapViewport();
        const click = clickEvent();

        viewport.onPointerDown(pointerEvent(1, 100, 100));
        viewport.onPointerMove(pointerEvent(1, 140, 100));
        viewport.onPointerUp(pointerEvent(1, 140, 100));
        viewport.onClickCapture(click);

        expect(click.stopPropagation).toHaveBeenCalledOnce();
        expect(click.preventDefault).toHaveBeenCalledOnce();
    });

    it('lets a tap with only slight movement through', () => {
        const viewport = useMapViewport();
        const click = clickEvent();

        viewport.onPointerDown(pointerEvent(1, 100, 100));
        viewport.onPointerMove(pointerEvent(1, 103, 102));
        viewport.onPointerUp(pointerEvent(1, 103, 102));
        viewport.onClickCapture(click);

        expect(click.stopPropagation).not.toHaveBeenCalled();
        expect(click.preventDefault).not.toHaveBeenCalled();
    });

    it('lets a tap through after an earlier drag was swallowed', () => {
        const viewport = useMapViewport();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerMove(pointerEvent(1, 50, 0));
        viewport.onPointerUp(pointerEvent(1, 50, 0));
        viewport.onClickCapture(clickEvent());

        const tap = clickEvent();
        viewport.onPointerDown(pointerEvent(1, 10, 10));
        viewport.onPointerUp(pointerEvent(1, 10, 10));
        viewport.onClickCapture(tap);

        expect(tap.stopPropagation).not.toHaveBeenCalled();
    });

    it('resets the drag flag on the next press when no click followed the drag', () => {
        const viewport = useMapViewport();
        const tap = clickEvent();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerMove(pointerEvent(1, 50, 0));
        viewport.onPointerUp(pointerEvent(1, 50, 0));

        viewport.onPointerDown(pointerEvent(1, 10, 10));
        viewport.onPointerUp(pointerEvent(1, 10, 10));
        viewport.onClickCapture(tap);

        expect(tap.stopPropagation).not.toHaveBeenCalled();
    });

    it('treats a pinch as a drag so lifting fingers over a pallet does not open it', () => {
        const viewport = useMapViewport();
        const click = clickEvent();

        viewport.onPointerDown(pointerEvent(1, 0, 0));
        viewport.onPointerDown(pointerEvent(2, 100, 0));
        viewport.onPointerUp(pointerEvent(2, 100, 0));
        viewport.onPointerUp(pointerEvent(1, 0, 0));
        viewport.onClickCapture(click);

        expect(click.stopPropagation).toHaveBeenCalledOnce();
    });
});

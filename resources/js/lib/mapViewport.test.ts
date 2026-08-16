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
});

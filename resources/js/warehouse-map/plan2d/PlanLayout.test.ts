import { describe, expect, it } from 'vitest';
import {
    PLAN_INSETS,
    centerOnPoint,
    fitPlan,
    highlightScale,
    isCoarse,
    pinchState,
    zoomAbout,
    zoomLimits,
} from './PlanLayout';

const tall = { w: 800, h: 3000 };
const wide = { w: 3000, h: 800 };

describe('highlightScale / isCoarse', () => {
    it('grows a match more the further out the map is', () => {
        expect(highlightScale(1)).toBe(1.2);
        expect(highlightScale(0.5)).toBe(1.2);
        expect(highlightScale(0.3)).toBe(1.4);
        expect(highlightScale(0.1)).toBe(1.5);
    });

    it('drops detail once a tile is smaller than a readable size', () => {
        expect(isCoarse(1)).toBe(false);
        expect(isCoarse(0.55)).toBe(false);
        expect(isCoarse(0.5)).toBe(true);
        expect(isCoarse(0.2)).toBe(true);
    });
});

describe('fitPlan', () => {
    const viewport = { w: 1400, h: 900 };

    it('keeps the vertical plan unless the one lying down fits clearly better', () => {
        const fit = fitPlan(
            viewport,
            { v: { w: 1000, h: 700 }, h: { w: 700, h: 1000 } },
            'auto',
        )!;

        expect(fit.orientation).toBe('v');
    });

    it('lies a long plan down on a wide screen', () => {
        const fit = fitPlan(viewport, { v: tall, h: wide }, 'auto')!;

        expect(fit.orientation).toBe('h');
        expect(fit.zoom).toBeGreaterThan(
            fitPlan(viewport, { v: tall, h: wide }, 'v')!.zoom,
        );
    });

    it('obeys an explicit orientation', () => {
        expect(fitPlan(viewport, { v: tall, h: wide }, 'v')!.orientation).toBe(
            'v',
        );
        expect(fitPlan(viewport, { v: tall, h: wide }, 'h')!.orientation).toBe(
            'h',
        );
    });

    it('fits the whole plan on screen, centred between the toolbars', () => {
        const fit = fitPlan(
            viewport,
            { v: { w: 400, h: 400 }, h: { w: 400, h: 400 } },
            'v',
        )!;
        const available = viewport.h - PLAN_INSETS.top - PLAN_INSETS.bottom;

        expect(fit.view.k).toBe(fit.zoom);
        expect(400 * fit.zoom).toBeLessThanOrEqual(available);
        expect(fit.view.x + (400 * fit.zoom) / 2).toBeCloseTo(viewport.w / 2);
        expect(fit.view.y + (400 * fit.zoom) / 2).toBeCloseTo(
            PLAN_INSETS.top + available / 2,
        );
    });

    it('cannot fit a plan that has not been laid out yet', () => {
        expect(
            fitPlan(viewport, { v: { w: 0, h: 0 }, h: { w: 0, h: 0 } }, 'auto'),
        ).toBeNull();
    });
});

describe('zoomAbout', () => {
    const limits = zoomLimits(0.4);

    it('keeps the point under the cursor where it is', () => {
        const before = { x: 100, y: 50, k: 1 };
        const after = zoomAbout(before, 1.5, 300, 200, limits);
        const planX = (300 - before.x) / before.k;
        const planY = (200 - before.y) / before.k;

        expect(after.k).toBe(1.5);
        expect(after.x + planX * after.k).toBeCloseTo(300);
        expect(after.y + planY * after.k).toBeCloseTo(200);
    });

    it('stays within the zoom limits', () => {
        expect(zoomAbout({ x: 0, y: 0, k: 1 }, 100, 0, 0, limits).k).toBe(1.6);
        expect(zoomAbout({ x: 0, y: 0, k: 1 }, 0.0001, 0, 0, limits).k).toBe(
            0.4,
        );
    });

    it('never needs to zoom out further than 0.6 unless the plan only fits smaller', () => {
        expect(zoomLimits(0.9).min).toBe(0.6);
        expect(zoomLimits(0.3).min).toBe(0.3);
    });
});

describe('centerOnPoint', () => {
    it('puts a plan point in the middle of the screen', () => {
        const view = centerOnPoint(
            { w: 1000, h: 800 },
            { x: 200, y: 100 },
            0.8,
            0.3,
        );

        expect(view.x + 200 * view.k).toBeCloseTo(500);
        expect(view.y + 100 * view.k).toBeCloseTo(400);
    });

    it('does not zoom out beyond what fits', () => {
        expect(
            centerOnPoint({ w: 1000, h: 800 }, { x: 0, y: 0 }, 0.2, 0.5).k,
        ).toBe(0.5);
    });
});

describe('pinchState', () => {
    it('is null without fingers, a point for one, and centre + distance for two', () => {
        expect(pinchState([])).toBeNull();
        expect(pinchState([{ x: 10, y: 20 }])).toEqual({
            cx: 10,
            cy: 20,
            distance: 1,
        });
        expect(
            pinchState([
                { x: 0, y: 0 },
                { x: 30, y: 40 },
            ]),
        ).toEqual({ cx: 15, cy: 20, distance: 50 });
    });
});

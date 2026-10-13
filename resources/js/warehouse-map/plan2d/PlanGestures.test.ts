import { describe, expect, it, vi } from 'vitest';
import { PlanGestures } from './PlanGestures';

function pointer(
    type: string,
    init: { x?: number; y?: number; id?: number } = {},
) {
    const event = new MouseEvent(type, {
        clientX: init.x ?? 0,
        clientY: init.y ?? 0,
        bubbles: true,
        cancelable: true,
    }) as MouseEvent & {
        pointerId?: number;
    };

    event.pointerId = init.id ?? 1;

    return event;
}

function setup(hit: Element | null = null) {
    const stage = document.createElement('div');
    const handlers = {
        onPan: vi.fn(),
        onZoom: vi.fn(),
        onTapTile: vi.fn(),
        onFit: vi.fn(),
        onGrab: vi.fn(),
    };
    const gestures = new PlanGestures(stage, handlers, () => hit);

    document.body.append(stage);
    gestures.mount();

    return { stage, handlers, gestures };
}

describe('PlanGestures', () => {
    it('pans with a drag and tells when it is grabbed and let go', () => {
        const { stage, handlers, gestures } = setup();

        stage.dispatchEvent(pointer('pointerdown', { x: 10, y: 10 }));
        stage.dispatchEvent(pointer('pointermove', { x: 25, y: 5 }));
        stage.dispatchEvent(pointer('pointerup', { x: 25, y: 5 }));

        expect(handlers.onGrab.mock.calls).toEqual([[true], [false]]);
        expect(handlers.onPan).toHaveBeenCalledWith(15, -5);
        expect(handlers.onTapTile).not.toHaveBeenCalled();
        gestures.dispose();
    });

    it('opens the tile under a short still press', () => {
        const tile = document.createElement('button');

        tile.className = 'wms-tile';

        const { stage, handlers, gestures } = setup(tile);

        stage.dispatchEvent(pointer('pointerdown', { x: 50, y: 50 }));
        stage.dispatchEvent(pointer('pointerup', { x: 50, y: 50 }));

        expect(handlers.onTapTile).toHaveBeenCalledWith(tile);
        gestures.dispose();
    });

    it('does not open a tile after dragging, a cancelled press, or on empty floor', () => {
        const tile = document.createElement('button');

        tile.className = 'wms-tile';

        const dragged = setup(tile);

        dragged.stage.dispatchEvent(pointer('pointerdown', { x: 0, y: 0 }));
        dragged.stage.dispatchEvent(pointer('pointermove', { x: 30, y: 0 }));
        dragged.stage.dispatchEvent(pointer('pointerup', { x: 30, y: 0 }));
        expect(dragged.handlers.onTapTile).not.toHaveBeenCalled();
        dragged.gestures.dispose();

        const cancelled = setup(tile);

        cancelled.stage.dispatchEvent(pointer('pointerdown'));
        cancelled.stage.dispatchEvent(pointer('pointercancel'));
        expect(cancelled.handlers.onTapTile).not.toHaveBeenCalled();
        cancelled.gestures.dispose();

        const floor = setup(document.createElement('div'));

        floor.stage.dispatchEvent(pointer('pointerdown'));
        floor.stage.dispatchEvent(pointer('pointerup'));
        expect(floor.handlers.onTapTile).not.toHaveBeenCalled();
        floor.gestures.dispose();
    });

    it('zooms with the wheel (more slowly with ctrl off than on a pinch-wheel)', () => {
        const { stage, handlers, gestures } = setup();
        const wheel = new WheelEvent('wheel', {
            deltaY: -100,
            clientX: 40,
            clientY: 60,
            cancelable: true,
            bubbles: true,
        });

        stage.dispatchEvent(wheel);

        expect(wheel.defaultPrevented).toBe(true);
        expect(handlers.onZoom).toHaveBeenCalledWith(Math.exp(0.15), 40, 60);

        stage.dispatchEvent(
            new WheelEvent('wheel', {
                deltaY: -100,
                ctrlKey: true,
                cancelable: true,
                bubbles: true,
            }),
        );
        expect(handlers.onZoom.mock.calls[1][0]).toBeCloseTo(Math.exp(1));
        gestures.dispose();
    });

    it('pinches and pans with two fingers', () => {
        const { stage, handlers, gestures } = setup();

        stage.dispatchEvent(pointer('pointerdown', { x: 100, y: 100, id: 1 }));
        stage.dispatchEvent(pointer('pointerdown', { x: 200, y: 100, id: 2 }));
        handlers.onPan.mockClear();
        stage.dispatchEvent(pointer('pointermove', { x: 50, y: 100, id: 1 })); // spreads 100 -> 150 apart

        expect(handlers.onZoom).toHaveBeenCalled();
        expect(handlers.onZoom.mock.calls.at(-1)![0]).toBeCloseTo(1.5);
        expect(handlers.onPan).toHaveBeenCalled();
        gestures.dispose();
    });

    it('fits on a double click anywhere but on a tile', () => {
        const { stage, handlers, gestures } = setup();
        const tile = document.createElement('button');

        tile.className = 'wms-tile';
        stage.append(tile);

        tile.dispatchEvent(new MouseEvent('dblclick', { bubbles: true }));
        expect(handlers.onFit).not.toHaveBeenCalled();

        stage.dispatchEvent(new MouseEvent('dblclick', { bubbles: true }));
        expect(handlers.onFit).toHaveBeenCalledTimes(1);
        gestures.dispose();
    });

    it('ignores presses that start on the floating controls', () => {
        const { stage, handlers, gestures } = setup();
        const hud = document.createElement('div');

        hud.className = 'wms-hud';
        stage.append(hud);
        hud.dispatchEvent(pointer('pointerdown'));

        expect(handlers.onGrab).not.toHaveBeenCalled();
        gestures.dispose();
    });

    it('stops after dispose', () => {
        const { stage, handlers, gestures } = setup();

        gestures.dispose();
        stage.dispatchEvent(pointer('pointerdown'));

        expect(handlers.onGrab).not.toHaveBeenCalled();
    });
});

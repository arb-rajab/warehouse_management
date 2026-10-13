import { describe, expect, it, vi } from 'vitest';
import type { InputContext, Intent } from './Intent';
import { KeyboardMouseAdapter } from './KeyboardMouseAdapter';

function setup(context: Partial<InputContext> = {}) {
    const canvas = document.createElement('canvas');

    document.body.append(canvas);

    const ctx: InputContext = {
        isSceneInteractive: () => true,
        mode: () => 'walk',
        nav: () => 'free',
        aisleCount: () => 20,
        ...context,
    };
    const adapter = new KeyboardMouseAdapter(canvas, document, ctx, () => 1000);
    const intents: Intent[] = [];

    adapter.onIntent((i) => intents.push(i));
    adapter.mount();

    const key = (
        type: 'keydown' | 'keyup',
        code: string,
        init: KeyboardEventInit = {},
    ) =>
        window.dispatchEvent(
            new KeyboardEvent(type, {
                code,
                bubbles: true,
                cancelable: true,
                ...init,
            }),
        );

    return { adapter, canvas, intents, key };
}

describe('KeyboardMouseAdapter: free walk', () => {
    it('turns held WASD into walking axes and releases them', () => {
        const { adapter, intents, key } = setup();

        key('keydown', 'KeyW');
        key('keydown', 'KeyD');
        key('keyup', 'KeyW');
        key('keyup', 'KeyD');

        expect(intents).toEqual([
            { type: 'move', source: 'keyboard', forward: 1, strafe: 0 },
            { type: 'move', source: 'keyboard', forward: 1, strafe: 1 },
            { type: 'move', source: 'keyboard', forward: 0, strafe: 1 },
            { type: 'move', source: 'keyboard', forward: 0, strafe: 0 },
        ]);
        adapter.dispose();
    });

    it('sprints on Shift and lifts on Space / Z', () => {
        const { adapter, intents, key } = setup();

        key('keydown', 'ShiftLeft');
        key('keydown', 'Space');
        key('keydown', 'KeyZ');
        key('keyup', 'Space');
        key('keyup', 'KeyZ');
        key('keyup', 'ShiftLeft');

        expect(intents).toContainEqual({
            type: 'sprint',
            source: 'keyboard',
            on: true,
        });
        expect(intents).toContainEqual({
            type: 'lift',
            source: 'keyboard',
            value: 1,
        });
        expect(intents).toContainEqual({
            type: 'lift',
            source: 'keyboard',
            value: 0,
        });
        expect(intents.at(-1)).toEqual({
            type: 'sprint',
            source: 'keyboard',
            on: false,
        });
        adapter.dispose();
    });

    it('maps the mode keys', () => {
        const { adapter, intents, key } = setup();

        key('keydown', 'KeyG');
        key('keydown', 'KeyO');
        key('keydown', 'KeyM');
        key('keydown', 'KeyF');
        key('keydown', 'Digit3');

        expect(intents.map((i) => i.type)).toEqual([
            'toggleNav',
            'goOverview',
            'toggleMinimap',
            'inspect',
            'aisle',
        ]);
        expect(intents.at(-1)).toEqual({ type: 'aisle', index: 2 });
        adapter.dispose();
    });

    it('jumps to aisle ten on 0 and ignores aisles that do not exist', () => {
        const { adapter, intents, key } = setup({ aisleCount: () => 4 });

        key('keydown', 'Digit0');
        key('keydown', 'Digit9');
        key('keydown', 'Digit4');

        expect(intents).toEqual([{ type: 'aisle', index: 3 }]);
        adapter.dispose();
    });

    it('never hijacks a browser shortcut', () => {
        const { adapter, intents, key } = setup();

        key('keydown', 'KeyW', { ctrlKey: true });
        key('keydown', 'KeyW', { metaKey: true });

        expect(intents).toEqual([]);
        adapter.dispose();
    });

    it('ignores keys while typing in an input', () => {
        const { adapter, intents } = setup();
        const input = document.createElement('input');

        document.body.append(input);
        input.dispatchEvent(
            new KeyboardEvent('keydown', { code: 'KeyW', bubbles: true }),
        );

        expect(intents).toEqual([]);
        adapter.dispose();
    });

    it('ignores keys when the scene is not interactive', () => {
        const { adapter, intents, key } = setup({
            isSceneInteractive: () => false,
        });

        key('keydown', 'KeyW');

        expect(intents).toEqual([]);
        adapter.dispose();
    });

    it('stops walking when the window loses focus', () => {
        const { adapter, intents, key } = setup();

        key('keydown', 'KeyW');
        window.dispatchEvent(new Event('blur'));

        expect(intents).toContainEqual({
            type: 'move',
            source: 'keyboard',
            forward: 0,
            strafe: 0,
        });
        expect(intents.at(-1)).toEqual({ type: 'zoomLens', on: false });
        adapter.dispose();
    });

    it('stops listening after dispose', () => {
        const { adapter, intents, key } = setup();

        adapter.dispose();
        key('keydown', 'KeyW');

        expect(intents).toEqual([]);
    });
});

describe('KeyboardMouseAdapter: guided walk', () => {
    const guided = { nav: () => 'guided' as const };

    it('steps a bay per press and four with Shift', () => {
        const { adapter, intents, key } = setup(guided);

        key('keydown', 'KeyW');
        key('keydown', 'KeyW', { shiftKey: true });
        key('keydown', 'KeyS');
        key('keydown', 'KeyA');
        key('keydown', 'KeyD');

        expect(intents).toEqual([
            { type: 'step', forward: 1, strafe: 0 },
            { type: 'step', forward: 4, strafe: 0 },
            { type: 'step', forward: -1, strafe: 0 },
            { type: 'step', forward: 0, strafe: -1 },
            { type: 'step', forward: 0, strafe: 1 },
        ]);
        adapter.dispose();
    });

    it('turns on Q / E and changes shelf on Space / Z', () => {
        const { adapter, intents, key } = setup(guided);

        key('keydown', 'KeyQ');
        key('keydown', 'KeyE');
        key('keydown', 'Space');
        key('keydown', 'KeyZ');

        expect(intents).toEqual([
            { type: 'turn', quarters: 1 },
            { type: 'turn', quarters: -1 },
            { type: 'changeLevel', delta: 1 },
            { type: 'changeLevel', delta: -1 },
        ]);
        adapter.dispose();
    });
});

describe('KeyboardMouseAdapter: overview', () => {
    it('pans with held keys and zooms with + and -', () => {
        const { adapter, intents, key } = setup({ mode: () => 'overview' });

        key('keydown', 'KeyW');
        key('keydown', 'Equal');
        key('keydown', 'Minus');

        expect(intents).toEqual([
            { type: 'move', source: 'keyboard', forward: 1, strafe: 0 },
            { type: 'zoomStep', factor: 0.85 },
            { type: 'zoomStep', factor: 1.18 },
        ]);
        adapter.dispose();
    });

    it('does not offer walk-only shortcuts', () => {
        const { adapter, intents, key } = setup({ mode: () => 'overview' });

        key('keydown', 'KeyG');
        key('keydown', 'KeyO');
        key('keydown', 'KeyF');

        expect(
            intents.filter((i) =>
                ['toggleNav', 'goOverview', 'inspect'].includes(i.type),
            ),
        ).toEqual([]);
        adapter.dispose();
    });
});

describe('KeyboardMouseAdapter: mouse', () => {
    function pointer(
        type: string,
        init: Partial<PointerEventInit> & {
            clientX?: number;
            clientY?: number;
        } = {},
    ) {
        const event = new MouseEvent(type, {
            bubbles: true,
            cancelable: true,
            ...init,
        }) as MouseEvent & {
            pointerType?: string;
            pointerId?: number;
        };

        event.pointerType = 'mouse';
        event.pointerId = 1;

        return event;
    }

    it('reports a still click as a tap and a drag as drags', () => {
        const { adapter, canvas, intents } = setup({ mode: () => 'overview' });

        canvas.dispatchEvent(
            pointer('pointerdown', { clientX: 10, clientY: 10, button: 0 }),
        );
        canvas.dispatchEvent(
            pointer('pointerup', { clientX: 10, clientY: 10, button: 0 }),
        );

        expect(intents.at(-1)).toEqual({
            type: 'tap',
            x: 10,
            y: 10,
            pointer: 'mouse',
        });

        intents.length = 0;
        canvas.dispatchEvent(
            pointer('pointerdown', { clientX: 10, clientY: 10, button: 0 }),
        );
        canvas.dispatchEvent(
            pointer('pointermove', { clientX: 40, clientY: 25 }),
        );
        canvas.dispatchEvent(
            pointer('pointerup', { clientX: 40, clientY: 25, button: 0 }),
        );

        expect(intents).toEqual([
            {
                type: 'drag',
                dx: 30,
                dy: 15,
                secondary: false,
                pointer: 'mouse',
            },
            { type: 'dragEnd', moved: true },
        ]);
        adapter.dispose();
    });

    it('treats the right button as a secondary drag, and in walk as the scope', () => {
        const overview = setup({ mode: () => 'overview' });

        overview.canvas.dispatchEvent(pointer('pointerdown', { button: 2 }));
        overview.canvas.dispatchEvent(
            pointer('pointermove', { clientX: 5, clientY: 0 }),
        );

        expect(overview.intents[0]).toMatchObject({
            type: 'drag',
            secondary: true,
        });
        overview.adapter.dispose();

        const walk = setup();

        walk.canvas.dispatchEvent(pointer('pointerdown', { button: 2 }));
        walk.canvas.dispatchEvent(pointer('pointerup', { button: 2 }));

        expect(walk.intents).toEqual([
            { type: 'zoomLens', on: true },
            { type: 'zoomLens', on: false },
        ]);
        walk.adapter.dispose();
    });

    it('reports hover only while the mouse is free', () => {
        const { adapter, canvas, intents } = setup({ mode: () => 'overview' });

        canvas.dispatchEvent(
            pointer('pointermove', { clientX: 7, clientY: 9 }),
        );

        expect(intents).toEqual([{ type: 'hover', x: 7, y: 9 }]);
        adapter.dispose();
    });

    it('turns wheel notches into wheel intents', () => {
        const { adapter, canvas, intents } = setup();
        const wheel = new WheelEvent('wheel', {
            deltaY: 120,
            cancelable: true,
            bubbles: true,
        });

        canvas.dispatchEvent(wheel);

        expect(intents).toEqual([{ type: 'wheel', delta: 120 }]);
        expect(wheel.defaultPrevented).toBe(true);
        adapter.dispose();
    });

    it('looks around while the mouse is captured, clamping a wild delta', () => {
        const { adapter, canvas, intents } = setup();

        Object.defineProperty(document, 'pointerLockElement', {
            configurable: true,
            get: () => canvas,
        });
        document.exitPointerLock = vi.fn();
        document.dispatchEvent(
            new MouseEvent('mousemove', {
                movementX: 500,
                movementY: -3,
            } as MouseEventInit),
        );

        expect(intents).toEqual([
            { type: 'look', dx: 120, dy: -3, rate: 0.0022 },
        ]);

        intents.length = 0;
        document.dispatchEvent(new MouseEvent('mousedown', { button: 0 }));
        expect(intents).toEqual([{ type: 'inspect' }]);
        expect(adapter.captured).toBe(true);
        adapter.dispose();
        expect(document.exitPointerLock).toHaveBeenCalled();
        Reflect.deleteProperty(document, 'pointerLockElement');
    });

    it('does not look when the mouse is free', () => {
        const { adapter, intents } = setup();

        document.dispatchEvent(
            new MouseEvent('mousemove', {
                movementX: 5,
                movementY: 5,
            } as MouseEventInit),
        );
        document.dispatchEvent(new MouseEvent('mousedown', { button: 0 }));

        expect(intents).toEqual([]);
        adapter.dispose();
    });

    it('tells the scene when the browser takes or gives back the mouse', () => {
        const { adapter, intents } = setup();

        document.dispatchEvent(new Event('pointerlockchange'));

        expect(intents).toEqual([{ type: 'captureChange', captured: false }]);
        adapter.dispose();
    });
});

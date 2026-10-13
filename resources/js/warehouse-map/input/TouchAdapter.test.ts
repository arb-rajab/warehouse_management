import { describe, expect, it } from 'vitest';
import type { InputContext, Intent } from './Intent';
import { TouchAdapter } from './TouchAdapter';
import type { PadAction } from './TouchAdapter';

function setup(context: Partial<InputContext> = {}) {
    const canvas = document.createElement('div');
    const stick = document.createElement('div');
    const liftUp = document.createElement('button');
    const liftDown = document.createElement('button');
    const padButton = document.createElement('button');
    const ctx: InputContext = {
        isSceneInteractive: () => true,
        mode: () => 'walk',
        nav: () => 'free',
        aisleCount: () => 3,
        ...context,
    };

    canvas.getBoundingClientRect = () => ({ width: 1000 }) as DOMRect;
    document.body.append(canvas, stick, liftUp, liftDown, padButton);

    let clock = 0;
    const adapter = new TouchAdapter(
        {
            canvas,
            stick,
            liftUp,
            liftDown,
            padButtons: [{ element: padButton, action: 'fw' as PadAction }],
        },
        ctx,
        () => clock,
    );
    const intents: Intent[] = [];

    adapter.onIntent((i) => intents.push(i));
    adapter.mount();

    const touch = (el: HTMLElement, type: string, x = 0, y = 0, id = 1) => {
        const event = new MouseEvent(type, {
            clientX: x,
            clientY: y,
            bubbles: true,
            cancelable: true,
        }) as MouseEvent & {
            pointerType?: string;
            pointerId?: number;
        };

        event.pointerType = 'touch';
        event.pointerId = id;
        el.dispatchEvent(event);
    };

    return {
        adapter,
        canvas,
        stick,
        liftUp,
        padButton,
        intents,
        touch,
        tick: (ms: number) => (clock += ms),
    };
}

describe('TouchAdapter', () => {
    it('walks with a floating joystick under the left thumb', () => {
        const { adapter, canvas, stick, intents, touch } = setup();

        touch(canvas, 'pointerdown', 100, 400);
        expect(stick.classList.contains('is-active')).toBe(true);
        expect(stick.style.getPropertyValue('--wms-stick-left')).toBe('100px');

        touch(canvas, 'pointermove', 100, 344); // up by a full radius
        const move = intents.at(-1) as Extract<Intent, { type: 'move' }>;

        expect(move.source).toBe('touch');
        expect(move.forward).toBeCloseTo(1);
        expect(move.strafe).toBeCloseTo(0);

        touch(canvas, 'pointerup', 100, 344);
        expect(intents.at(-1)).toEqual({
            type: 'move',
            source: 'touch',
            forward: 0,
            strafe: 0,
        });
        expect(stick.classList.contains('is-active')).toBe(false);
        adapter.dispose();
    });

    it('drags to look under the right thumb', () => {
        const { adapter, canvas, intents, touch } = setup();

        touch(canvas, 'pointerdown', 800, 300);
        touch(canvas, 'pointermove', 830, 310);
        touch(canvas, 'pointerup', 830, 310);

        expect(intents).toEqual([
            {
                type: 'drag',
                dx: 30,
                dy: 10,
                secondary: false,
                pointer: 'touch',
            },
            { type: 'dragEnd', moved: true },
        ]);
        adapter.dispose();
    });

    it('reports a quick still touch as a tap', () => {
        const { adapter, canvas, intents, touch, tick } = setup();

        touch(canvas, 'pointerdown', 800, 300);
        tick(100);
        touch(canvas, 'pointerup', 800, 300);

        expect(intents).toEqual([
            { type: 'tap', x: 800, y: 300, pointer: 'touch' },
        ]);
        adapter.dispose();
    });

    it('does not tap on a long press', () => {
        const { adapter, canvas, intents, touch, tick } = setup();

        touch(canvas, 'pointerdown', 800, 300);
        tick(900);
        touch(canvas, 'pointerup', 800, 300);

        expect(intents).toEqual([{ type: 'dragEnd', moved: false }]);
        adapter.dispose();
    });

    it('pinches with two fingers', () => {
        const { adapter, canvas, intents, touch } = setup({
            mode: () => 'overview',
        });

        touch(canvas, 'pointerdown', 400, 300, 1);
        touch(canvas, 'pointerdown', 600, 300, 2);
        intents.length = 0;
        touch(canvas, 'pointermove', 350, 300, 1); // fingers spread from 200 to 250

        const pinch = intents.find((i) => i.type === 'pinch') as Extract<
            Intent,
            { type: 'pinch' }
        >;

        expect(pinch.ratio).toBeCloseTo(200 / 250);
        adapter.dispose();
    });

    it('holds a lift button and lets go', () => {
        const { adapter, liftUp, intents, touch } = setup();

        touch(liftUp, 'pointerdown');
        touch(liftUp, 'pointerup');

        expect(intents).toEqual([
            { type: 'lift', source: 'touch', value: 1 },
            { type: 'lift', source: 'touch', value: 0 },
        ]);
        adapter.dispose();
    });

    it('fires a pad step at once and keeps stepping while held, until dispose', async () => {
        const { adapter, padButton, intents, touch } = setup();

        touch(padButton, 'pointerdown');
        expect(intents).toEqual([{ type: 'step', forward: 1, strafe: 0 }]);

        await new Promise((resolve) => setTimeout(resolve, 700));
        expect(intents.length).toBeGreaterThanOrEqual(2);

        adapter.dispose();
        const after = intents.length;

        await new Promise((resolve) => setTimeout(resolve, 400));
        expect(intents).toHaveLength(after);
    });

    it('ignores mouse pointers and touches during a fly', () => {
        const { adapter, canvas, intents } = setup({ mode: () => 'fly' });
        const event = new MouseEvent('pointerdown', {
            bubbles: true,
        }) as MouseEvent & { pointerType?: string; pointerId?: number };

        event.pointerType = 'touch';
        event.pointerId = 1;
        canvas.dispatchEvent(event);

        expect(intents).toEqual([]);
        adapter.dispose();
    });
});

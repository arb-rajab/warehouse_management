import { describe, expect, it } from 'vitest';
import { GamepadAdapter } from './GamepadAdapter';
import type { GamepadLike } from './GamepadAdapter';
import type { Intent } from './Intent';

function pad(axes: number[], pressed: number[] = []): GamepadLike {
    return {
        connected: true,
        axes,
        buttons: Array.from({ length: 8 }, (_, i) => ({
            pressed: pressed.includes(i),
        })),
    };
}

function setup(pads: Array<GamepadLike | null>) {
    const state = { pads };
    const adapter = new GamepadAdapter(() => state.pads);
    const intents: Intent[] = [];

    adapter.onIntent((i) => intents.push(i));

    return { state, adapter, intents };
}

describe('GamepadAdapter', () => {
    it('emits nothing without a pad', () => {
        const { adapter, intents } = setup([null]);

        adapter.poll();

        expect(intents).toEqual([]);
    });

    it('walks with the left stick, ignoring the dead zone', () => {
        const { state, adapter, intents } = setup([pad([0.1, -0.1, 0, 0])]);

        adapter.poll();
        expect(intents).toEqual([]);

        state.pads = [pad([0.5, -0.9, 0, 0])];
        adapter.poll();
        expect(intents).toEqual([
            { type: 'move', source: 'gamepad', forward: 0.9, strafe: 0.5 },
        ]);

        adapter.poll();
        expect(intents).toHaveLength(1);

        state.pads = [pad([0, 0, 0, 0])];
        adapter.poll();
        expect(intents.at(-1)).toEqual({
            type: 'move',
            source: 'gamepad',
            forward: 0,
            strafe: 0,
        });
    });

    it('lifts with the bumpers and sprints with the trigger', () => {
        const { state, adapter, intents } = setup([pad([0, 0, 0, 0], [5, 6])]);

        adapter.poll();
        expect(intents).toContainEqual({
            type: 'lift',
            source: 'gamepad',
            value: 1,
        });
        expect(intents).toContainEqual({
            type: 'sprint',
            source: 'gamepad',
            on: true,
        });

        state.pads = [pad([0, 0, 0, 0], [4])];
        adapter.poll();
        expect(intents).toContainEqual({
            type: 'lift',
            source: 'gamepad',
            value: -1,
        });
        expect(intents.at(-1)).toEqual({
            type: 'sprint',
            source: 'gamepad',
            on: false,
        });
    });

    it('turns continuously with the right stick, then stops once', () => {
        const { state, adapter, intents } = setup([pad([0, 0, 0.6, -0.4])]);

        adapter.poll();
        adapter.poll();
        expect(intents).toEqual([
            { type: 'lookRate', x: 0.6, y: -0.4 },
            { type: 'lookRate', x: 0.6, y: -0.4 },
        ]);

        state.pads = [pad([0, 0, 0, 0])];
        adapter.poll();
        adapter.poll();
        expect(intents.at(-1)).toEqual({ type: 'lookRate', x: 0, y: 0 });
        expect(intents).toHaveLength(3);
    });

    it('skips a pad that is not connected', () => {
        const dead = { ...pad([1, 1, 1, 1]), connected: false };
        const { adapter, intents } = setup([dead]);

        adapter.poll();

        expect(intents).toEqual([]);
    });
});

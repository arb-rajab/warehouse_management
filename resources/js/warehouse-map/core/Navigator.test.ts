import { describe, expect, it, vi } from 'vitest';
import type { Slot } from '../domain/types';
import { Navigator } from './Navigator';
import type { NavigatorDeps } from './Navigator';

const slot = { id: 1, row: 'A', col: 1, level: 1 } as Slot;

function setup(options: { view?: '2d' | '3d'; ensure?: () => void } = {}) {
    let view: '2d' | '3d' = options.view ?? '2d';
    const calls: string[] = [];
    const frames: Array<() => void> = [];
    const deps = {
        view: () => view,
        setView: vi.fn((v: '2d' | '3d') => {
            view = v;
            calls.push(`view:${v}`);
        }),
        setTarget: vi.fn(),
        closeRack: vi.fn(),
        scene: {
            isBuilt: false,
            ensure: vi.fn(options.ensure ?? (() => {})),
            show: vi.fn(() => calls.push('scene.show')),
            hide: vi.fn(() => calls.push('scene.hide')),
            focusSlot: vi.fn(() => calls.push('scene.focus')),
        } as unknown as NavigatorDeps['scene'],
        plan: {
            reveal: vi.fn(() => calls.push('plan.reveal')),
            fit: vi.fn(),
            zoomBy: vi.fn(),
            rotate: vi.fn(),
        },
        fontsReady: () => Promise.resolve(),
        nextFrame: (cb: () => void) => frames.push(cb),
        onSceneFailed: vi.fn(),
        writeHash: vi.fn(),
    };

    return {
        nav: new Navigator(deps),
        deps,
        calls,
        flushFrames: () => frames.splice(0).forEach((f) => f()),
        getView: () => view,
    };
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('Navigator', () => {
    it('shows the 3D scene once the fonts are ready, then runs the follow-up', async () => {
        const { nav, calls, deps } = setup();
        const then = vi.fn();

        nav.setView('3d', then);
        expect(then).not.toHaveBeenCalled();

        await flush();

        expect(calls).toEqual(['view:3d', 'scene.show']);
        expect(deps.scene.ensure).toHaveBeenCalled();
        expect(deps.writeHash).toHaveBeenCalledWith('3d');
        expect(then).toHaveBeenCalled();
    });

    it('hides the scene when going to 2D and waits a frame for the plan to be on screen', () => {
        const { nav, calls, flushFrames } = setup({ view: '3d' });
        const then = vi.fn();

        nav.setView('2d', then);

        expect(calls).toEqual(['scene.hide', 'view:2d']);
        expect(then).not.toHaveBeenCalled();

        flushFrames();
        expect(then).toHaveBeenCalled();
    });

    it('falls back to 2D and says so when the scene cannot start', async () => {
        const boom = new Error('no WebGL');
        const { nav, deps, getView } = setup({
            ensure: () => {
                throw boom;
            },
        });
        const then = vi.fn();

        nav.setView('3d', then);
        await flush();

        expect(getView()).toBe('2d');
        expect(deps.onSceneFailed).toHaveBeenCalledWith(boom);
        expect(then).not.toHaveBeenCalled();
    });

    it('ignores a switch that was overtaken by a newer one', async () => {
        const { nav, calls } = setup();

        nav.setView('3d');
        nav.setView('2d');
        await flush();

        expect(calls).toEqual(['scene.hide', 'view:2d']);
    });

    it('walks to a slot in 3D from the rack dialog', async () => {
        const { nav, deps, calls } = setup();

        nav.showIn3d(slot);
        await flush();

        expect(deps.closeRack).toHaveBeenCalled();
        expect(calls).toEqual(['view:3d', 'scene.show', 'scene.focus']);
    });

    it('shows a slot on the plan: target it, switch, centre on it', () => {
        const { nav, deps, calls, flushFrames } = setup({ view: '3d' });

        nav.showInPlan(slot);
        flushFrames();

        expect(deps.setTarget).toHaveBeenCalledWith(slot);
        expect(calls).toEqual(['scene.hide', 'view:2d', 'plan.reveal']);
    });

    it('"view on map" goes to the slot in whichever view is active', () => {
        const in3d = setup({ view: '3d' });

        in3d.nav.viewOnMap(slot);
        expect(in3d.calls).toEqual(['scene.focus']);

        const in2d = setup({ view: '2d' });

        in2d.nav.viewOnMap(slot);
        in2d.flushFrames();
        expect(in2d.calls).toContain('plan.reveal');
    });
});

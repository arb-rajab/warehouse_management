import { describe, expect, it, vi } from 'vitest';
import { FrameLoop } from './FrameLoop';

function manualRaf() {
    let callback: FrameRequestCallback | null = null;
    let nextHandle = 1;
    const cancelled: number[] = [];

    return {
        raf: (cb: FrameRequestCallback) => {
            callback = cb;

            return nextHandle++;
        },
        caf: (handle: number) => cancelled.push(handle),
        cancelled,
        frame(now: number) {
            const cb = callback;

            callback = null;
            cb?.(now);
        },
        get pending() {
            return callback !== null;
        },
    };
}

describe('FrameLoop', () => {
    it('runs every task on every frame, with a clamped delta', () => {
        const loop = manualRaf();
        const frames = new FrameLoop(loop.raf, loop.caf);
        const task = vi.fn();

        frames.onFrame(task);
        frames.mount();
        vi.spyOn(performance, 'now').mockReturnValue(0);
        loop.frame(16);
        loop.frame(5000);

        expect(task).toHaveBeenCalledTimes(2);
        expect(task.mock.calls[1][1]).toBeLessThanOrEqual(0.05);
    });

    it('draws only after a render was requested', () => {
        const loop = manualRaf();
        const frames = new FrameLoop(loop.raf, loop.caf);
        const render = vi.fn();

        frames.onRender(render);
        frames.mount();
        loop.frame(16); // starts dirty
        loop.frame(32);
        expect(render).toHaveBeenCalledTimes(1);

        frames.requestRender();
        loop.frame(48);
        expect(render).toHaveBeenCalledTimes(2);
        expect(frames.needsRender).toBe(false);
    });

    it('stops everything on dispose', () => {
        const loop = manualRaf();
        const frames = new FrameLoop(loop.raf, loop.caf);
        const task = vi.fn();

        frames.onFrame(task);
        frames.mount();
        frames.dispose();
        loop.frame(16);

        expect(task).not.toHaveBeenCalled();
        expect(loop.cancelled).toHaveLength(1);
    });

    it('lets a task unregister itself', () => {
        const loop = manualRaf();
        const frames = new FrameLoop(loop.raf, loop.caf);
        const task = vi.fn();
        const off = frames.onFrame(task);

        frames.mount();
        loop.frame(16);
        off();
        loop.frame(32);

        expect(task).toHaveBeenCalledTimes(1);
    });
});

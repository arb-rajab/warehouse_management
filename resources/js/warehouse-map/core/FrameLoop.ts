export type FrameTask = (now: number, deltaSeconds: number) => void;

export interface RenderRequester {
    requestRender(): void;
}

/**
 * The only owner of `requestAnimationFrame`. Tasks run every frame while they
 * are registered; a render task only draws when something called
 * `requestRender()`, so an idle page costs nothing.
 */
export class FrameLoop implements RenderRequester {
    private handle = 0;
    private running = false;
    private last = 0;
    private dirty = true;
    private readonly tasks = new Set<FrameTask>();
    private readonly renderers = new Set<(now: number) => void>();

    constructor(
        private readonly raf: (cb: FrameRequestCallback) => number = (cb) =>
            requestAnimationFrame(cb),
        private readonly caf: (handle: number) => void = (handle) =>
            cancelAnimationFrame(handle),
    ) {}

    mount(): void {
        if (this.running) {
            return;
        }

        this.running = true;
        this.last = performance.now();
        this.handle = this.raf((now) => this.tick(now));
    }

    dispose(): void {
        this.running = false;
        this.caf(this.handle);
        this.tasks.clear();
        this.renderers.clear();
    }

    /** Runs `task` on every frame (input polling, camera integration). */
    onFrame(task: FrameTask): () => void {
        this.tasks.add(task);

        return () => this.tasks.delete(task);
    }

    /** Runs `render` only on frames that follow a `requestRender()`. */
    onRender(render: (now: number) => void): () => void {
        this.renderers.add(render);

        return () => this.renderers.delete(render);
    }

    requestRender(): void {
        this.dirty = true;
    }

    get needsRender(): boolean {
        return this.dirty;
    }

    private tick(now: number): void {
        if (!this.running) {
            return;
        }

        const dt = Math.min(0.05, (now - this.last) / 1000);

        this.last = now;

        for (const task of [...this.tasks]) {
            task(now, dt);
        }

        if (this.dirty) {
            this.dirty = false;

            for (const render of [...this.renderers]) {
                render(now);
            }
        }

        this.handle = this.raf((time) => this.tick(time));
    }
}

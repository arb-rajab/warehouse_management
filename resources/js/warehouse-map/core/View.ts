/** Every module has `mount()` and `dispose()`; leaving the page leaves nothing running. */
export interface Mountable {
    mount(): void;
    dispose(): void;
}

/**
 * Base for modules that own listeners: one AbortController per module removes
 * every DOM listener at once, and `own()` collects the rest (store
 * subscriptions, timers) to undo in reverse order.
 */
export abstract class View implements Mountable {
    private readonly controller = new AbortController();
    private cleanups: Array<() => void> = [];
    private disposed = false;

    abstract mount(): void;

    protected get signal(): AbortSignal {
        return this.controller.signal;
    }

    /** Adds a DOM listener that dies with the module. */
    protected on<T extends EventTarget>(
        target: T,
        type: string,
        handler: (event: any) => void,
        options: AddEventListenerOptions = {},
    ): void {
        target.addEventListener(type, handler as EventListener, {
            ...options,
            signal: this.signal,
        });
    }

    /** Registers something to undo on dispose; returns it for chaining. */
    protected own<T extends (() => void) | undefined>(cleanup: T): T {
        if (cleanup) {
            this.cleanups.push(cleanup);
        }

        return cleanup;
    }

    protected get isDisposed(): boolean {
        return this.disposed;
    }

    /** Hook for subclasses to release their own resources. */
    protected onDispose(): void {}

    dispose(): void {
        if (this.disposed) {
            return;
        }

        this.disposed = true;
        this.controller.abort();

        for (const cleanup of this.cleanups.reverse()) {
            cleanup();
        }

        this.cleanups = [];
        this.onDispose();
    }
}

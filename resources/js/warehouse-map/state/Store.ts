export type Listener<T> = (value: T, previous: T) => void;

interface Subscription<S> {
    select: (state: S) => unknown;
    listener: Listener<any>;
    equals: (a: any, b: any) => boolean;
    last: unknown;
}

/**
 * A small observable store. State only changes through `update()`; a view
 * subscribes to the slice it renders with `select()` and is told only when
 * that slice changed. Views never hold the store itself, only their slice.
 */
export class Store<S extends object> {
    private state: S;
    private readonly subscriptions = new Set<Subscription<S>>();

    constructor(initial: S) {
        this.state = initial;
    }

    get(): S {
        return this.state;
    }

    update(patch: Partial<S> | ((state: S) => Partial<S>)): void {
        const next = typeof patch === 'function' ? patch(this.state) : patch;
        const changed = (Object.keys(next) as Array<keyof S>).some(
            (key) => !Object.is(this.state[key], next[key]),
        );

        if (!changed) {
            return;
        }

        this.state = { ...this.state, ...next };

        for (const subscription of [...this.subscriptions]) {
            if (!this.subscriptions.has(subscription)) {
                continue;
            }

            const value = subscription.select(this.state);

            if (!subscription.equals(value, subscription.last)) {
                const previous = subscription.last;

                subscription.last = value;
                subscription.listener(value, previous);
            }
        }
    }

    /**
     * Calls `listener` whenever `select(state)` changes (by `equals`, `Object.is`
     * by default). With `immediate`, also calls it once now.
     */
    select<T>(
        select: (state: S) => T,
        listener: Listener<T>,
        options: { equals?: (a: T, b: T) => boolean; immediate?: boolean } = {},
    ): () => void {
        const subscription: Subscription<S> = {
            select,
            listener,
            equals: options.equals ?? Object.is,
            last: select(this.state),
        };

        this.subscriptions.add(subscription);

        if (options.immediate) {
            listener(subscription.last as T, subscription.last as T);
        }

        return () => this.subscriptions.delete(subscription);
    }
}

/** Shallow equality for slices made of several primitives. */
export function shallowEqual<T extends object>(a: T, b: T): boolean {
    const keysA = Object.keys(a) as Array<keyof T>;

    return (
        keysA.length === Object.keys(b).length &&
        keysA.every((key) => Object.is(a[key], b[key]))
    );
}

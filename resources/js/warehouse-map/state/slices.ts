import { shallowEqual } from './Store';
import type { Store } from './Store';

/**
 * A read-only window onto the part of the state a view renders. Views get a
 * slice, never the store: they can read it and hear about changes, nothing else.
 */
export interface Slice<T> {
    get(): T;
    subscribe(listener: (value: T, previous: T) => void): () => void;
}

export function createSlice<S extends object, T extends object>(
    store: Store<S>,
    select: (state: S) => T,
    equals: (a: T, b: T) => boolean = shallowEqual,
): Slice<T> {
    return {
        get: () => select(store.get()),
        subscribe: (listener) => store.select(select, listener, { equals }),
    };
}

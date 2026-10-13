import { describe, expect, it, vi } from 'vitest';
import { Store, shallowEqual } from './Store';

describe('Store', () => {
    it('merges a patch and tells only the slices that changed', () => {
        const store = new Store({ a: 1, b: 'x' });
        const onA = vi.fn();
        const onB = vi.fn();

        store.select((s) => s.a, onA);
        store.select((s) => s.b, onB);
        store.update({ a: 2 });

        expect(store.get()).toEqual({ a: 2, b: 'x' });
        expect(onA).toHaveBeenCalledWith(2, 1);
        expect(onB).not.toHaveBeenCalled();
    });

    it('accepts an updater function', () => {
        const store = new Store({ n: 1 });

        store.update((s) => ({ n: s.n + 1 }));

        expect(store.get().n).toBe(2);
    });

    it('does nothing when no value actually changed', () => {
        const store = new Store({ a: 1 });
        const listener = vi.fn();

        store.select((s) => s.a, listener);
        store.update({ a: 1 });

        expect(listener).not.toHaveBeenCalled();
    });

    it('can call a new subscriber at once', () => {
        const store = new Store({ a: 1 });
        const listener = vi.fn();

        store.select((s) => s.a, listener, { immediate: true });

        expect(listener).toHaveBeenCalledWith(1, 1);
    });

    it('stops telling a subscriber that unsubscribed', () => {
        const store = new Store({ a: 1 });
        const listener = vi.fn();
        const off = store.select((s) => s.a, listener);

        off();
        store.update({ a: 2 });

        expect(listener).not.toHaveBeenCalled();
    });

    it('compares a slice with a custom equality', () => {
        const store = new Store({ p: { x: 1, y: 2 }, other: 0 });
        const listener = vi.fn();

        store.select((s) => ({ ...s.p }), listener, { equals: shallowEqual });
        store.update({ other: 1 });
        store.update({ p: { x: 1, y: 2 } });
        store.update({ p: { x: 5, y: 2 } });

        expect(listener).toHaveBeenCalledTimes(1);
    });

    it('survives a listener that updates the store', () => {
        const store = new Store({ a: 0, b: 0 });

        store.select(
            (s) => s.a,
            (a) => store.update({ b: a * 2 }),
        );
        store.update({ a: 3 });

        expect(store.get()).toEqual({ a: 3, b: 6 });
    });
});

describe('shallowEqual', () => {
    it('compares own keys by identity', () => {
        expect(shallowEqual({ a: 1 }, { a: 1 })).toBe(true);
        expect(shallowEqual({ a: 1 }, { a: 2 })).toBe(false);
        expect(shallowEqual({ a: 1 }, { a: 1, b: 2 } as never)).toBe(false);
    });
});

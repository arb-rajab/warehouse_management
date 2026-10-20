import { afterEach, describe, expect, it, vi } from 'vitest';
import { HashSync } from './HashSync';

afterEach(() => {
    window.location.hash = '';
});

describe('HashSync', () => {
    it('opens a deep link to a slot', () => {
        window.location.hash = '#slot=B5-2';

        const onTarget = vi.fn();
        const sync = new HashSync(window, onTarget);

        const target = {
            kind: 'slot',
            slot: { row: 'B', col: 5, level: 2 },
        };

        expect(sync.applyCurrent()).toEqual(target);
        expect(onTarget).toHaveBeenCalledWith(target);
        sync.dispose();
    });

    it('opens a view fragment and ignores anything else', () => {
        const onTarget = vi.fn();
        const sync = new HashSync(window, onTarget);

        window.location.hash = '#3d';
        expect(sync.applyCurrent()).toEqual({ kind: 'view', view: '3d' });
        expect(onTarget).toHaveBeenCalledWith({ kind: 'view', view: '3d' });

        window.location.hash = '#something-else';
        expect(sync.applyCurrent()).toBeNull();
        expect(onTarget).toHaveBeenCalledTimes(1);
        sync.dispose();
    });

    it('opens a filter link for several products', () => {
        window.location.hash = '#products=4,9';

        const onTarget = vi.fn();
        const sync = new HashSync(window, onTarget);
        const target = {
            kind: 'filter',
            filter: { productIds: [4, 9], state: null },
        };

        expect(sync.applyCurrent()).toEqual(target);
        expect(onTarget).toHaveBeenCalledWith(target);
        sync.dispose();
    });

    it('follows the fragment when it changes while the page is open', () => {
        const onTarget = vi.fn();
        const sync = new HashSync(window, onTarget);

        sync.mount();
        window.location.hash = '#slot=A1-1';
        window.dispatchEvent(new HashChangeEvent('hashchange'));

        expect(onTarget).toHaveBeenCalled();
        sync.dispose();
    });

    it('writes the view without losing the history state the panel keeps', () => {
        const sync = new HashSync(window, vi.fn());

        window.history.replaceState(
            { page: 'panel' },
            '',
            window.location.pathname,
        );
        sync.writeView('3d');

        expect(window.location.hash).toBe('#3d');
        expect(window.history.state).toEqual({ page: 'panel' });
        sync.dispose();
    });

    it('builds a shareable link to a slot', () => {
        window.location.hash = '#3d';

        expect(
            HashSync.slotLink(window, { row: 'A', col: 5, level: 2 }),
        ).toMatch(/#slot=A5-2$/);
        expect(
            HashSync.slotLink(window, { row: 'A', col: 5, level: 2 }),
        ).not.toContain('#3d');
    });
});

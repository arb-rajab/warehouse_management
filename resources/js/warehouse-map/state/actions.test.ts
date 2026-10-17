import { describe, expect, it } from 'vitest';
import { normalizeRows } from '../domain/normalizeRows';
import { createActions } from './actions';
import {
    createMatchSelector,
    createProductOptionsSelector,
    selectKeyHintKind,
    selectTarget,
} from './selectors';
import { initialState } from './state';
import type { MapState } from './state';
import { Store } from './Store';

function setup(overrides: Partial<MapState> = {}) {
    const store = new Store<MapState>(initialState(overrides));

    return { store, actions: createActions(store) };
}

const warehouse = normalizeRows([
    {
        id: 1,
        letter: 'A',
        cells: [
            {
                id: 1,
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: {
                    id: 9,
                    product_id: 1,
                    expiration_date: null,
                    product_name: 'Basmati Rice',
                },
            },
            { id: 2, cell_number: 1, flat_number: 2, state: 'empty' },
        ],
    },
]);

describe('search and filter', () => {
    it('turns the typed text into words only when committed', () => {
        const { store, actions } = setup();

        actions.setSearchText('Basmati  rice');
        expect(store.get().filter.terms).toEqual([]);

        actions.commitSearch();
        expect(store.get().filter.terms).toEqual(['basmati', 'rice']);
        expect(store.get().query).toBe('Basmati  rice');
    });

    it('clears the search text and the words', () => {
        const { store, actions } = setup();

        actions.setSearchText('rice');
        actions.commitSearch();
        actions.clearSearch();

        expect(store.get().query).toBe('');
        expect(store.get().filter.terms).toEqual([]);
    });

    it('keeps the other filters when one changes', () => {
        const { store, actions } = setup();

        actions.setStateFilter('opened');
        actions.toggleExpiring();
        actions.setSearchText('x');
        actions.commitSearch();

        expect(store.get().filter).toEqual({
            terms: ['x'],
            state: 'opened',
            expiring: true,
            productIds: [],
        });

        actions.toggleExpiring();
        expect(store.get().filter.expiring).toBe(false);
    });

    it('keeps the filter, the floor and the target when switching view', () => {
        const { store, actions } = setup({ warehouse });

        actions.setStateFilter('full');
        actions.setFloor(2);
        actions.setTarget(warehouse.slots[0]);
        actions.setView('3d');
        actions.setView('2d');

        expect(store.get().filter.state).toBe('full');
        expect(store.get().floor).toBe(2);
        expect(selectTarget(store.get())?.id).toBe(1);
    });
});

describe('the product picker', () => {
    it('checks and unchecks products, keeping the other filters', () => {
        const { store, actions } = setup();

        actions.setStateFilter('full');
        actions.toggleProduct(4);
        actions.toggleProduct(9);
        expect(store.get().filter.productIds).toEqual([4, 9]);

        actions.toggleProduct(4);
        expect(store.get().filter.productIds).toEqual([9]);
        expect(store.get().filter.state).toBe('full');

        actions.clearProducts();
        expect(store.get().filter.productIds).toEqual([]);
        expect(store.get().filter.state).toBe('full');
    });

    it('takes the products, and a named state, from a filter link', () => {
        const { store, actions } = setup();

        actions.setStateFilter('opened');
        actions.toggleProduct(1);
        actions.applyFilterLink({ productIds: [2, 3], state: null });
        expect(store.get().filter).toMatchObject({
            productIds: [2, 3],
            state: 'opened',
        });

        actions.applyFilterLink({ productIds: [5], state: 'full' });
        expect(store.get().filter).toMatchObject({
            productIds: [5],
            state: 'full',
        });
    });

    it('narrows the match index to the checked products', () => {
        const { store, actions } = setup({ warehouse });
        const matchOf = createMatchSelector();

        actions.toggleProduct(1);
        expect([...matchOf(store.get()).ids]).toEqual([1]);

        actions.toggleProduct(1);
        actions.toggleProduct(2);
        expect(matchOf(store.get()).count).toBe(0);
    });

    it('lists the products again only when the warehouse changed', () => {
        const { store, actions } = setup({ warehouse });
        const optionsOf = createProductOptionsSelector();
        const first = optionsOf(store.get());

        expect(first).toEqual([
            {
                id: 1,
                name: 'Basmati Rice',
                arName: '',
                active: true,
                pallets: 1,
            },
        ]);

        actions.toggleProduct(1);
        expect(optionsOf(store.get())).toBe(first);

        actions.setWarehouse(normalizeRows([]));
        expect(optionsOf(store.get())).toEqual([]);
    });
});

describe('views and overlays', () => {
    it('forgets a 3D selection and closes the drawer when going to 2D', () => {
        const { store, actions } = setup();

        actions.patchScene({ selectedId: 5 });
        actions.setDrawerOpen(true);
        actions.setView('2d');

        expect(store.get().scene.selectedId).toBeNull();
        expect(store.get().drawerOpen).toBe(false);
    });

    it('closes the topmost overlay first on Escape', () => {
        const { store, actions } = setup();

        actions.openRack({ row: 'A', col: 1, level: null });
        actions.setHelpOpen(true);

        expect(actions.closeTopOverlay()).toBe(true);
        expect(store.get().helpOpen).toBe(false);
        expect(store.get().rack).not.toBeNull();

        expect(actions.closeTopOverlay()).toBe(true);
        expect(store.get().rack).toBeNull();

        actions.setLegendOpen(true);
        expect(actions.closeTopOverlay()).toBe(true);
        actions.setDrawerOpen(true);
        expect(actions.closeTopOverlay()).toBe(true);
        expect(actions.closeTopOverlay()).toBe(false);
    });

    it('cycles the plan orientation auto -> vertical -> horizontal', () => {
        const { actions } = setup();

        expect(actions.cyclePlanOrientation()).toBe('v');
        expect(actions.cyclePlanOrientation()).toBe('h');
        expect(actions.cyclePlanOrientation()).toBe('auto');
    });

    it('merges a settings change', () => {
        const { store, actions } = setup();

        actions.updateSettings({ fov: 70 });

        expect(store.get().settings.fov).toBe(70);
        expect(store.get().settings.sens).toBe(1);
    });

    it('numbers hints so the same text can show twice', () => {
        const { store, actions } = setup();

        actions.showHint('Copied', 2000);
        const first = store.get().hint!.seq;
        actions.showHint('Copied', 2000);

        expect(store.get().hint!.seq).toBe(first + 1);

        actions.clearHint();
        expect(store.get().hint).toBeNull();
    });
});

describe('selectors', () => {
    it('rebuilds the match index only when the warehouse or filter changed', () => {
        const { store, actions } = setup({ warehouse });
        const select = createMatchSelector();
        const idle = select(store.get());

        expect(select(store.get())).toBe(idle);

        actions.setSearchText('rice');
        actions.commitSearch();
        const filtered = select(store.get());

        expect(filtered).not.toBe(idle);
        expect(filtered.count).toBe(1);

        actions.setFloor(1);
        expect(select(store.get())).toBe(filtered);
    });

    it('chooses the key hints for the view and camera mode', () => {
        const { store, actions } = setup();

        expect(selectKeyHintKind(store.get())).toBe('plan');

        actions.setView('3d');
        expect(selectKeyHintKind(store.get())).toBe('overview');

        actions.patchScene({ mode: 'walk', nav: 'guided' });
        expect(selectKeyHintKind(store.get())).toBe('guided');

        actions.patchScene({ nav: 'free' });
        expect(selectKeyHintKind(store.get())).toBe('free');
    });
});

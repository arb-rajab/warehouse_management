import { parseTerms } from '../domain/SlotFilter';
import type { Slot, StateFilter } from '../domain/types';
import type {
    MapState,
    PlanOrientation,
    RackRef,
    SceneState,
    ViewKind,
} from './state';
import type { Store } from './Store';

const ORIENTATION_ORDER: PlanOrientation[] = ['auto', 'v', 'h'];

/**
 * The intents views publish. Each one is a small state transition; side
 * effects (persisting, camera moves, the URL) are reactions elsewhere.
 */
export function createActions(store: Store<MapState>) {
    let hintSeq = 0;

    const dialogsClosed = {
        rack: null,
        helpOpen: false,
        legendOpen: false,
        drawerOpen: false,
    } satisfies Partial<MapState>;

    const actions = {
        setWarehouse(warehouse: MapState['warehouse']): void {
            store.update({ warehouse, loadFailed: false });
        },

        setLoadFailed(failed: boolean): void {
            store.update({ loadFailed: failed });
        },

        setView(view: ViewKind): void {
            store.update((s) => ({
                view,
                drawerOpen: false,
                // a 3D selection must not follow you into 2D
                scene:
                    view === '2d' ? { ...s.scene, selectedId: null } : s.scene,
            }));
        },

        setSearchText(query: string): void {
            store.update({ query });
        },

        /** The debounced search: the typed text becomes filter words. */
        commitSearch(): void {
            store.update((s) => ({
                filter: { ...s.filter, terms: parseTerms(s.query) },
            }));
        },

        clearSearch(): void {
            store.update((s) => ({
                query: '',
                filter: { ...s.filter, terms: [] },
            }));
        },

        setStateFilter(state: StateFilter): void {
            store.update((s) => ({ filter: { ...s.filter, state } }));
        },

        toggleExpiring(): void {
            store.update((s) => ({
                filter: { ...s.filter, expiring: !s.filter.expiring },
            }));
        },

        setFloor(floor: number | null): void {
            store.update({ floor });
        },

        setTarget(slot: Slot | null): void {
            store.update({ targetId: slot?.id ?? null });
        },

        setTheme(theme: MapState['theme']): void {
            store.update({ theme });
        },

        setFolded(folded: boolean): void {
            store.update({ folded });
        },

        setSearchOpen(open: boolean): void {
            store.update({ searchOpen: open });
        },

        setInputKind(inputKind: MapState['inputKind']): void {
            store.update({ inputKind });
        },

        setLocale(locale: MapState['locale']): void {
            store.update({ locale });
        },

        cyclePlanOrientation(): PlanOrientation {
            const current = store.get().planOrientation;
            const next =
                ORIENTATION_ORDER[
                    (ORIENTATION_ORDER.indexOf(current) + 1) %
                        ORIENTATION_ORDER.length
                ];

            store.update({ planOrientation: next });

            return next;
        },

        resetPlanOrientation(): void {
            store.update({ planOrientation: 'auto' });
        },

        openRack(rack: RackRef): void {
            store.update({ rack });
        },

        closeRack(): void {
            store.update({ rack: null });
        },

        setHelpOpen(helpOpen: boolean): void {
            store.update({ helpOpen });
        },

        setLegendOpen(legendOpen: boolean): void {
            store.update({ legendOpen });
        },

        setDrawerOpen(drawerOpen: boolean): void {
            store.update({ drawerOpen });
        },

        /** Escape closes whichever overlay is on top. Returns whether it closed one. */
        closeTopOverlay(): boolean {
            const s = store.get();

            if (s.helpOpen) {
                store.update({ helpOpen: false });
            } else if (s.rack) {
                store.update({ rack: null });
            } else if (s.legendOpen) {
                store.update({ legendOpen: false });
            } else if (s.drawerOpen) {
                store.update({ drawerOpen: false });
            } else {
                return false;
            }

            return true;
        },

        closeAllOverlays(): void {
            store.update(dialogsClosed);
        },

        updateSettings(patch: Partial<MapState['settings']>): void {
            store.update((s) => ({ settings: { ...s.settings, ...patch } }));
        },

        patchScene(patch: Partial<SceneState>): void {
            store.update((s) => ({ scene: { ...s.scene, ...patch } }));
        },

        showHint(text: string, ms: number): void {
            store.update({ hint: { text, ms, seq: ++hintSeq } });
        },

        clearHint(): void {
            store.update({ hint: null });
        },

        setWelcome(welcome: boolean): void {
            store.update({ welcome });
        },

        setChromeAway(chromeAway: boolean): void {
            store.update({ chromeAway });
        },
    };

    return actions;
}

export type MapActions = ReturnType<typeof createActions>;

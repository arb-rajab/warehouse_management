import { DEFAULT_SETTINGS } from '../config';
import type { UserSettings } from '../config';
import type { Theme } from '../data/SettingsStore';
import { NO_FILTER } from '../domain/SlotFilter';
import type { Locale, SlotFilter, Warehouse } from '../domain/types';

export type ViewKind = '2d' | '3d';
export type InputKindName = 'mouse' | 'touch';
export type PlanOrientation = 'auto' | 'v' | 'h';
export type CameraModeId = 'overview' | 'walk' | 'fly';
export type NavMode = 'free' | 'guided';

export interface RackRef {
    row: string;
    col: number;
    /** The shelf to highlight, when the dialog was opened from a specific slot. */
    level: number | null;
}

export interface SceneState {
    mode: CameraModeId;
    nav: NavMode;
    activeAisle: number | null;
    /** The slot under the crosshair / cursor. */
    hoverId: number | null;
    /** Whether the hover is worth describing in the details card. */
    cardVisible: boolean;
    /** The slot picked in the overview, waiting for "Rack details" / "Walk here". */
    selectedId: number | null;
    /** True while the mouse is captured (pointer lock). */
    captured: boolean;
    minimapHidden: boolean;
    /** Where a flight in progress lands: the HUD looks like it already has. */
    landing: 'overview' | 'walk';
    /** True once the walker has moved for a few seconds (the welcome card hides). */
    walkedEnough: boolean;
}

export interface Hint {
    text: string;
    ms: number;
    /** Changes with every request so the same text can be shown twice. */
    seq: number;
}

export interface MapState {
    warehouse: Warehouse | null;
    loadFailed: boolean;
    locale: Locale;
    theme: Theme;
    view: ViewKind;
    folded: boolean;
    inputKind: InputKindName;
    planOrientation: PlanOrientation;
    settings: UserSettings;
    /** What the search box holds, as typed. */
    query: string;
    filter: SlotFilter;
    floor: number | null;
    /** The slot the yellow target marker sits on. */
    targetId: number | null;
    searchOpen: boolean;
    rack: RackRef | null;
    helpOpen: boolean;
    legendOpen: boolean;
    drawerOpen: boolean;
    scene: SceneState;
    hint: Hint | null;
    /** Shows the key-hint card until it fades away. */
    welcome: boolean;
    /** The toolbars step aside while the map is being moved. */
    chromeAway: boolean;
}

export const INITIAL_SCENE: SceneState = {
    mode: 'overview',
    nav: 'free',
    activeAisle: null,
    hoverId: null,
    cardVisible: false,
    selectedId: null,
    captured: false,
    minimapHidden: false,
    landing: 'overview',
    walkedEnough: false,
};

export function initialState(overrides: Partial<MapState> = {}): MapState {
    return {
        warehouse: null,
        loadFailed: false,
        locale: 'en',
        theme: 'dark',
        view: '2d',
        folded: false,
        inputKind: 'mouse',
        planOrientation: 'auto',
        settings: { ...DEFAULT_SETTINGS },
        query: '',
        filter: { ...NO_FILTER },
        floor: null,
        targetId: null,
        searchOpen: false,
        rack: null,
        helpOpen: false,
        legendOpen: false,
        drawerOpen: false,
        scene: { ...INITIAL_SCENE },
        hint: null,
        welcome: false,
        chromeAway: false,
        ...overrides,
    };
}

export { LAYOUT } from './domain/layout';

/** localStorage keys, namespaced so they never clash with the panel's own. */
export const STORAGE_KEYS = {
    settings: 'wms-map:settings',
    theme: 'wms-map:theme',
    folded: 'wms-map:folded',
    welcome: 'wms-map:welcome-seen',
} as const;

export const THRESHOLDS = {
    /** A plan tile smaller than this many CSS px drops its digits and shadows. */
    planLodTilePx: 36,
    planTilePx: 66,
    planMinZoom: 0.6,
    planMaxZoom: 1.6,
    searchDebounceMs: 250,
    /** The welcome card shows on this many visits only. */
    welcomeVisits: 3,
    welcomeMs: 16_000,
    keysFadeMs: 12_000,
    hintMs: 9000,
    /** 3D: a click from farther than this flies in first. */
    flyInDistance: 30,
    /** Frame budget the adaptive resolution aims for, in ms. */
    slowFrameMs: 26,
    fastFrameMs: 18,
    refetchMinIntervalMs: 15_000,
} as const;

export interface UserSettings {
    sens: number;
    speed: number;
    fov: number;
    invertY: boolean;
    bob: boolean;
    magnet: boolean;
}

export const DEFAULT_SETTINGS: UserSettings = {
    sens: 1,
    speed: 1,
    fov: 62,
    invertY: false,
    bob: true,
    magnet: true,
};

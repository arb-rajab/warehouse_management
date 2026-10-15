import { DEFAULT_SETTINGS, STORAGE_KEYS } from '../config';
import type { UserSettings } from '../config';
import type { StorageLike } from './Storage';

export type Theme = 'light' | 'dark';

/** Per-viewer conveniences, remembered in the browser. The page works without them. */
export class SettingsStore {
    constructor(private readonly storage: StorageLike) {}

    loadSettings(): UserSettings {
        try {
            const raw = JSON.parse(
                this.storage.getItem(STORAGE_KEYS.settings) ?? '{}',
            ) as Partial<UserSettings> | null;

            return { ...DEFAULT_SETTINGS, ...sanitize(raw) };
        } catch {
            return { ...DEFAULT_SETTINGS };
        }
    }

    saveSettings(settings: UserSettings): void {
        this.storage.setItem(STORAGE_KEYS.settings, JSON.stringify(settings));
    }

    loadTheme(): Theme | null {
        const value = this.storage.getItem(STORAGE_KEYS.theme);

        return value === 'light' || value === 'dark' ? value : null;
    }

    saveTheme(theme: Theme): void {
        this.storage.setItem(STORAGE_KEYS.theme, theme);
    }

    loadFolded(): boolean {
        return this.storage.getItem(STORAGE_KEYS.folded) === '1';
    }

    saveFolded(folded: boolean): void {
        this.storage.setItem(STORAGE_KEYS.folded, folded ? '1' : '0');
    }

    welcomeVisits(): number {
        return Number(this.storage.getItem(STORAGE_KEYS.welcome)) || 0;
    }

    countWelcomeVisit(): void {
        this.storage.setItem(
            STORAGE_KEYS.welcome,
            String(this.welcomeVisits() + 1),
        );
    }
}

/** Keeps only values of the right type, so a hand-edited entry cannot break the walk. */
function sanitize(raw: Partial<UserSettings> | null): Partial<UserSettings> {
    const out: Partial<UserSettings> = {};

    if (!raw || typeof raw !== 'object') {
        return out;
    }

    for (const key of ['sens', 'speed', 'fov'] as const) {
        if (typeof raw[key] === 'number' && Number.isFinite(raw[key])) {
            out[key] = raw[key];
        }
    }

    for (const key of ['invertY', 'bob', 'magnet'] as const) {
        if (typeof raw[key] === 'boolean') {
            out[key] = raw[key];
        }
    }

    return out;
}

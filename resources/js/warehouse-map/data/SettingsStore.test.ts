import { describe, expect, it } from 'vitest';
import { DEFAULT_SETTINGS, STORAGE_KEYS } from '../config';
import { SettingsStore } from './SettingsStore';
import { MemoryStorage, SafeStorage } from './Storage';

describe('SettingsStore', () => {
    it('starts from the defaults', () => {
        expect(new SettingsStore(new MemoryStorage()).loadSettings()).toEqual(
            DEFAULT_SETTINGS,
        );
    });

    it('remembers settings', () => {
        const store = new SettingsStore(new MemoryStorage());

        store.saveSettings({ ...DEFAULT_SETTINGS, sens: 1.5, invertY: true });

        expect(store.loadSettings()).toEqual({
            ...DEFAULT_SETTINGS,
            sens: 1.5,
            invertY: true,
        });
    });

    it('ignores broken or mistyped stored values', () => {
        const storage = new MemoryStorage();

        storage.setItem(STORAGE_KEYS.settings, '{not json');
        expect(new SettingsStore(storage).loadSettings()).toEqual(
            DEFAULT_SETTINGS,
        );

        storage.setItem(
            STORAGE_KEYS.settings,
            JSON.stringify({ sens: 'fast', bob: 1, fov: 70 }),
        );
        expect(new SettingsStore(storage).loadSettings()).toEqual({
            ...DEFAULT_SETTINGS,
            fov: 70,
        });
    });

    it('remembers the theme, the folded toolbar and the welcome visits', () => {
        const store = new SettingsStore(new MemoryStorage());

        expect(store.loadTheme()).toBeNull();
        store.saveTheme('light');
        expect(store.loadTheme()).toBe('light');

        expect(store.loadFolded()).toBe(false);
        store.saveFolded(true);
        expect(store.loadFolded()).toBe(true);

        expect(store.welcomeVisits()).toBe(0);
        store.countWelcomeVisit();
        store.countWelcomeVisit();
        expect(store.welcomeVisits()).toBe(2);
    });

    it('ignores an unknown stored theme', () => {
        const storage = new MemoryStorage();

        storage.setItem(STORAGE_KEYS.theme, 'sepia');

        expect(new SettingsStore(storage).loadTheme()).toBeNull();
    });
});

describe('SafeStorage', () => {
    it('swallows a storage that throws', () => {
        const broken = new SafeStorage(() => ({
            getItem: () => {
                throw new Error('blocked');
            },
            setItem: () => {
                throw new Error('blocked');
            },
        }));

        expect(broken.getItem('a')).toBeNull();
        expect(() => broken.setItem('a', 'b')).not.toThrow();
    });

    it('works without any storage at all', () => {
        const none = new SafeStorage(() => null);

        expect(none.getItem('a')).toBeNull();
        expect(() => none.setItem('a', 'b')).not.toThrow();
    });
});

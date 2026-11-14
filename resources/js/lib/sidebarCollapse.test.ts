import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    readSidebarCollapsed,
    sidebarCollapseKey,
    writeSidebarCollapsed,
} from './sidebarCollapse';

afterEach(() => {
    window.localStorage.clear();
    vi.restoreAllMocks();
});

describe('sidebarCollapseKey', () => {
    it('separates users, sidebars and scopes', () => {
        expect(sidebarCollapseKey('admin-sidebar', 'map', 7)).toBe(
            'backoffice.sidebar.admin-sidebar.map.7',
        );
        expect(sidebarCollapseKey('admin-sidebar', 'page', 8)).not.toBe(
            sidebarCollapseKey('admin-sidebar', 'page', 7),
        );
        expect(sidebarCollapseKey('store-admin-sidebar', 'page', 7)).not.toBe(
            sidebarCollapseKey('admin-sidebar', 'page', 7),
        );
        expect(sidebarCollapseKey('admin-sidebar', 'page', undefined)).toBe(
            'backoffice.sidebar.admin-sidebar.page.guest',
        );
    });
});

describe('readSidebarCollapsed / writeSidebarCollapsed', () => {
    it('falls back to the default until a choice is stored', () => {
        expect(readSidebarCollapsed('k', true)).toBe(true);
        expect(readSidebarCollapsed('k', false)).toBe(false);
    });

    it('remembers the last choice', () => {
        writeSidebarCollapsed('k', true);
        expect(readSidebarCollapsed('k', false)).toBe(true);

        writeSidebarCollapsed('k', false);
        expect(readSidebarCollapsed('k', true)).toBe(false);
    });

    it('keeps the default when storage throws', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });

        expect(() => writeSidebarCollapsed('k', true)).not.toThrow();
        expect(readSidebarCollapsed('k', false)).toBe(false);
        expect(readSidebarCollapsed('k', true)).toBe(true);
    });
});

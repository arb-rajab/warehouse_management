/**
 * Whether the back-office sidebar is folded to its icon rail, remembered per
 * user, per sidebar and per scope (the warehouse map keeps its own choice,
 * collapsed by default) in this browser's localStorage. Storage can be
 * missing or throw (private mode, blocked site data), so every access is
 * guarded and the default wins (.ai/rules/layouts.md).
 */
export function sidebarCollapseKey(
    sidebarId: string,
    scope: string,
    userId: number | string | undefined,
): string {
    return `backoffice.sidebar.${sidebarId}.${scope}.${userId ?? 'guest'}`;
}

export function readSidebarCollapsed(key: string, fallback: boolean): boolean {
    try {
        const stored = window.localStorage.getItem(key);

        return stored === null ? fallback : stored === '1';
    } catch {
        return fallback;
    }
}

export function writeSidebarCollapsed(key: string, collapsed: boolean): void {
    try {
        window.localStorage.setItem(key, collapsed ? '1' : '0');
    } catch {
        // Not remembered; the sidebar still folds for this page.
    }
}

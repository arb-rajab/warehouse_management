/** The part of `localStorage` the map uses; tests pass a fake. */
export interface StorageLike {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
}

/** localStorage can throw (private windows, blocked site data): wrap every access. */
export class SafeStorage implements StorageLike {
    constructor(private readonly source: () => StorageLike | null) {}

    getItem(key: string): string | null {
        try {
            return this.source()?.getItem(key) ?? null;
        } catch {
            return null;
        }
    }

    setItem(key: string, value: string): void {
        try {
            this.source()?.setItem(key, value);
        } catch {
            /* a per-viewer convenience: the page works without it */
        }
    }
}

export function browserStorage(): StorageLike {
    return new SafeStorage(() => window.localStorage);
}

export class MemoryStorage implements StorageLike {
    private readonly items = new Map<string, string>();

    getItem(key: string): string | null {
        return this.items.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.items.set(key, value);
    }
}

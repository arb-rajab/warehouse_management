import { usePage } from '@inertiajs/vue3';
import { computed, ref, toValue, watch } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';

/**
 * One column a store list table can show or hide (store-table-columns.md).
 * The Select checkbox column and the Actions column are not defined here:
 * they are always shown and the page puts them around the chosen columns.
 */
export interface TableColumnDef {
    /** Stable id: the key the choice is stored under and the cells test. */
    key: string;
    /** The translation key of the header and of the picker's checkbox. */
    label: string;
    /** Always shown and locked in the picker: the row's identifier. */
    required?: boolean;
    /** Shown until the user chooses otherwise. Defaults to `true`. */
    defaultVisible?: boolean;
    /** `false` leaves the column out altogether (permission, list variant). */
    available?: boolean;
}

export interface TableColumnsState<C extends TableColumnDef = TableColumnDef> {
    /** The columns this list and this user can have at all, in table order. */
    available: ComputedRef<C[]>;
    /** The chosen keys: required columns plus the chosen optional ones. */
    visibleKeys: ComputedRef<string[]>;
    /** The chosen columns in table order, for the table's header. */
    visibleColumns: ComputedRef<C[]>;
    /** Per column key, whether its cell renders (`<td v-if="shown.email">`). */
    shown: ComputedRef<Record<string, boolean>>;
    /** Show or hide an optional column; a required one is left alone. */
    toggle: (key: string, on?: boolean) => void;
    /** Show every available column. */
    showAll: () => void;
    /** Back to the defaults. */
    reset: () => void;
}

/** The browser-storage key of one list's choice for one store user. */
export function tableColumnsStorageKey(
    userId: number | null | undefined,
    listId: string,
): string {
    return `store.tableColumns.${userId ?? 0}.${listId}`;
}

/** A stored array, or `null` for nothing, junk or unreadable storage. */
function readStored(storageKey: string): unknown[] | null {
    try {
        const stored: unknown = JSON.parse(
            window.localStorage.getItem(storageKey) ?? 'null',
        );

        return Array.isArray(stored) ? stored : null;
    } catch {
        return null;
    }
}

function writeStored(storageKey: string, keys: string[]): void {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(keys));
    } catch {
        // Blocked storage: the choice just lasts for this page view.
    }
}

/**
 * Which columns a store list shows, remembered per list and per store user in
 * the browser's localStorage (store-table-columns.md). Required columns are
 * always part of the choice; unknown or stale stored keys are ignored; empty,
 * junk or blocked storage falls back to the defaults.
 *
 * `legacyStorageKey` is read once when the list has no choice stored under the
 * current scheme yet (the Customers list's pre-`store.tableColumns` key) and
 * the result is then saved under the new key.
 */
export function useTableColumns<C extends TableColumnDef>(options: {
    /** A stable id per list and list variant, e.g. `sales.orders`. */
    listId: MaybeRefOrGetter<string>;
    columns: MaybeRefOrGetter<C[]>;
    legacyStorageKey?: MaybeRefOrGetter<string | undefined>;
}): TableColumnsState<C> {
    const page = usePage();
    const storageKey = computed(() =>
        tableColumnsStorageKey(
            page.props.auth?.user?.id,
            toValue(options.listId),
        ),
    );
    const available = computed(() =>
        toValue(options.columns).filter((column) => column.available !== false),
    );

    /** Table order, required columns included whether chosen or not. */
    function normalize(keys: readonly unknown[]): string[] {
        return available.value
            .filter((column) => column.required || keys.includes(column.key))
            .map((column) => column.key);
    }

    function defaults(): string[] {
        return available.value
            .filter(
                (column) => column.required || column.defaultVisible !== false,
            )
            .map((column) => column.key);
    }

    function load(): string[] {
        const current = readStored(storageKey.value);
        const legacyKey = toValue(options.legacyStorageKey);
        const stored =
            current ?? (legacyKey === undefined ? null : readStored(legacyKey));

        if (
            stored !== null &&
            available.value.some((column) => stored.includes(column.key))
        ) {
            const keys = normalize(stored);

            if (current === null) {
                writeStored(storageKey.value, keys);
            }

            return keys;
        }

        return defaults();
    }

    const chosen = ref<string[]>(load());

    function choose(keys: string[]): void {
        chosen.value = keys;
        writeStored(storageKey.value, keys);
    }

    // Another list variant on the same page instance (the product lists) or
    // a change of what is available: re-read, or at least re-normalize.
    watch(storageKey, () => {
        chosen.value = load();
    });
    watch(available, () => {
        chosen.value = normalize(chosen.value);
    });

    const visibleKeys = computed(() => normalize(chosen.value));
    const visibleColumns = computed(() =>
        available.value.filter((column) =>
            visibleKeys.value.includes(column.key),
        ),
    );
    const shown = computed(() =>
        Object.fromEntries(
            available.value.map((column) => [
                column.key,
                visibleKeys.value.includes(column.key),
            ]),
        ),
    );

    return {
        available,
        visibleKeys,
        visibleColumns,
        shown,
        toggle(key, on) {
            const column = available.value.find((item) => item.key === key);

            if (column === undefined || column.required) {
                return;
            }

            const shouldShow = on ?? !visibleKeys.value.includes(key);

            choose(
                normalize(
                    shouldShow
                        ? [...visibleKeys.value, key]
                        : visibleKeys.value.filter((item) => item !== key),
                ),
            );
        },
        showAll() {
            choose(available.value.map((column) => column.key));
        },
        reset() {
            choose(defaults());
        },
    };
}

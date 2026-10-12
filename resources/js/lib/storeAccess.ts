import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { StoreSharedProps } from '@/types/store';

/**
 * What the signed-in store user may see, from the store's shared props.
 * It hides links and buttons only; every route checks its own permission.
 */
export interface StoreAccess {
    /** Holds the permission, or is a Super Admin (Gate::before()). */
    can: (permission: string) => boolean;
    /** The vendor's own account (`users.raizer_admin`). */
    isRaizerAdmin: boolean;
    /** The business setting that switches a section on is on. */
    setting: (key: string) => boolean;
}

export function storeAccess(props?: StoreSharedProps): StoreAccess {
    return {
        can: (permission) =>
            props !== undefined &&
            (props.isSuperAdmin || props.permissions.includes(permission)),
        isRaizerAdmin: props?.isRaizerAdmin ?? false,
        setting: (key) => props?.settings[key] ?? false,
    };
}

export function useStoreAccess(): ComputedRef<StoreAccess> {
    const page = usePage();

    return computed(() => storeAccess(page.props.store));
}

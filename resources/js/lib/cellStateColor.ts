import { CircleDashed, Inbox, PackageOpen } from '@lucide/vue';
import type { Component } from 'vue';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';

/**
 * Single source of truth for per-state cell coloring and iconography, shared
 * by CellSlot.vue (Tailwind classes + icon for the 2D map), CellMap3D.vue
 * (hex values for three.js materials, plus the icon for its state-color
 * legend), so the views can't drift apart. Mirrored by hand in the mobile
 * app's _getBgColor/_getBorderColor/_getStateIcon (Flutter, separate repo) —
 * keep both in sync when these values change: empty=gray/CircleDashed,
 * full=green/Inbox, opened=orange/PackageOpen.
 */
interface CellStateColor {
    backgroundClass: string;
    borderClass: string;
    hex: number;
    icon: Component;
}

export const CELL_STATE_COLOR: Record<Cell['state'], CellStateColor> = {
    empty: {
        backgroundClass: 'bg-gray-100 dark:bg-neutral-900',
        borderClass: 'border-gray-400 dark:border-neutral-700',
        hex: 0x9ca3af,
        icon: CircleDashed,
    },
    full: {
        backgroundClass: 'bg-green-50 dark:bg-green-950',
        borderClass: 'border-green-500 dark:border-green-700',
        hex: 0x22c55e,
        icon: Inbox,
    },
    opened: {
        backgroundClass: 'bg-orange-50 dark:bg-orange-950',
        borderClass: 'border-orange-500 dark:border-orange-700',
        hex: 0xf97316,
        icon: PackageOpen,
    },
};

/** Every cell state, in the order they're consistently listed/iterated in the UI. */
export const CELL_STATES: Cell['state'][] = ['empty', 'full', 'opened'];

export function cellStateLabel(state: Cell['state']): string {
    return t(`cellLog.states.${state}`);
}

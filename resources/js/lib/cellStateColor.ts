import type { Cell } from '@/types/admin';

/**
 * Single source of truth for per-state cell coloring, shared by CellSlot.vue
 * (Tailwind classes for the 2D map) and CellMap3D.vue (hex values for
 * three.js materials), so the two views can't drift apart. Mirrored by hand
 * in the mobile app's _getBgColor/_getBorderColor (Flutter, separate repo) —
 * keep both in sync when these values change: empty=gray, full=green,
 * opened=orange.
 */
interface CellStateColor {
    backgroundClass: string;
    borderClass: string;
    hex: number;
}

export const CELL_STATE_COLOR: Record<Cell['state'], CellStateColor> = {
    empty: {
        backgroundClass: 'bg-gray-100 dark:bg-neutral-900',
        borderClass: 'border-gray-400 dark:border-neutral-700',
        hex: 0x9ca3af,
    },
    full: {
        backgroundClass: 'bg-green-50 dark:bg-green-950',
        borderClass: 'border-green-500 dark:border-green-700',
        hex: 0x22c55e,
    },
    opened: {
        backgroundClass: 'bg-orange-50 dark:bg-orange-950',
        borderClass: 'border-orange-500 dark:border-orange-700',
        hex: 0xf97316,
    },
};

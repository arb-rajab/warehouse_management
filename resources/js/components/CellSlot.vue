<script setup lang="ts">
import {
    CalendarPlus,
    CalendarX,
    CircleDashed,
    Inbox,
    PackageOpen,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';

const props = withDefaults(
    defineProps<{
        cell: Cell | null;
        label: string;
        highlighted: boolean;
        pulsing?: boolean;
    }>(),
    { pulsing: false },
);

/**
 * Canonical per-state cell styling, mirrored by hand in the mobile app's
 * _getBgColor/_getBorderColor/_getStateIcon (Flutter, separate repo) — keep
 * both in sync when these values change: empty=gray/CircleDashed,
 * full=green/Inbox, opened=orange/PackageOpen.
 */
const stateClasses: Record<Cell['state'], string> = {
    empty: 'bg-gray-100 dark:bg-neutral-900',
    full: 'bg-green-50 dark:bg-green-950',
    opened: 'bg-orange-50 dark:bg-orange-950',
};

const stateBorderClasses: Record<Cell['state'], string> = {
    empty: 'border-gray-400 dark:border-neutral-700',
    full: 'border-green-500 dark:border-green-700',
    opened: 'border-orange-500 dark:border-orange-700',
};

const stateIcons: Record<Cell['state'], Component> = {
    empty: CircleDashed,
    full: Inbox,
    opened: PackageOpen,
};

const stateIcon = computed(() =>
    props.cell ? stateIcons[props.cell.state] : null,
);

const borderClass = computed(() =>
    props.cell
        ? `border ${stateBorderClasses[props.cell.state]}`
        : 'border border-dashed border-gray-200 dark:border-neutral-800',
);
</script>

<template>
    <div
        data-testid="cell-slot"
        :data-slot-label="label"
        class="group relative flex h-28 w-32 shrink-0 flex-col justify-between rounded-md p-2 text-xs"
        :class="[
            cell ? stateClasses[cell.state] : '',
            borderClass,
            highlighted
                ? 'ring-2 ring-blue-500 ring-offset-1 dark:ring-offset-neutral-950'
                : '',
            pulsing
                ? 'animate-pulse ring-2 ring-emerald-500 ring-offset-1 dark:ring-offset-neutral-950'
                : '',
        ]"
    >
        <span
            class="flex items-center gap-1 font-medium text-gray-500 dark:text-neutral-400"
        >
            <component
                :is="stateIcon"
                v-if="stateIcon"
                class="h-3 w-3 shrink-0"
            />
            {{ label }}
        </span>

        <template v-if="cell?.pallet">
            <img
                v-if="cell.pallet.product_image_url"
                :src="cell.pallet.product_image_url"
                :alt="cell.pallet.product_name"
                class="mb-1 h-8 w-8 rounded object-cover"
            />
            <div
                class="truncate font-medium text-gray-900 dark:text-neutral-100"
            >
                {{ cell.pallet.product_name }}
            </div>
            <div class="space-y-0.5">
                <div
                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-neutral-400"
                >
                    <CalendarX class="h-3 w-3 shrink-0" />
                    {{ formatDate(cell.pallet.expiration_date) }}
                </div>
                <div
                    class="flex items-center gap-1 text-xs text-gray-400 dark:text-neutral-500"
                >
                    <CalendarPlus class="h-3 w-3 shrink-0" />
                    {{ formatDateTime(cell.pallet.added_at) }}
                </div>
            </div>
        </template>
        <span v-else class="text-gray-400 dark:text-neutral-600">{{
            t('rows.show.empty')
        }}</span>
    </div>
</template>

<script setup lang="ts">
import { Ban, CalendarPlus, CalendarX, QrCode } from '@lucide/vue';
import { computed } from 'vue';
import { exportQr } from '@/actions/App/Http/Controllers/Admin/CellController';
import { CELL_STATE_COLOR } from '@/lib/cellStateColor';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';

const props = withDefaults(
    defineProps<{
        cell: Cell | null;
        label: string;
        highlighted: boolean;
        pulsing?: boolean;
        toggleable?: boolean;
    }>(),
    { pulsing: false, toggleable: false },
);

const emit = defineEmits<{
    'toggle-active': [cell: Cell];
}>();

const stateIcon = computed(() =>
    props.cell ? CELL_STATE_COLOR[props.cell.state].icon : null,
);

const borderClass = computed(() => {
    if (!props.cell) {
        return 'border border-dashed border-gray-200 dark:border-neutral-800';
    }

    return props.cell.is_active
        ? `border ${CELL_STATE_COLOR[props.cell.state].borderClass}`
        : 'border-2 border-red-500 dark:border-red-600';
});

const toggleActiveLabel = computed(() =>
    props.cell?.is_active
        ? t('cells.toggleActive.deactivateLabel')
        : t('cells.toggleActive.reactivateLabel'),
);

function onToggleActive(): void {
    if (props.cell) {
        emit('toggle-active', props.cell);
    }
}
</script>

<template>
    <div
        data-testid="cell-slot"
        :data-slot-label="label"
        class="relative flex h-28 w-32 shrink-0 flex-col justify-between rounded-md p-2 text-xs"
        :class="[
            cell ? CELL_STATE_COLOR[cell.state].backgroundClass : '',
            borderClass,
            cell && !cell.is_active ? 'opacity-60' : '',
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

        <span
            v-if="cell && !cell.is_active"
            :title="t('cells.inactiveBadge')"
            class="absolute start-1 top-1 flex items-center gap-0.5 rounded bg-red-600 px-1 py-0.5 text-[10px] font-semibold text-white"
        >
            <Ban class="h-2.5 w-2.5 shrink-0" />
        </span>

        <button
            v-if="cell && toggleable"
            type="button"
            :title="toggleActiveLabel"
            :aria-label="toggleActiveLabel"
            class="absolute end-6 top-1 cursor-pointer text-gray-400 hover:text-red-600 dark:text-neutral-500 dark:hover:text-red-500"
            @click="onToggleActive"
        >
            <Ban class="h-3.5 w-3.5" />
        </button>

        <a
            v-if="cell"
            :href="exportQr.url(cell.id)"
            :title="t('rows.show.reprintQr')"
            class="absolute end-1 top-1"
        >
            <QrCode class="h-3.5 w-3.5 text-gray-400 dark:text-neutral-500" />
        </a>

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

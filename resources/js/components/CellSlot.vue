<script setup lang="ts">
import {
    Ban,
    CalendarPlus,
    CalendarX,
    PackageSearch,
    QrCode,
} from '@lucide/vue';
import { computed } from 'vue';
import { exportQr } from '@/actions/App/Http/Controllers/Admin/CellController';
import { CELL_STATE_COLOR } from '@/lib/cellStateColor';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { productName } from '@/lib/productName';
import type { Cell } from '@/types/admin';

const props = withDefaults(
    defineProps<{
        cell: Cell | null;
        label: string;
        highlighted: boolean;
        pulsing?: boolean;
        toggleable?: boolean;
        manageable?: boolean;
    }>(),
    { pulsing: false, toggleable: false, manageable: false },
);

const emit = defineEmits<{
    'toggle-active': [cell: Cell];
    'manage-pallet': [cell: Cell];
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

function onManagePallet(): void {
    if (props.cell) {
        emit('manage-pallet', props.cell);
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
                class="h-4 w-4 shrink-0"
                stroke-width="2.5"
            />
            {{ label }}
        </span>

        <span
            v-if="cell && !cell.is_active"
            :title="t('cells.inactiveBadge')"
            class="absolute start-1 top-1 flex items-center gap-0.5 rounded bg-red-600 px-1 py-0.5 text-[10px] font-semibold text-white"
        >
            <Ban class="h-3 w-3 shrink-0" stroke-width="2.5" />
        </span>

        <button
            v-if="cell && toggleable"
            type="button"
            :title="toggleActiveLabel"
            :aria-label="toggleActiveLabel"
            class="absolute end-7 top-1 cursor-pointer text-gray-400 hover:text-red-600 dark:text-neutral-500 dark:hover:text-red-500"
            @click="onToggleActive"
        >
            <Ban class="h-5 w-5" stroke-width="2.5" />
        </button>

        <a
            v-if="cell"
            :href="exportQr.url(cell.id)"
            :title="t('rows.show.reprintQr')"
            class="absolute end-1 top-1"
        >
            <QrCode
                class="h-5 w-5 text-gray-400 dark:text-neutral-500"
                stroke-width="2.5"
            />
        </a>

        <button
            v-if="cell && manageable && cell.is_active"
            type="button"
            :title="t('cells.palletActions.triggerLabel')"
            :aria-label="t('cells.palletActions.triggerLabel')"
            class="absolute end-1 bottom-1 cursor-pointer text-gray-400 hover:text-blue-600 dark:text-neutral-500 dark:hover:text-blue-400"
            @click="onManagePallet"
        >
            <PackageSearch class="h-5 w-5" stroke-width="2.5" />
        </button>

        <template v-if="cell?.pallet">
            <img
                v-if="cell.pallet.product_image_url"
                :src="cell.pallet.product_image_url"
                :alt="
                    productName(
                        cell.pallet.product_name,
                        cell.pallet.product_ar_name,
                    )
                "
                class="mb-1 h-8 w-8 rounded object-cover"
            />
            <div
                class="truncate font-medium text-gray-900 dark:text-neutral-100"
            >
                {{
                    productName(
                        cell.pallet.product_name,
                        cell.pallet.product_ar_name,
                    )
                }}
            </div>
            <div class="space-y-0.5">
                <div
                    v-if="cell.pallet.expiration_date"
                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-neutral-400"
                >
                    <CalendarX class="h-3 w-3 shrink-0" />
                    {{ formatDate(cell.pallet.expiration_date) }}
                </div>
                <div
                    v-if="cell.pallet.added_at"
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

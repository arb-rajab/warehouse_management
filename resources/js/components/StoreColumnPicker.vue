<script setup lang="ts">
import { Columns3 } from '@lucide/vue';
import { computed, ref } from 'vue';
import FilterDialog from '@/components/FilterDialog.vue';
import {
    filterClearButtonClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { TableColumnsState } from '@/lib/useTableColumns';

const props = defineProps<{
    /** What `useTableColumns()` returned for this list. */
    state: TableColumnsState;
}>();

defineSlots<{
    /** Start side of the toolbar row, such as a bulk-delete form. */
    default?(): unknown;
}>();

const open = ref(false);
const columns = computed(() => props.state.available.value);
const chosen = computed(() => props.state.visibleKeys.value);

function hintId(key: string): string {
    return `column-always-shown-${key}`;
}
</script>

<template>
    <!--
        The one toolbar row above a store list table (store-table-columns.md):
        the page's bulk actions, if any, on the start side and the Columns
        button on the end side. `ms-auto` is logical, so it mirrors in Arabic.
    -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <slot />
        <button
            type="button"
            :class="[filterTriggerButtonClass, 'ms-auto']"
            @click="open = true"
        >
            <Columns3 class="h-4 w-4" />{{ t('store.table.columns') }}
        </button>
    </div>
    <FilterDialog
        v-model:open="open"
        :title="t('store.table.columnsTitle')"
        :close-label="t('common.close')"
    >
        <div class="grid grid-cols-1 gap-2 pb-4 sm:grid-cols-2">
            <div
                v-for="column in columns"
                :key="column.key"
                class="flex flex-wrap items-center gap-x-2 text-sm"
            >
                <label class="flex items-center gap-2"
                    ><input
                        type="checkbox"
                        :checked="chosen.includes(column.key)"
                        :disabled="column.required"
                        :aria-describedby="
                            column.required ? hintId(column.key) : undefined
                        "
                        class="cursor-pointer disabled:cursor-not-allowed"
                        @change="
                            (event: Event) =>
                                state.toggle(
                                    column.key,
                                    (event.target as HTMLInputElement).checked,
                                )
                        "
                    />{{ t(column.label) }}</label
                ><span
                    v-if="column.required"
                    :id="hintId(column.key)"
                    class="text-xs text-muted"
                    >{{ t('store.table.alwaysShown') }}</span
                >
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                :class="filterClearButtonClass"
                @click="state.showAll()"
            >
                {{ t('store.table.showAll') }}
            </button>
            <button
                type="button"
                :class="filterClearButtonClass"
                @click="state.reset()"
            >
                {{ t('store.table.reset') }}
            </button>
        </div>
    </FilterDialog>
</template>

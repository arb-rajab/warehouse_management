<script setup lang="ts">
import FilterCheckbox from '@/components/FilterCheckbox.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';

defineProps<{
    /**
     * Prefixes every id this renders (`${idPrefix}-state`/`-expires-within-days`/
     * `-expired`) so a page can render this twice — once in its main Filters
     * dialog, once in a DataTable column-filter popover — without a DOM id
     * collision (see LocationFilterFields.vue).
     */
    idPrefix: string;
    expiringSoonDays: number;
}>();

const state = defineModel<string>('state', { required: true });
const expired = defineModel<boolean>('expired', { required: true });
const expiresWithinDays = defineModel<string>('expiresWithinDays', {
    required: true,
});
const inactive = defineModel<boolean>('inactive', { required: true });

// No `empty` option here (unlike the cells map) — an empty cell never holds
// a product, so filtering to it would always zero out every column.
const occupiedCellStates: Extract<Cell['state'], 'full' | 'opened'>[] = [
    'full',
    'opened',
];

// Mirrors the dashboard's fixed expiring-soon windows, as a quick-pick
// shortcut for this field instead of typing a day count every time.
const expiringSoonQuickPicks = [7, 14, 30, 60];

function stateLabel(cellState: Cell['state']): string {
    return t(`cellLog.states.${cellState}`);
}
</script>

<template>
    <!-- `contents` so these three fields lay out as flat siblings in the
    parent's grid instead of as one grid cell each. -->
    <div class="contents">
        <FilterSelect
            :id="`${idPrefix}-state`"
            v-model="state"
            :label="t('cells.filters.state')"
            :all-label="t('cellLog.filters.all')"
            :options="
                occupiedCellStates.map((s) => ({
                    value: s,
                    label: stateLabel(s),
                }))
            "
        />

        <!-- Lets the caller keep its product filter between state and
        expires-within-days, matching the page's existing field order. -->
        <slot />

        <div>
            <FilterNumberField
                :id="`${idPrefix}-expires-within-days`"
                v-model="expiresWithinDays"
                :label="t('cellHighlight.expiresWithinDays')"
                :placeholder="String(expiringSoonDays)"
            />
            <div class="mt-1 flex gap-1">
                <button
                    v-for="days in expiringSoonQuickPicks"
                    :key="days"
                    type="button"
                    class="cursor-pointer rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800"
                    @click="expiresWithinDays = String(days)"
                >
                    {{ days }}
                </button>
            </div>
        </div>

        <FilterCheckbox
            :id="`${idPrefix}-expired`"
            v-model="expired"
            :label="t('cellHighlight.expired')"
        />

        <FilterCheckbox
            :id="`${idPrefix}-inactive`"
            v-model="inactive"
            :label="t('products.filters.inactive')"
        />
    </div>
</template>

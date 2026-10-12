<script setup lang="ts">
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import { columnNumberOptions, selectedCountLabel } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { RowFilterOption } from '@/types/admin';

const props = defineProps<{
    /**
     * Prefixes every id this renders (`${idPrefix}-row`/`${idPrefix}-column`)
     * so a page can render this twice — once in its main Filters dialog,
     * once in a DataTable column-filter popover — without a DOM id
     * collision (see js-components.md).
     */
    idPrefix: string;
    rows: RowFilterOption[];
    maxColumnNumber: number;
}>();

const rowIds = defineModel<string[]>('rowIds', { required: true });
const columnNumber = defineModel<string>('columnNumber', { required: true });

const columnNumbers = columnNumberOptions(props.maxColumnNumber);
</script>

<template>
    <div>
        <FilterMultiSelect
            :id="`${idPrefix}-row`"
            v-model="rowIds"
            :label="t('cellLog.filters.row')"
            :all-label="t('cellLog.filters.all')"
            :selected-count-label="selectedCountLabel"
            :options="
                rows.map((row) => ({
                    value: String(row.id),
                    label: row.letter,
                }))
            "
        />

        <FilterSelect
            :id="`${idPrefix}-column`"
            v-model="columnNumber"
            :label="t('cellLog.filters.column')"
            :all-label="t('cellLog.filters.all')"
            :options="
                columnNumbers.map((columnNumber) => ({
                    value: columnNumber,
                    label: String(columnNumber),
                }))
            "
        />
    </div>
</template>

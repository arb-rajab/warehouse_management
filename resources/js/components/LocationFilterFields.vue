<script setup lang="ts">
import FilterSelect from '@/components/FilterSelect.vue';
import { columnNumberOptions } from '@/lib/filters';
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

const rowId = defineModel<string>('rowId', { required: true });
const columnNumber = defineModel<string>('columnNumber', { required: true });

const columnNumbers = columnNumberOptions(props.maxColumnNumber);
</script>

<template>
    <div>
        <FilterSelect
            :id="`${idPrefix}-row`"
            v-model="rowId"
            :label="t('cellLog.filters.row')"
            :all-label="t('cellLog.filters.all')"
            :options="rows.map((row) => ({ value: row.id, label: row.letter }))"
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

<script setup lang="ts">
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import { cellLogActionLabel } from '@/lib/cellStatusLogDisplay';
import { selectedCountLabel } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { CellLogAction } from '@/types/admin';

defineProps<{
    /**
     * Prefixes every id this renders (`${idPrefix}-action`/`${idPrefix}-user`)
     * so a page can render this twice — once in its main Filters dialog, once
     * in a DataTable column-filter popover — without a DOM id collision (see
     * LocationFilterFields.vue).
     */
    idPrefix: string;
    actions: CellLogAction[];
    users: { id: number; name: string }[];
}>();

const action = defineModel<CellLogAction[]>('action', { required: true });
const userId = defineModel<string[]>('userId', { required: true });
</script>

<template>
    <!-- `contents` so these two fields lay out as flat siblings in the
    parent's grid instead of as one grid cell each. -->
    <div class="contents">
        <FilterMultiSelect
            :id="`${idPrefix}-action`"
            v-model="action"
            :label="t('cellLog.filters.statusChange')"
            :all-label="t('cellLog.filters.all')"
            :selected-count-label="selectedCountLabel"
            :options="
                actions.map((a) => ({ value: a, label: cellLogActionLabel(a) }))
            "
        />

        <FilterMultiSelect
            :id="`${idPrefix}-user`"
            v-model="userId"
            :label="t('cellLog.filters.doneBy')"
            :all-label="t('cellLog.filters.all')"
            :selected-count-label="selectedCountLabel"
            :options="
                users.map((u) => ({ value: u.id.toString(), label: u.name }))
            "
        />
    </div>
</template>

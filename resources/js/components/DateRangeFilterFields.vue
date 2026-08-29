<script setup lang="ts">
import FilterDateField from '@/components/FilterDateField.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';

defineProps<{
    fromId: string;
    toId: string;
    withinDaysId: string;
    fromLabel: string;
    toLabel: string;
    withinDaysLabel: string;
    rangeDisabled: boolean;
    daysDisabled: boolean;
}>();

const from = defineModel<string>('from', { required: true });
const to = defineModel<string>('to', { required: true });
const withinDays = defineModel<string>('withinDays', { required: true });
</script>

<template>
    <!-- `contents` so these three fields lay out as flat siblings in the
    parent's grid instead of as one grid cell each. -->
    <div class="contents">
        <FilterDateField
            :id="fromId"
            v-model="from"
            :label="fromLabel"
            :disabled="rangeDisabled"
        />

        <FilterDateField
            :id="toId"
            v-model="to"
            :label="toLabel"
            :disabled="rangeDisabled"
        />

        <FilterNumberField
            :id="withinDaysId"
            v-model="withinDays"
            :label="withinDaysLabel"
            :disabled="daysDisabled"
        />
    </div>
</template>

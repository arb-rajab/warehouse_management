<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import {
    filterApplyButtonClass,
    filterClearButtonClass,
    filterFooterClass,
} from '@/lib/filters';

defineProps<{
    formId: string;
    applyLabel: string;
    clearLabel: string;
}>();

defineEmits<{
    clear: [];
}>();
</script>

<template>
    <div :class="filterFooterClass">
        <button type="submit" :form="formId" :class="filterApplyButtonClass">
            <Check class="h-4 w-4 shrink-0" />
            {{ applyLabel }}
        </button>
        <button
            type="button"
            :class="filterClearButtonClass"
            @click="$emit('clear')"
        >
            <X class="h-4 w-4 shrink-0" />
            {{ clearLabel }}
        </button>
    </div>
</template>

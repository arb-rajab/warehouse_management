<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { fieldLabelClass } from '@/lib/filters';
import {
    useDismissibleListbox,
    useMultiSelectToggle,
} from '@/lib/useDismissibleListbox';

const props = defineProps<{
    id: string;
    label: string;
    allLabel: string;
    selectedCountLabel: (count: number) => string;
    options: { value: string; label: string }[];
}>();

const model = defineModel<string[]>({ required: true });

const { open, containerRef, setOptionRef, onOptionKeydown } =
    useDismissibleListbox(() => props.options.length);
const { isChecked, toggleValue } = useMultiSelectToggle(model);

function buttonLabel(): string {
    if (model.value.length === 0) {
        return props.allLabel;
    }

    if (model.value.length === 1) {
        return (
            props.options.find((option) => option.value === model.value[0])
                ?.label ?? props.allLabel
        );
    }

    return props.selectedCountLabel(model.value.length);
}
</script>

<template>
    <div ref="containerRef" class="relative">
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <button
            :id="id"
            type="button"
            class="flex w-full min-w-48 cursor-pointer items-center justify-between gap-2 rounded-md border border-gray-300 px-3 py-2 text-left text-sm dark:border-neutral-700 dark:bg-neutral-800"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="truncate">{{ buttonLabel() }}</span>
            <ChevronDown class="h-4 w-4 shrink-0 text-gray-400" />
        </button>
        <div
            v-if="open"
            role="listbox"
            class="absolute z-10 mt-1 w-max max-w-xs min-w-full rounded-md border border-gray-300 bg-white p-2 shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
        >
            <label
                v-for="(option, index) in options"
                :key="option.value"
                role="option"
                :aria-selected="isChecked(option.value)"
                class="flex cursor-pointer items-start gap-2 rounded px-2 py-1 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700"
            >
                <input
                    :ref="(el) => setOptionRef(el, index)"
                    type="checkbox"
                    class="mt-0.5 shrink-0"
                    :checked="isChecked(option.value)"
                    @change="toggleValue(option.value)"
                    @keydown="onOptionKeydown($event, index)"
                />
                <span class="break-words">{{ option.label }}</span>
            </label>
        </div>
    </div>
</template>

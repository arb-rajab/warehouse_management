<script setup lang="ts">
defineOptions({ inheritAttrs: false });

/**
 * `value` is bound with the `.attr` modifier so the input stays uncontrolled:
 * the browser keeps whatever the user typed when a failed submit re-renders
 * the form with new errors. Any extra attributes (min, maxlength, required,
 * readonly, placeholder, autocomplete) land on the input via `$attrs`.
 */
import { fieldLabelClass } from '@/lib/filters';

withDefaults(
    defineProps<{
        id: string;
        label: string;
        type?: string;
        value?: string | number;
        error?: string;
        inputClass?: string;
    }>(),
    {
        type: 'text',
        value: undefined,
        error: undefined,
        inputClass: undefined,
    },
);
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <input
            :id="id"
            :name="id"
            :type="type"
            :value.attr="value"
            v-bind="$attrs"
            :class="[
                'w-full rounded-md border border-gray-300 px-3 py-2 text-sm read-only:bg-gray-100 dark:border-neutral-700 dark:bg-neutral-800 dark:read-only:bg-neutral-900',
                inputClass,
            ]"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </div>
</template>

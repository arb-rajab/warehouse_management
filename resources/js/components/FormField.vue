<script setup lang="ts">
defineOptions({ inheritAttrs: false });

/**
 * `value` is bound with the `.attr` modifier so the input stays uncontrolled:
 * the browser keeps whatever the user typed when a failed submit re-renders
 * the form with new errors. Any extra attributes (min, maxlength, readonly,
 * placeholder, autocomplete) land on the input via `$attrs`. `required` is a
 * prop so the label can show the RequiredMark beside the native attribute.
 */
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass } from '@/lib/filters';
import { useRequiredField } from '@/lib/requiredFields';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        type?: string;
        value?: string | number;
        error?: string;
        inputClass?: string;
        required?: boolean;
    }>(),
    {
        type: 'text',
        required: false,
    },
);

useRequiredField(() => props.required);
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <input
            :id="id"
            :name="id"
            :type="type"
            :value.attr="value"
            :required="required"
            v-bind="$attrs"
            :class="[
                'w-full rounded-md border border-gray-300 px-3 py-2 text-sm read-only:bg-gray-100 dark:border-neutral-700 dark:bg-neutral-800 dark:read-only:bg-neutral-900',
                inputClass,
            ]"
        />
        <FieldError :message="error" />
    </div>
</template>

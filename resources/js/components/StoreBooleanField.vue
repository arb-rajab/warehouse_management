<script setup lang="ts">
import { nextTick, onMounted, useTemplateRef } from 'vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
/**
 * `required` defaults to true, as the select always carried it; pass
 * `:required="false"` where the server rule is not `required`.
 *
 * `highlighted` marks the option a button sent the admin here to change: it is
 * ringed, scrolled into view and focused when the page renders
 * (.ai/rules/deep-link-highlight.md).
 */
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        value?: boolean;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        highlighted?: boolean;
    }>(),
    { required: true, highlighted: false },
);
const select = useTemplateRef<HTMLSelectElement>('select');
onMounted(async () => {
    if (!props.highlighted) {
        return;
    }

    await nextTick();
    select.value?.scrollIntoView?.({ block: 'center' });
    select.value?.focus({ preventScroll: true });
});
</script>
<template>
    <div
        :data-highlighted="highlighted ? '' : undefined"
        :class="
            highlighted
                ? 'rounded-lg p-3 ring-2 ring-amber-500 ring-offset-2 dark:ring-offset-neutral-900'
                : ''
        "
    >
        <label :for="name" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <select
            :id="name"
            ref="select"
            :name="name"
            :required="required"
            :disabled="disabled"
            :class="[
                plainFieldInputClass,
                'cursor-pointer disabled:cursor-not-allowed',
            ]"
        >
            <option value="1" :selected="value">
                {{ t('store.settings.enabled') }}
            </option>
            <option value="0" :selected="!value">
                {{ t('store.settings.disabled') }}
            </option>
        </select>
        <FieldError :message="error" />
    </div>
</template>

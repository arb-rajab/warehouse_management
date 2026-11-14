<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
/**
 * `required` defaults to true, as the select always carried it; pass
 * `:required="false"` where the server rule is not `required`.
 *
 * `highlighted` marks the option a button sent the admin here to change: it is
 * ringed, scrolled into view and focused when the page renders, through the
 * shared StoreHighlight (.ai/rules/deep-link-highlight.md).
 */
withDefaults(
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
</script>
<template>
    <StoreHighlight :active="highlighted">
        <label :for="name" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <select
            :id="name"
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
    </StoreHighlight>
</template>

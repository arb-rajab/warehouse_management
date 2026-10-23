<script setup lang="ts">
import { t } from '@/lib/i18n';
import { useRequiredField } from '@/lib/requiredFields';

/**
 * The mark after a required field's label: a red asterisk hidden from
 * assistive technology, plus visually hidden "required" text for screen
 * readers. Render it only when the control also carries `required` (or
 * `aria-required`) and the server rule is `required` (.ai/rules/form-required-fields.md).
 * While rendered it registers with the enclosing form, which then shows the
 * "* Required fields" legend.
 */
useRequiredField(() => true);
</script>

<template>
    <span data-required-mark class="ms-0.5 text-red-700 dark:text-red-400"
        ><span aria-hidden="true">*</span
        ><span class="sr-only">{{ t('common.required') }}</span></span
    >
</template>

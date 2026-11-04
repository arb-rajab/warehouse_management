<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { Check, LoaderCircle, SlidersHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import {
    countBadgeClass,
    filterApplyButtonClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

defineProps<{
    /** The list's own GET route; the fields keep the names it already reads. */
    action: UrlMethodPair;
    /** How many of the dialog's filters are applied (see `countActive`). */
    activeCount: number;
}>();

defineSlots<{
    /** Search, at most one key filter, and hidden fields such as `per_page`. */
    default(props: {
        errors: Record<string, string>;
        processing: boolean;
    }): unknown;
    /** Every other filter, shown in the "Filters" dialog. */
    filters(props: { errors: Record<string, string> }): unknown;
}>();

/** One list filter per page; the dialog footer's buttons submit it by id. */
const formId = 'store-list-filters';
const open = ref(false);
const dialogFieldsRef = ref<HTMLElement | null>(null);

/**
 * Submit the form without the dialog's filters, keeping search, the key
 * filter and the hidden fields. Disabled controls are left out of the form's
 * data, which Inertia's `<Form>` reads synchronously inside `submit()`, so
 * the dialog's fields are disabled only for that call and work for
 * uncontrolled and v-model fields alike.
 */
function clearDialogFilters(submit: () => void): void {
    const controls = Array.from(
        dialogFieldsRef.value?.querySelectorAll<
            HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
        >('input[name], select[name], textarea[name]') ?? [],
    ).filter((control) => !control.disabled);

    controls.forEach((control) => {
        control.disabled = true;
    });

    try {
        submit();
    } finally {
        controls.forEach((control) => {
            control.disabled = false;
        });
    }
}
</script>

<template>
    <Form
        :id="formId"
        :action="action"
        #default="{ errors, processing, submit }"
        class="mb-4 flex flex-wrap items-end gap-3"
        @success="open = false"
    >
        <slot :errors="errors" :processing="processing" />
        <button
            type="submit"
            :disabled="processing"
            :class="[
                filterApplyButtonClass,
                'disabled:cursor-not-allowed disabled:opacity-50',
            ]"
        >
            <LoaderCircle
                v-if="processing"
                class="h-4 w-4 shrink-0 animate-spin"
            />
            <Check v-else class="h-4 w-4 shrink-0" />
            {{
                processing
                    ? t('store.listFilters.applying')
                    : t('common.filters.apply')
            }}
        </button>
        <button
            type="button"
            :class="filterTriggerButtonClass"
            @click="open = true"
        >
            <SlidersHorizontal class="h-4 w-4" />
            {{ t('common.filters.title') }}
            <span v-if="activeCount > 0" :class="countBadgeClass">
                <span aria-hidden="true">{{ activeCount }}</span>
                <span class="sr-only">{{
                    t('store.listFilters.activeCount', { count: activeCount })
                }}</span>
            </span>
        </button>
        <!-- Kept mounted so a search sent while the dialog is closed still
             carries the dialog's filters, as one plain GET form. -->
        <FilterDialog
            v-model:open="open"
            keep-mounted
            :title="t('common.filters.title')"
            :close-label="t('common.filters.close')"
        >
            <div
                ref="dialogFieldsRef"
                class="grid grid-cols-1 gap-4 pb-6 sm:grid-cols-2"
            >
                <slot name="filters" :errors="errors" />
            </div>
            <template #footer>
                <FilterDialogFooter
                    :form-id="formId"
                    :apply-label="t('common.filters.apply')"
                    :clear-label="t('common.filters.clear')"
                    @clear="clearDialogFilters(submit)"
                />
            </template>
        </FilterDialog>
    </Form>
</template>

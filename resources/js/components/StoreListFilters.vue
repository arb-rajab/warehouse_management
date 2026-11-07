<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form, router } from '@inertiajs/vue3';
import { Check, LoaderCircle, SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import {
    countBadgeClass,
    filterApplyButtonClass,
    filterClearButtonClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreFilterChip } from '@/types/store';

const props = defineProps<{
    /** The list's own GET route; the fields keep the names it already reads. */
    action: UrlMethodPair;
    /**
     * How many of the dialog's filters are applied (see `countActive`). Only
     * for pages that do not pass `chips`; with `chips` the badge counts them.
     */
    activeCount?: number;
    /**
     * The dialog's applied filters, one removable chip each. The badge shows
     * their number, so pass the same list the count would come from.
     */
    chips?: StoreFilterChip[];
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

const badgeCount = computed(
    () => props.chips?.length ?? props.activeCount ?? 0,
);

function chipText(chip: StoreFilterChip): string {
    return chip.value ? `${chip.label}: ${chip.value}` : chip.label;
}

/**
 * Reload the list without the given query keys (and without the page, which
 * may no longer exist). Every other applied parameter is kept, read from the
 * address rather than the form, so an unsent edit in a field is not applied.
 */
function dropFromQuery(keys: string[]): void {
    const params = new URLSearchParams(window.location.search);

    [...keys, 'page'].forEach((key) => {
        params.delete(key);
        params.delete(`${key}[]`);
    });
    const query = params.toString();

    router.get(
        query ? `${props.action.url}?${query}` : props.action.url,
        {},
        { preserveState: true, replace: true },
    );
}

/** The same as the dialog's Clear: keeps search, the key filter and the rest. */
function clearChips(): void {
    dropFromQuery((props.chips ?? []).flatMap((chip) => chip.keys));
}

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
            <span v-if="badgeCount > 0" :class="countBadgeClass">
                <span aria-hidden="true">{{ badgeCount }}</span>
                <span class="sr-only">{{
                    t('store.listFilters.activeCount', { count: badgeCount })
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
    <ul
        v-if="chips?.length"
        :aria-label="t('store.listFilters.appliedFilters')"
        class="-mt-2 mb-4 flex flex-wrap items-center gap-2"
    >
        <li
            v-for="chip in chips"
            :key="chip.key"
            class="inline-flex max-w-full items-center gap-1 rounded-full bg-gray-100 ps-3 pe-1 text-sm text-gray-800 dark:bg-neutral-800 dark:text-neutral-100"
        >
            <span class="min-w-0 truncate">{{ chipText(chip) }}</span>
            <button
                type="button"
                class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-neutral-700"
                :aria-label="
                    t('store.listFilters.removeFilter', {
                        label: chipText(chip),
                    })
                "
                @click="dropFromQuery(chip.keys)"
            >
                <X class="h-4 w-4" />
            </button>
        </li>
        <li>
            <button
                type="button"
                :class="filterClearButtonClass"
                @click="clearChips"
            >
                {{ t('store.listFilters.clearFilters') }}
            </button>
        </li>
    </ul>
</template>

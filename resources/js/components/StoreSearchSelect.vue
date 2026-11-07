<script setup lang="ts">
import {
    Check,
    ChevronDown,
    CircleAlert,
    Keyboard,
    LoaderCircle,
    PackageSearch,
    RotateCw,
    Search,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreSearchOptionRow from '@/components/StoreSearchOptionRow.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import {
    usePanelMaxWidth,
    usePanelViewportTracking,
} from '@/lib/useDismissibleListbox';
import { useSearchOptions } from '@/lib/useSearchOptions';
import type { SearchEndpoint } from '@/lib/useSearchOptions';
import type { SearchOption, SearchParams } from '@/types/searchSelect';

/**
 * The one searchable select of the store back office
 * (.ai/rules/store-search-select.md): a typeahead against a Wayfinder
 * endpoint, modeled on the warehouse product search. Results are rows with a
 * label, a description, an image or icon and a badge; the open list is a
 * teleported fixed layer (no dialog, table or sticky header clips it; a
 * top-anchored sheet on phones); the selected value is shown as a chip that
 * survives an edit form's prefill because `v-model` holds the whole option.
 *
 * Form contract: a hidden input named `name` posts the id (`name` ends with
 * `[]` for `multiple`, one input per pick), the visible search box lives in the
 * teleported panel and is never posted, and a required field with no value
 * carries a visually hidden native `required` guard that hands focus to the
 * trigger. A change dispatches a bubbling `change` from the hidden input, so
 * lib/useFormValidation.ts revalidates the field.
 *
 * Single selection: `v-model`. Multiple selection: `multiple` and `v-model:items`.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        endpoint: SearchEndpoint;
        name?: string;
        /** Extra query parameters (a dependent select's `state_id`); a change drops the loaded rows. */
        params?: SearchParams;
        multiple?: boolean;
        required?: boolean;
        disabled?: boolean;
        clearable?: boolean;
        error?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        helper?: string;
        /** A query shorter than this is not sent. */
        minChars?: number;
        debounceMs?: number;
    }>(),
    { clearable: true, minChars: 0, debounceMs: 300 },
);

const single = defineModel<SearchOption | null>({ default: null });
const items = defineModel<SearchOption[]>('items', { default: () => [] });
const emit = defineEmits<{
    select: [option: SearchOption];
    remove: [option: SearchOption];
    clear: [];
    open: [];
    close: [];
}>();

const open = ref(false);
const phone = ref(false);
const activeIndex = ref(-1);
const fieldRef = ref<HTMLElement | null>(null);
const boxRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLButtonElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const scrollRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

const selectedList = computed<SearchOption[]>(() =>
    props.multiple ? items.value : single.value ? [single.value] : [],
);
const hasValue = computed(() => selectedList.value.length > 0);

function isSelected(option: SearchOption): boolean {
    return selectedList.value.some(
        (picked) => String(picked.id) === String(option.id),
    );
}

const {
    query,
    results: options,
    loading,
    failed,
    belowMinimum,
    hasMore,
    total,
    refresh,
    reset,
    fetchFirstPageIfEmpty,
    fetchNextPage,
    onOptionsScroll,
} = useSearchOptions<SearchOption>({
    endpoint: props.endpoint,
    params: () => props.params ?? {},
    minChars: () => props.minChars,
    debounceMs: props.debounceMs,
    onResults: (list, replace) => {
        if (replace) {
            activeIndex.value =
                list.length === 0
                    ? -1
                    : Math.max(
                          list.findIndex((option) => isSelected(option)),
                          0,
                      );
        }
    },
});

const labelId = computed(() => `${props.id}-label`);
const panelId = computed(() => `${props.id}-panel`);
const listboxId = computed(() => `${props.id}-listbox`);
const errorId = computed(() => `${props.id}-error`);
const helperId = computed(() => `${props.id}-helper`);
const describedBy = computed(
    () =>
        [
            props.error ? errorId.value : null,
            props.helper ? helperId.value : null,
        ]
            .filter((id) => id !== null)
            .join(' ') || undefined,
);

function optionId(index: number): string {
    return `${props.id}-option-${index}`;
}

// The panel geometry: the shared start-edge anchor of FilterProductSelect, plus
// a flip above the field when there is no room below it.
const {
    panelMaxWidthPx,
    panelPosition,
    recompute: recomputeGeometry,
} = usePanelMaxWidth(boxRef, 448);
const placement = ref({ flipUp: false, bottom: 0, listMax: 288 });

function recompute(): void {
    recomputeGeometry();
    const rect = boxRef.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const below = window.innerHeight - rect.bottom - 8;
    const above = rect.top - 8;
    const flipUp = below < 280 && above > below;

    placement.value = {
        flipUp,
        bottom: window.innerHeight - rect.top + 4,
        listMax: Math.max(160, Math.min(288, (flipUp ? above : below) - 120)),
    };
}

usePanelViewportTracking(boxRef, open, recompute);

const panelStyle = computed(() => {
    if (phone.value) {
        return {};
    }

    const width = Math.min(
        Math.max(panelPosition.value.minWidth, 352),
        panelMaxWidthPx.value,
    );

    return {
        ...(placement.value.flipUp
            ? { bottom: `${placement.value.bottom}px` }
            : { top: `${panelPosition.value.top}px` }),
        insetInlineStart: `${panelPosition.value.insetInlineStart}px`,
        width: `${width}px`,
    };
});
const scrollStyle = computed(() =>
    phone.value
        ? { maxHeight: 'min(24rem, 55dvh)' }
        : { maxHeight: `${placement.value.listMax}px` },
);

function openPanel(seed?: string): void {
    if (props.disabled || open.value) {
        return;
    }

    phone.value =
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(max-width: 639.98px)').matches;
    recompute();

    if (seed !== undefined) {
        query.value = seed;
    }

    open.value = true;
    emit('open');

    if (seed === undefined) {
        fetchFirstPageIfEmpty();
    }

    void nextTick(() => inputRef.value?.focus());
}

function closePanel(returnFocus = true): void {
    if (!open.value) {
        return;
    }

    open.value = false;
    emit('close');

    if (returnFocus) {
        triggerRef.value?.focus();
    }
}

function notifyChange(): void {
    void nextTick(() => {
        fieldRef.value
            ?.querySelector('input[type="hidden"]')
            ?.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function choose(option: SearchOption): void {
    if (props.multiple) {
        if (isSelected(option)) {
            removeOne(option);

            return;
        }

        items.value = [...items.value, option];
        emit('select', option);
    } else {
        single.value = option;
        emit('select', option);
        closePanel();
    }

    notifyChange();
}

function removeOne(option: SearchOption): void {
    items.value = items.value.filter(
        (picked) => String(picked.id) !== String(option.id),
    );
    emit('remove', option);
    notifyChange();
}

function clear(): void {
    if (props.multiple) {
        items.value = [];
    } else {
        single.value = null;
    }

    emit('clear');
    notifyChange();
    triggerRef.value?.focus();
}

function move(delta: 1 | -1): void {
    const count = options.value.length;

    if (count === 0) {
        return;
    }

    if (delta === 1 && activeIndex.value === count - 1 && hasMore.value) {
        fetchNextPage();

        return;
    }

    activeIndex.value =
        activeIndex.value < 0
            ? delta === 1
                ? 0
                : count - 1
            : (activeIndex.value + delta + count) % count;
}

function onInputKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Home' && query.value === '') {
        event.preventDefault();
        activeIndex.value = options.value.length > 0 ? 0 : -1;
    } else if (event.key === 'End' && query.value === '') {
        event.preventDefault();
        activeIndex.value = options.value.length - 1;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const option = options.value[activeIndex.value];

        if (option) {
            choose(option);
        }
    } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closePanel();
    } else if (event.key === 'Tab') {
        // Focus the trigger first: the browser then tabs on from it, not from
        // a panel that sits at the end of <body>.
        closePanel();
    }
}

function onTriggerKeydown(event: KeyboardEvent): void {
    if (props.disabled) {
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        openPanel();
    } else if (
        event.key.length === 1 &&
        event.key !== ' ' &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey
    ) {
        event.preventDefault();
        openPanel(event.key);
    }
}

function onDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;

    if (
        target &&
        (fieldRef.value?.contains(target) || panelRef.value?.contains(target))
    ) {
        return;
    }

    closePanel(false);
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', onDocumentClick);
    } else {
        document.removeEventListener('click', onDocumentClick);
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});

watch(activeIndex, (index) => {
    void nextTick(() =>
        document
            .getElementById(optionId(index))
            ?.scrollIntoView?.({ block: 'nearest' }),
    );
});
watch(
    () => JSON.stringify(props.params ?? {}),
    () => {
        reset();

        if (open.value) {
            refresh();
        }
    },
);
watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            closePanel(false);
        }
    },
);

const statusText = computed(() => {
    if (failed.value) {
        return t('common.searchSelect.error');
    }

    if (belowMinimum.value) {
        return t('common.searchSelect.minChars', { count: props.minChars });
    }

    if (loading.value) {
        return t('common.searchSelect.searching');
    }

    if (options.value.length === 0) {
        return t('common.searchSelect.noResults');
    }

    const parts = [
        t('common.searchSelect.resultsCount', { count: total.value }),
    ];

    if (props.multiple && items.value.length > 0) {
        parts.unshift(
            t('common.searchSelect.selectedCount', {
                count: items.value.length,
            }),
        );
    }

    if (hasMore.value) {
        parts.push(t('common.searchSelect.moreResults'));
    }

    return parts.join(' · ');
});

const boxClass = computed(() => [
    'min-h-10 w-full rounded-md border bg-white px-3 py-1.5 pe-16 text-start text-sm dark:bg-neutral-800',
    props.error
        ? 'border-red-500 dark:border-red-400'
        : open.value
          ? 'border-blue-500 ring-2 ring-blue-500/30'
          : 'border-gray-300 dark:border-neutral-700',
    props.disabled
        ? 'cursor-not-allowed opacity-60'
        : 'cursor-pointer focus-visible:border-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500/30 focus-visible:outline-none',
]);

defineExpose({
    focus: () => triggerRef.value?.focus(),
    refresh,
});
</script>

<template>
    <div ref="fieldRef">
        <label :id="labelId" :for="id" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <input
            v-if="name && !multiple"
            type="hidden"
            :name="name"
            :value="single ? String(single.id) : ''"
        />
        <template v-if="name && multiple">
            <input
                v-for="picked in items"
                :key="String(picked.id)"
                type="hidden"
                :name="name"
                :value="String(picked.id)"
            />
        </template>
        <div ref="boxRef" class="relative">
            <input
                v-if="required && !hasValue && !disabled"
                type="text"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                autocomplete="off"
                required
                value=""
                data-testid="search-select-guard"
                @focus="triggerRef?.focus()"
            />
            <div
                v-if="multiple"
                class="flex flex-wrap items-center gap-1.5"
                :class="boxClass"
                @click.self="openPanel()"
            >
                <span
                    v-for="picked in items"
                    :key="String(picked.id)"
                    class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-gray-300 bg-gray-50 py-0.5 ps-1 pe-1 dark:border-neutral-600 dark:bg-neutral-700"
                    data-testid="search-select-chip"
                >
                    <StoreSearchOptionRow
                        :option="picked"
                        variant="chip"
                        class="max-w-56"
                    />
                    <button
                        type="button"
                        class="grid size-5 shrink-0 cursor-pointer place-items-center rounded-full text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed dark:text-neutral-300 dark:hover:bg-neutral-600"
                        :aria-label="
                            t('common.searchSelect.remove', {
                                label: picked.label,
                            })
                        "
                        :disabled="disabled"
                        @click="removeOne(picked)"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </span>
                <button
                    :id="id"
                    ref="triggerRef"
                    type="button"
                    class="min-w-24 flex-1 cursor-pointer py-0.5 text-start text-gray-500 focus-visible:outline-none disabled:cursor-not-allowed dark:text-neutral-400"
                    aria-haspopup="listbox"
                    :aria-expanded="open"
                    :aria-controls="open ? panelId : undefined"
                    :aria-labelledby="`${labelId} ${id}`"
                    :aria-required="required || undefined"
                    :aria-invalid="error ? true : undefined"
                    :aria-describedby="describedBy"
                    :disabled="disabled"
                    @click="open ? closePanel() : openPanel()"
                    @keydown="onTriggerKeydown"
                >
                    {{
                        hasValue
                            ? t('common.searchSelect.addMore')
                            : (placeholder ??
                              t('common.searchSelect.placeholder'))
                    }}
                </button>
            </div>
            <button
                v-else
                :id="id"
                ref="triggerRef"
                type="button"
                class="flex w-full items-center gap-2"
                :class="boxClass"
                aria-haspopup="listbox"
                :aria-expanded="open"
                :aria-controls="open ? panelId : undefined"
                :aria-labelledby="`${labelId} ${id}`"
                :aria-required="required || undefined"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                :disabled="disabled"
                @click="open ? closePanel() : openPanel()"
                @keydown="onTriggerKeydown"
            >
                <slot
                    v-if="single"
                    name="selected"
                    :option="single"
                    :clear="clear"
                >
                    <StoreSearchOptionRow :option="single" variant="selected" />
                </slot>
                <span
                    v-else
                    class="min-w-0 flex-1 truncate text-gray-500 dark:text-neutral-400"
                    >{{
                        placeholder ?? t('common.searchSelect.placeholder')
                    }}</span
                >
            </button>
            <span
                class="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1 text-gray-400"
            >
                <button
                    v-if="clearable && hasValue && !disabled"
                    type="button"
                    class="pointer-events-auto grid size-6 cursor-pointer place-items-center rounded hover:bg-gray-100 dark:hover:bg-neutral-700"
                    :aria-label="
                        t(
                            multiple
                                ? 'common.searchSelect.clearAll'
                                : 'common.searchSelect.clear',
                        )
                    "
                    data-testid="search-select-clear"
                    @click="clear"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
                <ChevronDown class="size-4" aria-hidden="true" />
            </span>
        </div>
        <p
            v-if="helper"
            :id="helperId"
            class="mt-1 text-xs text-gray-500 dark:text-neutral-400"
        >
            {{ helper }}
        </p>
        <div :id="errorId"><FieldError :message="error" /></div>
        <Teleport to="body">
            <div v-if="open" data-search-select-layer>
                <div
                    v-if="phone"
                    class="fixed inset-0 z-[69] bg-black/40"
                    aria-hidden="true"
                    data-testid="search-select-backdrop"
                    @click="closePanel()"
                ></div>
                <div
                    :id="panelId"
                    ref="panelRef"
                    class="fixed z-[70] overflow-hidden rounded-lg border border-gray-300 bg-white text-gray-900 shadow-xl ring-1 ring-black/5 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                    :class="phone ? 'inset-x-2 top-2' : ''"
                    :style="panelStyle"
                >
                    <div
                        class="relative border-b border-gray-200 p-2 dark:border-neutral-700"
                    >
                        <Search
                            class="pointer-events-none absolute start-4 top-1/2 size-4 -translate-y-1/2 text-gray-400"
                            aria-hidden="true"
                        />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="true"
                            autocomplete="off"
                            maxlength="100"
                            class="w-full rounded-md border border-gray-300 py-2 ps-8 pe-14 text-base sm:text-sm dark:border-neutral-600 dark:bg-neutral-900"
                            :aria-controls="listboxId"
                            :aria-activedescendant="
                                activeIndex >= 0
                                    ? optionId(activeIndex)
                                    : undefined
                            "
                            :aria-label="label"
                            :placeholder="
                                searchPlaceholder ??
                                t('common.searchSelect.searchPlaceholder')
                            "
                            @keydown="onInputKeydown"
                        />
                        <span
                            class="absolute end-4 top-1/2 flex -translate-y-1/2 items-center gap-1 text-gray-400"
                        >
                            <LoaderCircle
                                v-if="loading"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                                data-testid="search-select-spinner"
                            />
                            <button
                                v-if="query !== ''"
                                type="button"
                                tabindex="-1"
                                class="grid size-5 cursor-pointer place-items-center rounded hover:bg-gray-100 dark:hover:bg-neutral-700"
                                :aria-label="
                                    t('common.searchSelect.clearSearch')
                                "
                                @click="
                                    query = '';
                                    inputRef?.focus();
                                "
                            >
                                <X class="size-3.5" aria-hidden="true" />
                            </button>
                        </span>
                    </div>
                    <div
                        ref="scrollRef"
                        class="overflow-y-auto py-1"
                        :style="scrollStyle"
                        @scroll="onOptionsScroll(scrollRef)"
                    >
                        <div
                            v-if="failed"
                            role="alert"
                            class="m-2 flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                            data-testid="search-select-error"
                        >
                            <CircleAlert
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="flex-1">{{
                                t('common.searchSelect.error')
                            }}</span>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-red-300 px-2 py-1 text-xs font-medium dark:border-red-800"
                                data-testid="search-select-retry"
                                @click="refresh()"
                            >
                                <RotateCw class="size-3.5" aria-hidden="true" />
                                {{ t('common.searchSelect.retry') }}
                            </button>
                        </div>
                        <p
                            v-if="belowMinimum"
                            class="flex items-center gap-2 px-4 py-6 text-sm text-gray-500 dark:text-neutral-400"
                            data-testid="search-select-min-chars"
                        >
                            <Keyboard class="size-4" aria-hidden="true" />
                            {{
                                t('common.searchSelect.minChars', {
                                    count: minChars,
                                })
                            }}
                        </p>
                        <div
                            v-else-if="loading && options.length === 0"
                            aria-hidden="true"
                            data-testid="search-select-skeleton"
                        >
                            <div
                                v-for="row in 3"
                                :key="row"
                                class="flex min-h-14 items-center gap-3 px-3 py-2"
                            >
                                <span
                                    class="size-10 shrink-0 animate-pulse rounded-full bg-gray-200 dark:bg-neutral-700"
                                ></span>
                                <span class="flex-1 space-y-2">
                                    <span
                                        class="block h-3 w-2/3 animate-pulse rounded bg-gray-200 dark:bg-neutral-700"
                                    ></span>
                                    <span
                                        class="block h-2.5 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-neutral-700/70"
                                    ></span>
                                </span>
                            </div>
                        </div>
                        <slot
                            v-else-if="
                                !loading && !failed && options.length === 0
                            "
                            name="empty"
                            :query="query"
                        >
                            <div
                                class="flex flex-col items-center gap-2 px-4 py-8 text-center text-sm text-gray-500 dark:text-neutral-400"
                                data-testid="search-select-empty"
                            >
                                <PackageSearch
                                    class="size-8 text-gray-400"
                                    aria-hidden="true"
                                />
                                <p
                                    class="font-medium text-gray-700 dark:text-neutral-200"
                                >
                                    {{ t('common.searchSelect.noResults') }}
                                </p>
                                <p v-if="query !== ''" class="text-xs">
                                    {{
                                        t('common.searchSelect.noResultsFor', {
                                            query,
                                        })
                                    }}
                                </p>
                            </div>
                        </slot>
                        <div
                            :id="listboxId"
                            role="listbox"
                            :aria-label="
                                t('common.searchSelect.panelLabel', { label })
                            "
                            :aria-multiselectable="multiple || undefined"
                            :aria-busy="loading"
                            :hidden="options.length === 0"
                        >
                            <div
                                v-for="(option, index) in options"
                                :id="optionId(index)"
                                :key="String(option.id)"
                                role="option"
                                :aria-selected="isSelected(option)"
                                class="flex min-h-14 cursor-pointer items-center gap-3 border-s-2 px-3 py-2 text-start"
                                :class="[
                                    index === activeIndex
                                        ? 'bg-gray-100 dark:bg-neutral-700'
                                        : '',
                                    isSelected(option)
                                        ? 'border-blue-600 dark:border-blue-400'
                                        : 'border-transparent',
                                ]"
                                @click="choose(option)"
                                @mousemove="activeIndex = index"
                            >
                                <slot
                                    name="option"
                                    :option="option"
                                    :query="query"
                                    :active="index === activeIndex"
                                    :selected="isSelected(option)"
                                >
                                    <StoreSearchOptionRow
                                        :option="option"
                                        :query="query"
                                    >
                                        <template
                                            v-if="$slots.image"
                                            #image="slotProps"
                                            ><slot
                                                name="image"
                                                v-bind="slotProps"
                                        /></template>
                                        <template
                                            v-if="$slots.badge"
                                            #badge="slotProps"
                                            ><slot
                                                name="badge"
                                                v-bind="slotProps"
                                        /></template>
                                    </StoreSearchOptionRow>
                                    <Check
                                        v-if="isSelected(option)"
                                        class="size-4 shrink-0 text-blue-600 dark:text-blue-400"
                                        aria-hidden="true"
                                    />
                                </slot>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-between gap-2 border-t border-gray-200 px-3 py-1.5 text-xs text-gray-500 dark:border-neutral-700 dark:text-neutral-400"
                    >
                        <span
                            role="status"
                            aria-live="polite"
                            data-testid="search-select-status"
                            >{{ statusText }}</span
                        >
                        <button
                            v-if="phone"
                            type="button"
                            class="cursor-pointer rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 dark:border-neutral-600 dark:text-neutral-200"
                            @click="closePanel()"
                        >
                            {{ t('common.searchSelect.close') }}
                        </button>
                        <span
                            v-else
                            dir="ltr"
                            class="inline-flex items-center gap-1"
                            :title="t('common.searchSelect.keyboardHint')"
                        >
                            <Keyboard class="size-3.5" aria-hidden="true" />
                            <kbd
                                class="rounded border border-gray-300 px-1 dark:border-neutral-600"
                                >↑</kbd
                            >
                            <kbd
                                class="rounded border border-gray-300 px-1 dark:border-neutral-600"
                                >↓</kbd
                            >
                            <kbd
                                class="rounded border border-gray-300 px-1 dark:border-neutral-600"
                                >Enter</kbd
                            >
                            <kbd
                                class="rounded border border-gray-300 px-1 dark:border-neutral-600"
                                >Esc</kbd
                            >
                        </span>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

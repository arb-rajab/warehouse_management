<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ChevronDown, LoaderCircle, Search } from '@lucide/vue';
import type { ComponentPublicInstance } from 'vue';
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { search as searchProducts } from '@/actions/App/Http/Controllers/Admin/ProductController';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { Paginated, ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    id: string;
    label: string;
    allLabel: string;
    selectedCountLabel: (count: number) => string;
    selected: ProductFilterOption[];
}>();

const model = defineModel<string[]>({ required: true });

const open = ref(false);
const containerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const optionsListRef = ref<HTMLElement | null>(null);
const optionRefs = ref<(HTMLInputElement | null)[]>([]);

const query = ref('');
const results = ref<ProductFilterOption[]>([]);
const page = ref<Paginated<ProductFilterOption> | null>(null);
const loading = ref(false);
let requestSeq = 0;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Only ever grows — a selected id must keep resolving to its name even once
// it scrolls out of `results` (a new search replaces the visible page, but
// the selection itself doesn't change).
const namesById = reactive(new Map<number, string>());

function rememberNames(products: ProductFilterOption[]): void {
    for (const product of products) {
        namesById.set(product.id, product.name);
    }
}

watch(() => props.selected, rememberNames, { immediate: true });

const http = useHttp({});

function fetchPage(pageNumber: number, replace: boolean): void {
    const mySeq = ++requestSeq;
    loading.value = true;

    http.get(
        searchProducts.url({ query: { q: query.value, page: pageNumber } }),
        {
            onSuccess: (response: unknown) => {
                if (mySeq !== requestSeq) {
                    return;
                }

                const body = response as Paginated<ProductFilterOption>;

                results.value = replace
                    ? body.data
                    : [...results.value, ...body.data];
                page.value = body;
                rememberNames(body.data);
                loading.value = false;
            },
        },
    );
}

watch(query, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => fetchPage(1, true), 300);
});

onBeforeUnmount(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});

function onOptionsScroll(): void {
    const el = optionsListRef.value;
    const currentPage = page.value;

    if (!el || !currentPage || loading.value) {
        return;
    }

    if (currentPage.meta.current_page >= currentPage.meta.last_page) {
        return;
    }

    if (el.scrollHeight - el.scrollTop - el.clientHeight > 48) {
        return;
    }

    fetchPage(currentPage.meta.current_page + 1, false);
}

function toggleOpen(): void {
    open.value = !open.value;

    if (open.value) {
        if (results.value.length === 0 && !loading.value) {
            fetchPage(1, true);
        }

        nextTick(() => searchInputRef.value?.focus());
    }
}

function setOptionRef(
    el: Element | ComponentPublicInstance | null,
    index: number,
): void {
    optionRefs.value[index] = el as HTMLInputElement | null;
}

function onOptionKeydown(event: KeyboardEvent, index: number): void {
    const count = results.value.length;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        optionRefs.value[(index + 1) % count]?.focus();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        optionRefs.value[(index - 1 + count) % count]?.focus();
    } else if (event.key === 'Home') {
        event.preventDefault();
        optionRefs.value[0]?.focus();
    } else if (event.key === 'End') {
        event.preventDefault();
        optionRefs.value[count - 1]?.focus();
    }
}

function isChecked(value: string): boolean {
    return model.value.includes(value);
}

function toggleValue(value: string): void {
    model.value = isChecked(value)
        ? model.value.filter((selected) => selected !== value)
        : [...model.value, value];
}

function buttonLabel(): string {
    if (model.value.length === 0) {
        return props.allLabel;
    }

    if (model.value.length === 1) {
        return namesById.get(Number(model.value[0])) ?? props.allLabel;
    }

    return props.selectedCountLabel(model.value.length);
}

function onDocumentClick(event: MouseEvent): void {
    if (
        containerRef.value &&
        !containerRef.value.contains(event.target as Node)
    ) {
        open.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
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
            @click="toggleOpen"
        >
            <span class="truncate">{{ buttonLabel() }}</span>
            <ChevronDown class="h-4 w-4 shrink-0 text-gray-400" />
        </button>
        <div
            v-if="open"
            class="absolute z-10 mt-1 w-max max-w-xs min-w-full rounded-md border border-gray-300 bg-white p-2 shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
        >
            <div class="relative mb-2">
                <Search
                    class="pointer-events-none absolute start-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                />
                <input
                    ref="searchInputRef"
                    v-model="query"
                    type="search"
                    :placeholder="t('cellLog.filters.searchPlaceholder')"
                    class="w-full rounded-md border border-gray-300 py-1.5 ps-8 pe-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                />
            </div>

            <div
                ref="optionsListRef"
                role="listbox"
                class="max-h-64 overflow-y-auto"
                @scroll="onOptionsScroll"
            >
                <p
                    v-if="loading && results.length === 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-gray-500 dark:text-neutral-400"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
                <p
                    v-else-if="!loading && results.length === 0"
                    class="px-2 py-1 text-sm text-gray-500 dark:text-neutral-400"
                >
                    {{ t('cellLog.filters.noProductsFound') }}
                </p>

                <label
                    v-for="(product, index) in results"
                    :key="product.id"
                    role="option"
                    :aria-selected="isChecked(product.id.toString())"
                    class="flex cursor-pointer items-start gap-2 rounded px-2 py-1 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700"
                >
                    <input
                        :ref="(el) => setOptionRef(el, index)"
                        type="checkbox"
                        class="mt-0.5 shrink-0"
                        :checked="isChecked(product.id.toString())"
                        @change="toggleValue(product.id.toString())"
                        @keydown="onOptionKeydown($event, index)"
                    />
                    <span class="break-words">{{ product.name }}</span>
                </label>

                <p
                    v-if="loading && results.length > 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-gray-500 dark:text-neutral-400"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ChevronDown, LoaderCircle, Search } from '@lucide/vue';
import { nextTick, reactive, ref, watch } from 'vue';
import { search as searchProducts } from '@/actions/App/Http/Controllers/Admin/ProductController';
import { debounce, fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useDismissibleListbox } from '@/lib/useDismissibleListbox';
import type { Paginated, ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    id: string;
    label: string;
    placeholder: string;
}>();

const model = defineModel<ProductFilterOption | null>({ required: true });

const searchInputRef = ref<HTMLInputElement | null>(null);

const query = ref('');
const results = ref<ProductFilterOption[]>([]);
const page = ref<Paginated<ProductFilterOption> | null>(null);
const loading = ref(false);
let requestSeq = 0;

const namesById = reactive(new Map<number, string>());

watch(
    () => model.value,
    (product) => {
        if (product) {
            namesById.set(product.id, product.name);
        }
    },
    { immediate: true },
);

const { open, containerRef, setOptionRef, onOptionKeydown } =
    useDismissibleListbox(() => results.value.length);

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

                for (const product of body.data) {
                    namesById.set(product.id, product.name);
                }

                loading.value = false;
            },
        },
    );
}

const debouncedSearch = debounce(() => fetchPage(1, true), 300);

watch(query, debouncedSearch);

const optionsListRef = ref<HTMLElement | null>(null);

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

function select(product: ProductFilterOption): void {
    model.value = product;
    open.value = false;
}

function buttonLabel(): string {
    if (!model.value) {
        return props.placeholder;
    }

    return namesById.get(model.value.id) ?? props.placeholder;
}
</script>

<template>
    <div ref="containerRef" class="relative">
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <button
            :id="id"
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 px-3 py-2 text-left text-sm dark:border-neutral-700 dark:bg-neutral-800"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="toggleOpen"
        >
            <span class="truncate">{{ buttonLabel() }}</span>
            <ChevronDown class="h-4 w-4 shrink-0 text-gray-400" />
        </button>
        <div
            v-if="open"
            class="absolute z-10 mt-1 w-full rounded-md border border-gray-300 bg-white p-2 shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
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

                <button
                    v-for="(product, index) in results"
                    :key="product.id"
                    :ref="(el) => setOptionRef(el, index)"
                    type="button"
                    role="option"
                    :aria-selected="model?.id === product.id"
                    class="flex w-full cursor-pointer items-start gap-2 rounded px-2 py-1 text-start text-sm hover:bg-gray-100 dark:hover:bg-neutral-700"
                    :class="
                        model?.id === product.id
                            ? 'bg-gray-100 dark:bg-neutral-700'
                            : ''
                    "
                    @click="select(product)"
                    @keydown="onOptionKeydown($event, index)"
                >
                    <span class="break-words">{{ product.name }}</span>
                </button>

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

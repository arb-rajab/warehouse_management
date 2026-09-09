<script setup lang="ts">
import { ChevronDown, LoaderCircle, Search } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import ProductOptionLabel from '@/components/ProductOptionLabel.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useDismissibleListbox } from '@/lib/useDismissibleListbox';
import { useProductSearch } from '@/lib/useProductSearch';
import type { ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    id: string;
    label: string;
    placeholder: string;
}>();

const model = defineModel<ProductFilterOption | null>({ required: true });

const searchInputRef = ref<HTMLInputElement | null>(null);
const optionsListRef = ref<HTMLElement | null>(null);

const {
    query,
    results,
    loading,
    namesById,
    rememberNames,
    fetchFirstPageIfEmpty,
    onOptionsScroll,
} = useProductSearch();

watch(
    () => model.value,
    (product) => {
        if (product) {
            rememberNames([product]);
        }
    },
    { immediate: true },
);

const { open, containerRef, setOptionRef, onOptionKeydown } =
    useDismissibleListbox(() => results.value.length);

function toggleOpen(): void {
    open.value = !open.value;

    if (open.value) {
        fetchFirstPageIfEmpty();

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
                @scroll="onOptionsScroll(optionsListRef)"
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
                    <ProductOptionLabel :product="product" />
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

<script setup lang="ts">
import { ChevronDown, LoaderCircle, Search } from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import ProductOptionLabel from '@/components/ProductOptionLabel.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import {
    useDismissibleListbox,
    useMultiSelectToggle,
} from '@/lib/useDismissibleListbox';
import { useProductSearch } from '@/lib/useProductSearch';
import type { ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    id: string;
    label: string;
    allLabel: string;
    selectedCountLabel: (count: number) => string;
    selected: ProductFilterOption[];
}>();

const model = defineModel<string[]>({ required: true });

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

/**
 * Every product ever seen with full data (not just its resolved name), so a
 * checked product keeps rendering in the pinned section below even after a
 * new search replaces `results` or scrolls it out of view — see the
 * `pinnedProducts`/`visibleResults` split below.
 */
const knownProducts = reactive(new Map<number, ProductFilterOption>());

function rememberProducts(products: ProductFilterOption[]): void {
    for (const product of products) {
        knownProducts.set(product.id, product);
    }
}

watch(
    () => props.selected,
    (products) => {
        rememberProducts(products);
        rememberNames(products);
    },
    { immediate: true },
);
watch(results, rememberProducts);

const { isChecked, toggleValue } = useMultiSelectToggle(model);

const pinnedProducts = computed(() =>
    Array.from(knownProducts.values()).filter((product) =>
        isChecked(product.id.toString()),
    ),
);
const visibleResults = computed(() => {
    const pinnedIds = new Set(
        pinnedProducts.value.map((product) => product.id),
    );

    return results.value.filter((product) => !pinnedIds.has(product.id));
});

const { open, containerRef, setOptionRef, onOptionKeydown } =
    useDismissibleListbox(
        () => pinnedProducts.value.length + visibleResults.value.length,
    );

function toggleOpen(): void {
    open.value = !open.value;

    if (open.value) {
        fetchFirstPageIfEmpty();

        nextTick(() => searchInputRef.value?.focus());
    }
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
</script>

<template>
    <div ref="containerRef" class="relative">
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <button
            :id="id"
            type="button"
            class="flex w-full min-w-48 cursor-pointer items-center justify-between gap-2 rounded-md border border-gray-300 px-3 py-2 text-start text-sm dark:border-neutral-700 dark:bg-neutral-800"
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
                @scroll="onOptionsScroll(optionsListRef)"
            >
                <template v-if="pinnedProducts.length > 0">
                    <p
                        class="px-2 py-1 text-xs font-medium text-gray-400 dark:text-neutral-500"
                    >
                        {{ t('cellLog.filters.selected') }}
                    </p>
                    <label
                        v-for="(product, index) in pinnedProducts"
                        :key="`pinned-${product.id}`"
                        role="option"
                        aria-selected="true"
                        class="flex cursor-pointer items-start gap-2 rounded px-2 py-1 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700"
                    >
                        <input
                            :ref="(el) => setOptionRef(el, index)"
                            type="checkbox"
                            class="mt-0.5 shrink-0"
                            checked
                            @change="toggleValue(product.id.toString())"
                            @keydown="onOptionKeydown($event, index)"
                        />
                        <ProductOptionLabel :product="product" />
                    </label>
                    <hr class="my-1 border-gray-200 dark:border-neutral-700" />
                </template>

                <p
                    v-if="loading && visibleResults.length === 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-gray-500 dark:text-neutral-400"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
                <p
                    v-else-if="!loading && visibleResults.length === 0"
                    class="px-2 py-1 text-sm text-gray-500 dark:text-neutral-400"
                >
                    {{ t('cellLog.filters.noProductsFound') }}
                </p>

                <label
                    v-for="(product, index) in visibleResults"
                    :key="product.id"
                    role="option"
                    :aria-selected="isChecked(product.id.toString())"
                    class="flex cursor-pointer items-start gap-2 rounded px-2 py-1 text-sm hover:bg-gray-100 dark:hover:bg-neutral-700"
                >
                    <input
                        :ref="
                            (el) =>
                                setOptionRef(el, pinnedProducts.length + index)
                        "
                        type="checkbox"
                        class="mt-0.5 shrink-0"
                        :checked="isChecked(product.id.toString())"
                        @change="toggleValue(product.id.toString())"
                        @keydown="
                            onOptionKeydown(
                                $event,
                                pinnedProducts.length + index,
                            )
                        "
                    />
                    <ProductOptionLabel :product="product" />
                </label>

                <p
                    v-if="loading && visibleResults.length > 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-gray-500 dark:text-neutral-400"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
            </div>
        </div>
    </div>
</template>

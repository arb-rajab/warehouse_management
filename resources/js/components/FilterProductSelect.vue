<script setup lang="ts">
import { ChevronDown, LoaderCircle, Search } from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import ProductActiveBadge from '@/components/ProductActiveBadge.vue';
import ProductOptionLabel from '@/components/ProductOptionLabel.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import {
    useDismissibleListbox,
    useMultiSelectToggle,
    usePanelMaxWidth,
    usePanelViewportTracking,
} from '@/lib/useDismissibleListbox';
import { useProductSearch } from '@/lib/useProductSearch';
import type { ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    id: string;
    label: string;
    allLabel: string;
    selectedCountLabel: (count: number) => string;
    selected: ProductFilterOption[];
    /** Search every product, deactivated ones the warehouse no longer holds included. */
    includeInactive?: boolean;
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
} = useProductSearch({ includeInactive: props.includeInactive });

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
const {
    panelMaxWidthPx,
    panelPosition,
    recompute: recomputePanelGeometry,
} = usePanelMaxWidth(containerRef);

function toggleOpen(): void {
    open.value = !open.value;

    if (open.value) {
        recomputePanelGeometry();
        fetchFirstPageIfEmpty();

        nextTick(() => searchInputRef.value?.focus());
    }
}

usePanelViewportTracking(containerRef, open, recomputePanelGeometry);

function deselectAll(): void {
    model.value = [];
}

const singleSelectedProduct = computed(() =>
    model.value.length === 1
        ? knownProducts.get(Number(model.value[0]))
        : undefined,
);

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
            class="flex w-full min-w-48 cursor-pointer items-center justify-between gap-2 rounded-md border border-line-strong bg-surface px-3 py-2 text-start text-sm"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="toggleOpen"
        >
            <span class="min-w-0 flex-1 truncate">{{ buttonLabel() }}</span>
            <ProductActiveBadge
                v-if="singleSelectedProduct"
                :active="singleSelectedProduct.active"
            />
            <ChevronDown class="h-4 w-4 shrink-0 text-muted" />
        </button>
        <div
            v-if="open"
            class="fixed z-[60] w-max rounded-md border border-line-strong bg-surface p-2 shadow-pop"
            :style="{
                top: `${panelPosition.top}px`,
                insetInlineStart: `${panelPosition.insetInlineStart}px`,
                minWidth: `${panelPosition.minWidth}px`,
                maxWidth: `${panelMaxWidthPx}px`,
            }"
        >
            <div class="relative mb-2">
                <Search
                    class="pointer-events-none absolute start-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                />
                <input
                    ref="searchInputRef"
                    v-model="query"
                    type="search"
                    :placeholder="t('cellLog.filters.searchPlaceholder')"
                    class="w-full rounded-md border border-line-strong bg-surface py-1.5 ps-8 pe-2 text-sm"
                />
            </div>

            <div
                ref="optionsListRef"
                role="listbox"
                class="max-h-64 overflow-y-auto"
                @scroll="onOptionsScroll(optionsListRef)"
            >
                <template v-if="pinnedProducts.length > 0">
                    <div class="flex items-center justify-between px-2 py-1">
                        <p class="text-xs font-medium text-muted">
                            {{ t('cellLog.filters.selected') }}
                        </p>
                        <button
                            type="button"
                            data-testid="deselect-all-products"
                            class="cursor-pointer text-xs font-medium text-link hover:underline"
                            @click="deselectAll"
                        >
                            {{ t('cellLog.filters.deselectAll') }}
                        </button>
                    </div>
                    <div class="divide-y divide-line">
                        <label
                            v-for="(product, index) in pinnedProducts"
                            :key="`pinned-${product.id}`"
                            role="option"
                            aria-selected="true"
                            class="flex cursor-pointer items-start gap-2 px-2 py-1.5 text-sm hover:bg-subtle"
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
                    </div>
                    <hr class="my-1 border-line" />
                </template>

                <p
                    v-if="loading && visibleResults.length === 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-muted"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
                <p
                    v-else-if="!loading && visibleResults.length === 0"
                    class="px-2 py-1 text-sm text-muted"
                >
                    {{ t('cellLog.filters.noProductsFound') }}
                </p>

                <div
                    v-if="visibleResults.length > 0"
                    class="divide-y divide-line"
                >
                    <label
                        v-for="(product, index) in visibleResults"
                        :key="product.id"
                        role="option"
                        :aria-selected="isChecked(product.id.toString())"
                        class="flex cursor-pointer items-start gap-2 px-2 py-1.5 text-sm hover:bg-subtle"
                    >
                        <input
                            :ref="
                                (el) =>
                                    setOptionRef(
                                        el,
                                        pinnedProducts.length + index,
                                    )
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
                </div>

                <p
                    v-if="loading && visibleResults.length > 0"
                    class="flex items-center gap-2 px-2 py-1 text-sm text-muted"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                    {{ t('cellLog.filters.searching') }}
                </p>
            </div>
        </div>
    </div>
</template>

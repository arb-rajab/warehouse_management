<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Ban,
    LocateFixed,
    RotateCcw,
    RotateCw,
    Search,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import CellHighlightFilters from '@/components/CellHighlightFilters.vue';
import CellSlot from '@/components/CellSlot.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    emptyCellHighlightFilters,
    matchesCellHighlight,
} from '@/lib/cellHighlight';
import type { CellHighlightFiltersValue } from '@/lib/cellHighlight';
import { columnNumberOptions } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import {
    mapOrientation,
    useMapViewport,
    viewportTransform,
} from '@/lib/mapViewport';
import type {
    CellHighlightSeed,
    ProductFilterOptions,
    CellMapRow,
    CellSlotLocation,
    CellWithLocation,
} from '@/types/admin';

const props = defineProps<{
    rows: CellMapRow[];
    cells: CellWithLocation[];
    flatNumber: number;
    maxFlatNumber: number;
    today: string;
    initialHighlight: CellHighlightSeed;
    jumpToCell: CellSlotLocation | null;
    searchError: boolean;
    filterOptions: ProductFilterOptions;
}>();

const highlightFilters = reactive<CellHighlightFiltersValue>({
    ...emptyCellHighlightFilters(),
    state: props.initialHighlight.state ? [props.initialHighlight.state] : [],
    productIds: props.initialHighlight.productIds.map(String),
});

function highlighted(cell: CellWithLocation | null): boolean {
    return matchesCellHighlight(cell, highlightFilters, props.today);
}

const flatNumberOptions = computed(() =>
    columnNumberOptions(props.maxFlatNumber),
);

const cellsByRowLetter = computed(() => {
    const map = new Map<string, Map<number, CellWithLocation>>();

    for (const cell of props.cells) {
        if (!map.has(cell.row_letter)) {
            map.set(cell.row_letter, new Map());
        }

        map.get(cell.row_letter)?.set(cell.cell_number, cell);
    }

    return map;
});

function cellAt(
    rowLetter: string,
    cellNumber: number,
): CellWithLocation | null {
    return cellsByRowLetter.value.get(rowLetter)?.get(cellNumber) ?? null;
}

function flatExistsForRow(row: CellMapRow): boolean {
    return props.flatNumber <= row.flats_count;
}

interface DisplayItem {
    key: string;
    label: string;
    kind: 'cell' | 'flat-unavailable' | 'cell-unavailable';
    cell: CellWithLocation | null;
}

interface DisplayBand {
    key: string;
    axisLabel: string;
    items: DisplayItem[];
}

const orientation = computed(() => mapOrientation(viewport.state.rotation));

const maxCellsCount = computed(() =>
    Math.max(0, ...props.rows.map((row) => row.cells_count)),
);

function buildItem(row: CellMapRow, cellNumber: number): DisplayItem {
    const label = formatSlot(row.letter, cellNumber, props.flatNumber);

    if (!flatExistsForRow(row)) {
        return { key: label, label, kind: 'flat-unavailable', cell: null };
    }

    if (cellNumber > row.cells_count) {
        return { key: label, label, kind: 'cell-unavailable', cell: null };
    }

    return {
        key: label,
        label,
        kind: 'cell',
        cell: cellAt(row.letter, cellNumber),
    };
}

const itemAxisLabels = computed<number[]>(() => {
    const numbers = Array.from(
        { length: maxCellsCount.value },
        (_, i) => i + 1,
    );

    return orientation.value.reverseNumbers ? [...numbers].reverse() : numbers;
});

/**
 * A row's own cell count when the band's letter label sits right after it (nothing needs to
 * line up beyond it), or `maxCellsCount` — padded with unavailable slots — whenever the band
 * label sits at the far edge (rows) or bands run as columns, so every band's label lines up
 * flush in a single row/column instead of trailing off at a different point per band.
 */
function orderedNumbersFor(row: CellMapRow): number[] {
    const o = orientation.value;
    const count =
        o.bandsAsColumns || o.bandLabelAtEnd
            ? maxCellsCount.value
            : row.cells_count;
    const numbers = Array.from({ length: count }, (_, i) => i + 1);

    return o.reverseNumbers ? [...numbers].reverse() : numbers;
}

const displayBands = computed<DisplayBand[]>(() =>
    props.rows.map((row) => ({
        key: row.letter,
        axisLabel: row.letter,
        items: orderedNumbersFor(row).map((n) => buildItem(row, n)),
    })),
);

function goToFlat(flatNumber: number): void {
    router.get(
        cellsIndex().url,
        { flat_number: flatNumber },
        { preserveState: true, replace: true },
    );
}

const searchQuery = ref('');

function onSearchSubmit(): void {
    const search = searchQuery.value.trim();

    if (search === '') {
        return;
    }

    router.get(
        cellsIndex().url,
        { flat_number: props.flatNumber, search },
        { preserveState: true, replace: true },
    );
}

const viewport = useMapViewport();
const viewportTransformStyle = computed(() =>
    viewportTransform(viewport.state),
);
const viewportZoomPercent = computed(() =>
    Math.round(viewport.state.zoom * 100),
);
const viewportEl = ref<HTMLElement | null>(null);

const pulsingLabel = ref<string | null>(null);
let pulseTimeout: ReturnType<typeof setTimeout> | undefined;

function centerLabelInViewport(label: string): void {
    const container = viewportEl.value;
    const target = document.querySelector(`[data-slot-label="${label}"]`);

    if (!container || !target) {
        return;
    }

    const containerRect = container.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();

    viewport.panBy(
        containerRect.left +
            containerRect.width / 2 -
            (targetRect.left + targetRect.width / 2),
        containerRect.top +
            containerRect.height / 2 -
            (targetRect.top + targetRect.height / 2),
    );
}

/**
 * Cell A1 (the first cell of the first row) is the map's fixed orientation anchor —
 * rotating or resetting the view always re-centers on it so it stays reachable no
 * matter how the letter/number axes have been swapped.
 */
function centerFirstCellInViewport(): void {
    const firstRow = props.rows[0];

    if (!firstRow) {
        return;
    }

    centerLabelInViewport(formatSlot(firstRow.letter, 1, props.flatNumber));
}

/**
 * Rotate/reset recenter without an in-flight CSS transition to measure against. The pan
 * transition normally animates panX/panY changes, but `centerFirstCellInViewport()` reads
 * live layout via `getBoundingClientRect()` right after the DOM patches — if that transition
 * is still interpolating from the pre-rotation pan, the measurement is of a moving target,
 * so the computed offset drifts further off with every rotation until the map is panned
 * entirely outside the viewport. Suppressing the transition for this one synchronous
 * measure-then-set makes the geometry read exact; it's restored afterwards so drag/zoom
 * keep animating normally.
 */
const suppressPanTransition = ref(false);

async function recenterInstantly(mutate: () => void): Promise<void> {
    suppressPanTransition.value = true;
    mutate();
    await nextTick();
    centerFirstCellInViewport();
    await nextTick();
    suppressPanTransition.value = false;
}

async function rotateLeftAndRecenter(): Promise<void> {
    await recenterInstantly(() => viewport.rotateLeft());
}

async function rotateRightAndRecenter(): Promise<void> {
    await recenterInstantly(() => viewport.rotateRight());
}

async function resetViewAndRecenter(): Promise<void> {
    await recenterInstantly(() => viewport.reset());
}

watch(
    () => props.jumpToCell,
    (jumpToCell) => {
        if (!jumpToCell) {
            return;
        }

        const label = formatSlot(
            jumpToCell.row_letter,
            jumpToCell.cell_number,
            jumpToCell.flat_number,
        );
        pulsingLabel.value = label;
        centerLabelInViewport(label);

        clearTimeout(pulseTimeout);
        pulseTimeout = setTimeout(() => {
            pulsingLabel.value = null;
        }, 1500);
    },
    { immediate: true, flush: 'post' },
);
</script>

<template>
    <Head :title="t('cells.title')" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('cells.title') }}</h1>
            <div class="flex items-center gap-4">
                <CellHighlightFilters
                    :model-value="highlightFilters"
                    :products="filterOptions.products"
                />
            </div>
        </div>

        <div
            v-if="rows.length === 0"
            class="text-sm text-gray-500 dark:text-neutral-400"
        >
            {{ t('cells.empty') }}
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <form
                    class="flex items-center gap-2"
                    @submit.prevent="onSearchSubmit"
                >
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('cells.search.placeholder')"
                        class="w-56 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    <button
                        type="submit"
                        :aria-label="t('cells.search.submit')"
                        class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        <Search class="h-4 w-4" />
                    </button>
                </form>
                <span
                    v-if="searchError"
                    role="alert"
                    aria-live="polite"
                    class="text-sm text-red-600 dark:text-red-400"
                >
                    {{ t('cells.search.notFound') }}
                </span>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="n in flatNumberOptions"
                    :key="n"
                    type="button"
                    data-testid="flat-tab"
                    :aria-pressed="n === flatNumber"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        n === flatNumber
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800'
                    "
                    @click="goToFlat(n)"
                >
                    {{ t('rows.show.flat', { n }) }}
                </button>
            </div>

            <div class="mb-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    :title="t('cells.map.zoomOut')"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    @click="viewport.zoomOut"
                >
                    <ZoomOut class="h-4 w-4" />
                </button>
                <span
                    data-testid="zoom-percent"
                    class="w-12 text-center text-sm text-gray-500 dark:text-neutral-400"
                    >{{ viewportZoomPercent }}%</span
                >
                <button
                    type="button"
                    :title="t('cells.map.zoomIn')"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    @click="viewport.zoomIn"
                >
                    <ZoomIn class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    :title="t('cells.map.rotateLeft')"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    @click="rotateLeftAndRecenter"
                >
                    <RotateCcw class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    :title="t('cells.map.rotateRight')"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    @click="rotateRightAndRecenter"
                >
                    <RotateCw class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    :title="t('cells.map.resetView')"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    @click="resetViewAndRecenter"
                >
                    <LocateFixed class="h-4 w-4" />
                </button>
            </div>

            <div
                ref="viewportEl"
                data-testid="map-viewport"
                class="relative h-[32rem] touch-none overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-neutral-800 dark:bg-neutral-950"
                @wheel.prevent="viewport.onWheel"
                @pointerdown="viewport.onPointerDown"
                @pointermove="viewport.onPointerMove"
                @pointerup="viewport.onPointerUp"
                @pointercancel="viewport.onPointerUp"
                @pointerleave="viewport.onPointerUp"
            >
                <div
                    class="flex gap-6 p-2 ease-out"
                    :class="[
                        orientation.bandsAsColumns ? 'flex-row' : 'flex-col',
                        suppressPanTransition
                            ? ''
                            : 'transition-transform duration-150',
                    ]"
                    :style="{
                        transform: viewportTransformStyle,
                        transformOrigin: 'center',
                    }"
                >
                    <div
                        v-if="
                            !orientation.bandsAsColumns &&
                            !orientation.bandLabelAtEnd
                        "
                        class="flex gap-2"
                    >
                        <div class="h-6 w-12 shrink-0"></div>
                        <div
                            v-for="item in itemAxisLabels"
                            :key="item"
                            data-testid="axis-header-item"
                            class="flex h-6 w-32 shrink-0 items-center justify-center text-xs font-medium text-gray-500 dark:text-neutral-400"
                        >
                            {{ item }}
                        </div>
                    </div>

                    <div
                        v-for="band in displayBands"
                        :key="band.key"
                        data-testid="map-row"
                        class="flex gap-2"
                        :class="
                            orientation.bandsAsColumns
                                ? 'flex-col items-center'
                                : 'flex-row items-start'
                        "
                    >
                        <div
                            v-if="!orientation.bandLabelAtEnd"
                            data-testid="band-label"
                            class="flex shrink-0 items-center justify-center bg-gray-50 text-sm font-medium text-gray-500 dark:bg-neutral-950 dark:text-neutral-400"
                            :class="
                                orientation.bandsAsColumns
                                    ? 'h-12 w-32'
                                    : 'h-28 w-12'
                            "
                        >
                            {{ band.axisLabel }}
                        </div>

                        <div
                            class="flex gap-2"
                            :class="
                                orientation.bandsAsColumns
                                    ? 'flex-col'
                                    : 'flex-row'
                            "
                        >
                            <template
                                v-for="item in band.items"
                                :key="item.key"
                            >
                                <CellSlot
                                    v-if="item.kind === 'cell'"
                                    :cell="item.cell"
                                    :label="item.label"
                                    :highlighted="highlighted(item.cell)"
                                    :pulsing="pulsingLabel === item.label"
                                />
                                <div
                                    v-else-if="item.kind === 'flat-unavailable'"
                                    data-testid="flat-not-available"
                                    class="flex h-28 w-32 shrink-0 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-200 bg-gray-50 p-2 text-center text-xs text-gray-400 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-600"
                                >
                                    <Ban class="h-4 w-4" />
                                    {{ t('cells.flatNotAvailable') }}
                                </div>
                                <div v-else class="h-28 w-32 shrink-0"></div>
                            </template>
                        </div>

                        <div
                            v-if="orientation.bandLabelAtEnd"
                            data-testid="band-label"
                            class="flex shrink-0 items-center justify-center bg-gray-50 text-sm font-medium text-gray-500 dark:bg-neutral-950 dark:text-neutral-400"
                            :class="
                                orientation.bandsAsColumns
                                    ? 'h-12 w-32'
                                    : 'h-28 w-12'
                            "
                        >
                            {{ band.axisLabel }}
                        </div>
                    </div>

                    <div
                        v-if="
                            !orientation.bandsAsColumns &&
                            orientation.bandLabelAtEnd
                        "
                        class="flex gap-2"
                    >
                        <div class="h-6 w-12 shrink-0"></div>
                        <div
                            v-for="item in itemAxisLabels"
                            :key="item"
                            data-testid="axis-header-item"
                            class="flex h-6 w-32 shrink-0 items-center justify-center text-xs font-medium text-gray-500 dark:text-neutral-400"
                        >
                            {{ item }}
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>

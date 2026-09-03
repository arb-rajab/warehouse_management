<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Box,
    ChevronDown,
    ChevronUp,
    LayoutGrid,
    LocateFixed,
    Maximize2,
    Minimize2,
    Orbit,
    RotateCcw,
    RotateCw,
    Search,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import CellHighlightFilters from '@/components/CellHighlightFilters.vue';
import CellMap3D from '@/components/CellMap3D.vue';
import CellSlot from '@/components/CellSlot.vue';
import PageHeader from '@/components/PageHeader.vue';
import PalletActionsDialog from '@/components/PalletActionsDialog.vue';
import ToggleCellActiveDialog from '@/components/ToggleCellActiveDialog.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    countActiveCellHighlightFilters,
    emptyCellHighlightFilters,
    matchesCellHighlight,
} from '@/lib/cellHighlight';
import type { CellHighlightFiltersValue } from '@/lib/cellHighlight';
import {
    columnNumberOptions,
    countBadgeClass,
    mapToolbarButtonClass,
    matchNavButtonClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import {
    mapOrientation,
    useMapViewport,
    viewportTransform,
} from '@/lib/mapViewport';
import type {
    Cell,
    CellHighlightSample,
    CellHighlightSeed,
    CellMap3DBand,
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
    cellHighlightSamples: CellHighlightSample[];
}>();

const highlightFilters = reactive<CellHighlightFiltersValue>({
    ...emptyCellHighlightFilters(),
    state: props.initialHighlight.state ? [props.initialHighlight.state] : [],
    productIds: props.initialHighlight.productIds.map(String),
    expiresWithinDays:
        props.initialHighlight.expiresWithinDays !== null
            ? String(props.initialHighlight.expiresWithinDays)
            : '',
    expired: props.initialHighlight.expired,
    inactive: props.initialHighlight.inactive,
});

function highlighted(cell: CellWithLocation | null): boolean {
    return matchesCellHighlight(cell, highlightFilters, props.today);
}

const hasActiveHighlight = computed(
    () => countActiveCellHighlightFilters(highlightFilters) > 0,
);

/**
 * Every sample matching the active highlight filters — computed once from
 * `cellHighlightSamples` (loaded for every flat, unlike `cells` which is
 * scoped to the one on screen) and reused by the per-flat/total match counts
 * below and by `orderedMatches` for next/previous-match navigation.
 */
const matchingSamples = computed(() =>
    props.cellHighlightSamples.filter((sample) =>
        matchesCellHighlight(sample, highlightFilters, props.today),
    ),
);

/** How many matching cells fall on each flat, for the flat tabs' badges. */
const flatMatchCounts = computed(() => {
    const counts = new Map<number, number>();

    for (const sample of matchingSamples.value) {
        counts.set(
            sample.flat_number,
            (counts.get(sample.flat_number) ?? 0) + 1,
        );
    }

    return counts;
});

/** The warehouse-wide match total — the sum of every flat tab's own count. */
const totalMatchCount = computed(() => matchingSamples.value.length);

const rowOrderIndex = computed(() => {
    const map = new Map<string, number>();
    props.rows.forEach((row, index) => map.set(row.letter, index));

    return map;
});

/**
 * Matching cells in map-reading order (row order as displayed, then cell
 * number within a row, then flat number) — what next/previous-match
 * navigation cycles through.
 */
const orderedMatches = computed(() =>
    [...matchingSamples.value].sort((a, b) => {
        const rowDiff =
            (rowOrderIndex.value.get(a.row_letter) ?? Number.MAX_SAFE_INTEGER) -
            (rowOrderIndex.value.get(b.row_letter) ?? Number.MAX_SAFE_INTEGER);

        if (rowDiff !== 0) {
            return rowDiff;
        }

        if (a.cell_number !== b.cell_number) {
            return a.cell_number - b.cell_number;
        }

        return a.flat_number - b.flat_number;
    }),
);

const focusedMatchIndex = ref<number | null>(null);

watch(highlightFilters, () => {
    focusedMatchIndex.value = null;
});

/**
 * Jumps to the match at `index` (wrapping is the caller's job). In 3D mode
 * every flat is already rendered, so it always just moves/pitches the
 * camera locally; in 2D it recenters in place when the match is already on
 * the current flat, or reloads onto the matching flat otherwise (reusing
 * the location-search machinery so the existing `jumpToCell` pulse-and-
 * center watcher picks it up once loaded).
 */
function focusMatchAt(index: number): void {
    const match = orderedMatches.value[index];

    if (!match) {
        return;
    }

    focusedMatchIndex.value = index;

    const label = formatSlot(
        match.row_letter,
        match.cell_number,
        match.flat_number,
    );

    if (viewMode.value === '3d' || match.flat_number === props.flatNumber) {
        pulseAndCenterLabel(
            label,
            match.row_letter,
            match.cell_number,
            match.flat_number,
        );

        return;
    }

    router.get(
        cellsIndex().url,
        { flat_number: match.flat_number, search: label, ...highlightQuery() },
        { preserveState: true, replace: true },
    );
}

function jumpToNextMatch(): void {
    const { length } = orderedMatches.value;

    if (length === 0) {
        return;
    }

    focusMatchAt(((focusedMatchIndex.value ?? -1) + 1) % length);
}

function jumpToPreviousMatch(): void {
    const { length } = orderedMatches.value;

    if (length === 0) {
        return;
    }

    focusMatchAt(
        ((((focusedMatchIndex.value ?? 0) - 1) % length) + length) % length,
    );
}

/**
 * The current highlight filters, re-serialized as the same query keys the
 * dashboard deep-links with — kept in every in-page navigation (flat switch,
 * search) so the active highlight survives instead of dropping out of the
 * URL. `state` can only carry one value here since the backend seed still
 * validates it as a single string (see `.ai/rules/components-js-lib.md`).
 */
function highlightQuery(): Record<string, FormDataConvertible> {
    const query: Record<string, FormDataConvertible> = {};

    if (highlightFilters.state.length === 1) {
        query.state = highlightFilters.state[0];
    }

    if (highlightFilters.productIds.length > 0) {
        query.product_id = highlightFilters.productIds;
    }

    if (highlightFilters.expiresWithinDays !== '') {
        query.expires_within_days = Number(highlightFilters.expiresWithinDays);
    }

    if (highlightFilters.expired) {
        query.expired = true;
    }

    if (highlightFilters.inactive) {
        query.is_active = false;
    }

    return query;
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
        { flat_number: flatNumber, ...highlightQuery() },
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
        { flat_number: props.flatNumber, search, ...highlightQuery() },
        { preserveState: true, replace: true },
    );
}

const isMapFullscreen = ref(false);

function toggleMapFullscreen(): void {
    isMapFullscreen.value = !isMapFullscreen.value;
}

function onMapFullscreenKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isMapFullscreen.value) {
        isMapFullscreen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('keydown', onMapFullscreenKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onMapFullscreenKeydown);
});

const toggleActiveDialogOpen = ref(false);
const toggleActiveCell = ref<Cell | null>(null);
const toggleActiveLabel = ref('');

function openToggleActiveDialog(cell: Cell | null, label: string): void {
    if (!cell) {
        return;
    }

    toggleActiveCell.value = cell;
    toggleActiveLabel.value = label;
    toggleActiveDialogOpen.value = true;
}

const palletActionsDialogOpen = ref(false);
const palletActionsCell = ref<Cell | null>(null);
const palletActionsLabel = ref('');

function openPalletActionsDialog(cell: Cell | null, label: string): void {
    if (!cell) {
        return;
    }

    palletActionsCell.value = cell;
    palletActionsLabel.value = label;
    palletActionsDialogOpen.value = true;
}

const palletActionError = computed(
    () => (usePage().props.errors as Partial<Record<'action', string>>)?.action,
);

const viewport = useMapViewport();
const viewportTransformStyle = computed(() =>
    viewportTransform(viewport.state),
);
const viewportZoomPercent = computed(() =>
    Math.round(viewport.state.zoom * 100),
);
const viewportEl = ref<HTMLElement | null>(null);

const viewMode = ref<'2d' | '3d'>('2d');
const map3DRef = ref<InstanceType<typeof CellMap3D> | null>(null);

function setViewMode(mode: '2d' | '3d'): void {
    viewMode.value = mode;

    // A fresh CellMap3D always mounts in walk mode (it owns cameraMode
    // internally and doesn't persist across unmounts) — resetting the
    // mirror here keeps this toggle button's pressed-state from showing a
    // stale "orbit" left over from a previous time in the 3D view.
    if (mode === '3d') {
        cameraDisplayMode.value = 'walk';
    }
}

/**
 * A read-only mirror of CellMap3D's own `cameraMode`, kept in sync via its
 * `camera-mode-change` emit — only needed so this toolbar button can show
 * which camera mode is active. `setCameraMode` (exposed by the child) is
 * the one source of truth; this ref never drives the camera itself.
 */
const cameraDisplayMode = ref<'walk' | 'orbit'>('walk');

function toggleCameraMode(): void {
    map3DRef.value?.setCameraMode(
        cameraDisplayMode.value === 'walk' ? 'orbit' : 'walk',
    );
}

const pulsingLabel = ref<string | null>(null);
let pulseTimeout: ReturnType<typeof setTimeout> | undefined;

/**
 * Every flat's cells, reshaped for the 3D view (CellMap3D.vue), which renders
 * every flat at once rather than paging one at a time like the 2D grid.
 * Built from `cellHighlightSamples` — already loaded for every flat, for the
 * 2D map's per-flat match badges — reusing `matchesCellHighlight` exactly as
 * `matchingSamples` does, so the two views can never disagree on what counts
 * as a match.
 */
const map3DBands = computed<CellMap3DBand[]>(() => {
    const itemsByRow = new Map<string, CellMap3DBand['items']>();

    for (const row of props.rows) {
        itemsByRow.set(row.letter, []);
    }

    for (const sample of props.cellHighlightSamples) {
        const label = formatSlot(
            sample.row_letter,
            sample.cell_number,
            sample.flat_number,
        );

        itemsByRow.get(sample.row_letter)?.push({
            cellId: sample.cell_id,
            cellNumber: sample.cell_number,
            flatNumber: sample.flat_number,
            state: sample.state,
            isActive: sample.is_active,
            highlighted: matchesCellHighlight(
                sample,
                highlightFilters,
                props.today,
            ),
            pulsing: pulsingLabel.value === label,
            pallet: sample.pallet,
        });
    }

    return props.rows.map((row) => ({
        letter: row.letter,
        items: itemsByRow.get(row.letter) ?? [],
    }));
});

/**
 * Pulses and focuses the cell at `label` — recentering the 2D viewport, or
 * moving/pitching the 3D camera to face it (`rowLetter`/`cellNumber`/
 * `flatNumber` are needed for the latter since the 3D view has no DOM nodes
 * to measure).
 */
function pulseAndCenterLabel(
    label: string,
    rowLetter: string,
    cellNumber: number,
    flatNumber: number,
): void {
    pulsingLabel.value = label;

    if (viewMode.value === '3d') {
        map3DRef.value?.focusCell(rowLetter, cellNumber, flatNumber);
    } else {
        centerLabelInViewport(label);
    }

    clearTimeout(pulseTimeout);
    pulseTimeout = setTimeout(() => {
        pulsingLabel.value = null;
    }, 1500);
}

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
    if (viewMode.value === '3d') {
        map3DRef.value?.resetView();

        return;
    }

    await recenterInstantly(() => viewport.reset());
}

watch(
    () => props.jumpToCell,
    (jumpToCell) => {
        if (!jumpToCell) {
            return;
        }

        pulseAndCenterLabel(
            formatSlot(
                jumpToCell.row_letter,
                jumpToCell.cell_number,
                jumpToCell.flat_number,
            ),
            jumpToCell.row_letter,
            jumpToCell.cell_number,
            jumpToCell.flat_number,
        );
    },
    { immediate: true, flush: 'post' },
);
</script>

<template>
    <Head :title="t('cells.title')" />

    <AdminLayout>
        <PageHeader :title="t('cells.title')">
            <div class="flex items-center gap-4">
                <div v-if="hasActiveHighlight" class="flex items-center gap-2">
                    <span
                        data-testid="total-match-count"
                        class="text-sm text-gray-500 dark:text-neutral-400"
                    >
                        {{
                            t('cells.filters.matchCount', {
                                count: totalMatchCount,
                            })
                        }}
                    </span>
                    <template v-if="totalMatchCount > 0">
                        <button
                            type="button"
                            :title="t('cells.filters.previousMatch')"
                            data-testid="previous-match"
                            :class="matchNavButtonClass"
                            @click="jumpToPreviousMatch"
                        >
                            <ChevronUp class="h-4 w-4" />
                        </button>
                        <span
                            v-if="focusedMatchIndex !== null"
                            data-testid="match-position"
                            class="text-sm text-gray-500 dark:text-neutral-400"
                        >
                            {{
                                t('cells.filters.matchPosition', {
                                    current: focusedMatchIndex + 1,
                                    total: totalMatchCount,
                                })
                            }}
                        </span>
                        <button
                            type="button"
                            :title="t('cells.filters.nextMatch')"
                            data-testid="next-match"
                            :class="matchNavButtonClass"
                            @click="jumpToNextMatch"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </button>
                    </template>
                </div>
                <CellHighlightFilters
                    :model-value="highlightFilters"
                    :products="filterOptions.products"
                />
            </div>
        </PageHeader>

        <ActionErrorBanner :message="palletActionError" />

        <div
            v-if="rows.length === 0"
            class="text-sm text-gray-500 dark:text-neutral-400"
        >
            {{ t('cells.empty') }}
        </div>

        <template v-else>
            <div
                data-testid="map-section"
                :class="
                    isMapFullscreen
                        ? 'fixed inset-0 z-50 flex flex-col overflow-auto bg-white p-4 dark:bg-neutral-950'
                        : ''
                "
            >
                <div
                    class="mb-4 space-y-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex flex-wrap items-center gap-2">
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
                                :class="mapToolbarButtonClass"
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

                    <div v-if="viewMode === '2d'" class="flex flex-wrap gap-2">
                        <button
                            v-for="n in flatNumberOptions"
                            :key="n"
                            type="button"
                            data-testid="flat-tab"
                            :aria-pressed="n === flatNumber"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                            :class="
                                n === flatNumber
                                    ? selectedToggleClass
                                    : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800'
                            "
                            @click="goToFlat(n)"
                        >
                            {{ t('rows.show.flat', { n }) }}
                            <span
                                v-if="
                                    hasActiveHighlight &&
                                    (flatMatchCounts.get(n) ?? 0) > 0
                                "
                                data-testid="flat-match-count"
                                :class="countBadgeClass"
                            >
                                {{ flatMatchCounts.get(n) }}
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <button
                        type="button"
                        :title="t('cells.map.view2d')"
                        data-testid="view-mode-2d"
                        :aria-pressed="viewMode === '2d'"
                        :class="[
                            mapToolbarButtonClass,
                            viewMode === '2d' ? selectedToggleClass : '',
                        ]"
                        @click="setViewMode('2d')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        :title="t('cells.map.view3d')"
                        data-testid="view-mode-3d"
                        :aria-pressed="viewMode === '3d'"
                        :class="[
                            mapToolbarButtonClass,
                            viewMode === '3d' ? selectedToggleClass : '',
                        ]"
                        @click="setViewMode('3d')"
                    >
                        <Box class="h-4 w-4" />
                    </button>
                    <button
                        v-if="viewMode === '3d'"
                        type="button"
                        :title="t('cells.map.overview')"
                        data-testid="camera-mode-orbit"
                        :aria-pressed="cameraDisplayMode === 'orbit'"
                        :class="[
                            mapToolbarButtonClass,
                            cameraDisplayMode === 'orbit'
                                ? selectedToggleClass
                                : '',
                        ]"
                        @click="toggleCameraMode"
                    >
                        <Orbit class="h-4 w-4" />
                    </button>
                    <template v-if="viewMode === '2d'">
                        <button
                            type="button"
                            :title="t('cells.map.zoomOut')"
                            :class="mapToolbarButtonClass"
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
                            :class="mapToolbarButtonClass"
                            @click="viewport.zoomIn"
                        >
                            <ZoomIn class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            :title="t('cells.map.rotateLeft')"
                            :class="mapToolbarButtonClass"
                            @click="rotateLeftAndRecenter"
                        >
                            <RotateCcw class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            :title="t('cells.map.rotateRight')"
                            :class="mapToolbarButtonClass"
                            @click="rotateRightAndRecenter"
                        >
                            <RotateCw class="h-4 w-4" />
                        </button>
                    </template>
                    <button
                        type="button"
                        :title="t('cells.map.resetView')"
                        :class="mapToolbarButtonClass"
                        @click="resetViewAndRecenter"
                    >
                        <LocateFixed class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        :title="
                            isMapFullscreen
                                ? t('cells.map.exitFullscreen')
                                : t('cells.map.fullscreen')
                        "
                        :class="mapToolbarButtonClass"
                        @click="toggleMapFullscreen"
                    >
                        <Minimize2 v-if="isMapFullscreen" class="h-4 w-4" />
                        <Maximize2 v-else class="h-4 w-4" />
                    </button>
                </div>

                <div
                    v-if="viewMode === '2d'"
                    ref="viewportEl"
                    data-testid="map-viewport"
                    class="relative cursor-grab touch-none overflow-hidden rounded-lg border border-gray-200 bg-gray-50 select-none active:cursor-grabbing dark:border-neutral-800 dark:bg-neutral-950"
                    :class="isMapFullscreen ? 'min-h-0 flex-1' : 'h-[32rem]'"
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
                            orientation.bandsAsColumns
                                ? 'flex-row'
                                : 'flex-col',
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
                                        toggleable
                                        manageable
                                        @toggle-active="
                                            openToggleActiveDialog(
                                                item.cell,
                                                item.label,
                                            )
                                        "
                                        @manage-pallet="
                                            openPalletActionsDialog(
                                                item.cell,
                                                item.label,
                                            )
                                        "
                                    />
                                    <div
                                        v-else-if="
                                            item.kind === 'flat-unavailable'
                                        "
                                        data-testid="flat-not-available"
                                        class="flex h-28 w-32 shrink-0 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-200 bg-gray-50 p-2 text-center text-xs text-gray-400 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-600"
                                    >
                                        <Ban class="h-4 w-4" />
                                        {{ t('cells.flatNotAvailable') }}
                                    </div>
                                    <div
                                        v-else
                                        class="h-28 w-32 shrink-0"
                                    ></div>
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

                <div
                    v-else
                    class="relative overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-800"
                    :class="isMapFullscreen ? 'min-h-0 flex-1' : 'h-[32rem]'"
                >
                    <CellMap3D
                        ref="map3DRef"
                        :bands="map3DBands"
                        @camera-mode-change="
                            (mode) => (cameraDisplayMode = mode)
                        "
                        @manage-pallet="
                            (cell, label) =>
                                openPalletActionsDialog(cell, label)
                        "
                        @toggle-active="
                            (cell, label) => openToggleActiveDialog(cell, label)
                        "
                    />
                </div>
            </div>
        </template>

        <ToggleCellActiveDialog
            v-model:open="toggleActiveDialogOpen"
            :cell="toggleActiveCell"
            :label="toggleActiveLabel"
        />

        <PalletActionsDialog
            v-model:open="palletActionsDialogOpen"
            :cell="palletActionsCell"
            :label="palletActionsLabel"
            :rows="rows"
        />
    </AdminLayout>
</template>

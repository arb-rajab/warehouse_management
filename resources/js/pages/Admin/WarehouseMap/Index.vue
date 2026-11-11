<script lang="ts">
const MAP_COMPONENT = 'Admin/WarehouseMap/Index';

/**
 * What the map held when this page was last left (its `snapshot()`, a
 * `MapCarry`). Back / Forward between two sets of warehouses mounts the page
 * afresh (Inertia swaps history entries without keeping state), so the next
 * mount of the map carries it over; a visit to any other page forgets it.
 * Module scope, so it outlives the component; typed in the setup block,
 * which owns the map's imports.
 */
let carried: unknown = null;
</script>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as warehouseMapIndex,
    rows as warehouseMapRows,
} from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import { update as updateLocale } from '@/actions/App/Http/Controllers/LocaleController';
import PalletTransferDialog from '@/components/PalletTransferDialog.vue';
import type { PalletTransferDialogTarget } from '@/components/PalletTransferDialog.vue';
import SuccessBanner from '@/components/SuccessBanner.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { i18n, isSupportedLocale, t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { productName } from '@/lib/productName';
import type {
    PalletTransferTargets,
    WarehouseFilters,
    WarehouseSummary,
} from '@/types/admin';
import { mountWarehouseMap } from '@/warehouse-map/main';
import type {
    MapCarry,
    PalletTransferRequest,
    WarehouseMapHandle,
    WarehouseSelection,
} from '@/warehouse-map/main';
import '../../../../css/warehouse-map/index.css';

const props = defineProps<{
    /** The URL's `warehouse_ids[]`: the one source of the warehouses shown. */
    filters: WarehouseFilters;
    /** Every warehouse, for the map's switcher. */
    warehouses: WarehouseSummary[];
    /** Sent only on the partial reload the transfer dialog asks for. */
    transferTargets?: PalletTransferTargets;
}>();

const container = ref<HTMLElement | null>(null);
let map: WarehouseMapHandle | null = null;

/**
 * The map's rows, narrowed to the selected warehouses (every warehouse when
 * none is selected) — the same `warehouse_ids[]` the page itself carries.
 */
function rowsUrl(): string {
    return warehouseMapRows.url({
        query: props.filters.warehouse_ids
            ? { warehouse_ids: props.filters.warehouse_ids }
            : {},
    });
}

/**
 * The map's switcher asks for other warehouses: the page is visited again
 * with them in `warehouse_ids[]` (a new history entry, so Back returns to
 * the previous ones) and the view's fragment (`#2d` / `#3d`, which the map
 * writes; a slot or products link is not carried to other warehouses). The
 * page keeps its state; the changed prop then tells the map to load the new
 * rows in place.
 */
function visitWarehouses(ids: WarehouseSelection): void {
    const viewHash = /^#[23]d$/.test(window.location.hash)
        ? window.location.hash
        : '';

    router.get(
        warehouseMapIndex.url({
            query: ids ? { warehouse_ids: [...ids] } : {},
        }) + viewHash,
        {},
        { preserveState: true, preserveScroll: true },
    );
}

watch(
    () => (props.filters.warehouse_ids ?? []).join(','),
    () => void map?.showWarehouses(props.filters.warehouse_ids, rowsUrl()),
);

const transferOpen = ref(false);
const transferTarget = ref<PalletTransferDialogTarget | null>(null);
// Kept here, not read off the prop: a later visit of this page (the
// transfer's own redirect) does not send the optional prop again.
const transferTargets = ref<PalletTransferTargets | null>(
    props.transferTargets ?? null,
);

/** Loads every warehouse's rows for the transfer dialog, once. */
function loadTransferTargets(): void {
    if (transferTargets.value) {
        return;
    }

    router.reload({
        only: ['transferTargets'],
        onSuccess: (page) => {
            transferTargets.value =
                (page.props.transferTargets as
                    PalletTransferTargets | undefined) ?? null;
        },
    });
}

/** The map's "Transfer…" on an occupied shelf. */
function openTransfer(request: PalletTransferRequest): void {
    transferTarget.value = {
        palletId: request.palletId,
        location: formatSlot(
            request.rowLetter,
            request.cellNumber,
            request.flatNumber,
        ),
        productName: productName(request.productName, request.productArName),
        warehouseId: request.warehouseId,
    };
    transferOpen.value = true;
    loadTransferTargets();
}

/** The pallet moved: show it on the map without waiting for a focus. */
function onTransferred(): void {
    void map?.refresh();
}

/** The `warehouseMap` messages of a language (the map module takes plain dictionaries). */
function messagesOf(locale: string): Record<string, string> {
    return (i18n.global.getLocaleMessage(locale) as Record<string, any>)
        .warehouseMap;
}

/**
 * Like the panel's own language switcher: a full reload, so the freshly
 * booted app reads the new locale (and `dir`) server-side.
 */
function switchLocale(locale: string): void {
    router.post(
        updateLocale(locale).url,
        {},
        { onSuccess: () => window.location.reload() },
    );
}

onMounted(() => {
    const current = String(i18n.global.locale.value);
    const locale = isSupportedLocale(current) ? current : 'en';

    const carry = carried as MapCarry | null;

    carried = null;
    map = mountWarehouseMap(container.value as HTMLElement, {
        rowsUrl: rowsUrl(),
        helpUrl: helpIndex.url(),
        locale,
        messages: messagesOf(locale),
        fallbackMessages: messagesOf('en'),
        switchLocale,
        transferPallet: openTransfer,
        warehouses: {
            options: props.warehouses,
            selectedIds: props.filters.warehouse_ids,
            select: visitWarehouses,
        },
        carry,
    });
});

onBeforeUnmount(() => {
    carried = map?.snapshot() ?? null;
    map?.dispose();
    map = null;

    // kept only for the map page itself (Back / Forward between warehouses)
    const stop = router.on('navigate', (event) => {
        if (event.detail.page.component !== MAP_COMPONENT) {
            carried = null;
        }

        stop();
    });
});
</script>

<template>
    <Head :title="t('nav.warehouseMap')" />

    <AdminLayout full-bleed>
        <div class="absolute inset-0 flex flex-col">
            <!-- The shell shows no banner on a full-bleed page; a transfer's
                 success message shows in this bar, which takes no room
                 without one (the warehouses are switched inside the map). -->
            <div
                class="shrink-0 border-b border-line bg-surface px-4 py-2 empty:hidden [&>p]:mb-0"
                data-testid="warehouse-map-bar"
            >
                <SuccessBanner />
            </div>
            <div
                ref="container"
                class="relative min-h-0 flex-1"
                data-testid="warehouse-map"
            ></div>
        </div>

        <PalletTransferDialog
            v-model:open="transferOpen"
            :target="transferTarget"
            :targets="transferTargets"
            :return-warehouse-ids="filters.warehouse_ids"
            @transferred="onTransferred"
        />
    </AdminLayout>
</template>

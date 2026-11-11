<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as warehouseMapIndex,
    rows as warehouseMapRows,
} from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import { update as updateLocale } from '@/actions/App/Http/Controllers/LocaleController';
import FilterWarehouseSelect from '@/components/FilterWarehouseSelect.vue';
import PalletTransferDialog from '@/components/PalletTransferDialog.vue';
import type { PalletTransferDialogTarget } from '@/components/PalletTransferDialog.vue';
import SuccessBanner from '@/components/SuccessBanner.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { selectedCountLabel } from '@/lib/filters';
import { i18n, isSupportedLocale, t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import { productName } from '@/lib/productName';
import type {
    PalletTransferTargets,
    WarehouseFilterOptions,
    WarehouseFilters,
} from '@/types/admin';
import { mountWarehouseMap } from '@/warehouse-map/main';
import type {
    PalletTransferRequest,
    WarehouseMapHandle,
} from '@/warehouse-map/main';
import '../../../../css/warehouse-map/index.css';

const props = defineProps<{
    filters: WarehouseFilters;
    filterOptions: WarehouseFilterOptions;
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
 * A different selection is a different warehouse: the page is visited again
 * without keeping its state, so the map is mounted afresh on the new rows
 * rather than patched.
 */
function onWarehouseIdsChange(ids: string[]): void {
    router.get(
        warehouseMapIndex().url,
        { warehouse_ids: ids },
        { preserveState: false },
    );
}

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

    map = mountWarehouseMap(container.value as HTMLElement, {
        rowsUrl: rowsUrl(),
        helpUrl: helpIndex.url(),
        locale,
        messages: messagesOf(locale),
        fallbackMessages: messagesOf('en'),
        switchLocale,
        transferPallet: openTransfer,
    });
});

onBeforeUnmount(() => {
    map?.dispose();
    map = null;
});
</script>

<template>
    <Head :title="t('nav.warehouseMap')" />

    <AdminLayout full-bleed>
        <div class="absolute inset-0 flex flex-col">
            <div
                class="flex shrink-0 flex-wrap items-end justify-end gap-x-4 gap-y-2 border-b border-line bg-surface px-4 py-2"
            >
                <!-- The shell shows no banner on a full-bleed page; a
                     transfer's success message shows here. -->
                <div class="min-w-0 flex-1 [&>p]:mb-0">
                    <SuccessBanner />
                </div>
                <FilterWarehouseSelect
                    id="warehouse-map-warehouse"
                    :model-value="(filters.warehouse_ids ?? []).map(String)"
                    :label="t('warehouses.filters.label')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="filterOptions.warehouses"
                    @update:model-value="onWarehouseIdsChange"
                />
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

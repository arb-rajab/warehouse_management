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
import AdminLayout from '@/layouts/AdminLayout.vue';
import { selectedCountLabel } from '@/lib/filters';
import { i18n, isSupportedLocale, t } from '@/lib/i18n';
import type { WarehouseFilterOptions, WarehouseFilters } from '@/types/admin';
import { mountWarehouseMap } from '@/warehouse-map/main';
import type { WarehouseMapHandle } from '@/warehouse-map/main';
import '../../../../css/warehouse-map/index.css';

const props = defineProps<{
    filters: WarehouseFilters;
    filterOptions: WarehouseFilterOptions;
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
                class="flex shrink-0 items-end justify-end border-b border-line bg-surface px-4 py-2"
            >
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
    </AdminLayout>
</template>

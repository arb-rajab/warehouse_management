<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { rows as warehouseMapRows } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import { update as updateLocale } from '@/actions/App/Http/Controllers/LocaleController';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { i18n, isSupportedLocale, t } from '@/lib/i18n';
import { mountWarehouseMap } from '@/warehouse-map/main';
import type { WarehouseMapHandle } from '@/warehouse-map/main';
import '../../../../css/warehouse-map/index.css';

const container = ref<HTMLElement | null>(null);
let map: WarehouseMapHandle | null = null;

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
        rowsUrl: warehouseMapRows.url(),
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
        <div
            ref="container"
            class="absolute inset-0"
            data-testid="warehouse-map"
        ></div>
    </AdminLayout>
</template>

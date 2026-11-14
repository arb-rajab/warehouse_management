<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { i18n, isSupportedLocale, t } from '@/lib/i18n';
import { mountWarehouseSwitcherPreview } from '@/warehouse-map/preview';
import '../../../../css/warehouse-map/index.css';

const sections = [
    'views',
    'legend',
    'search',
    'warehouses',
    'walking',
    'sharing',
] as const;

/** The `warehouseMap` messages of a language (the map module takes plain dictionaries). */
function messagesOf(locale: string): Record<string, string> {
    return (i18n.global.getLocaleMessage(locale) as Record<string, any>)
        .warehouseMap;
}

// a function ref: a plain `ref` inside v-for would collect an array
let switcherHost: HTMLElement | null = null;
const setSwitcherHost = (el: unknown) => {
    switcherHost = el instanceof HTMLElement ? el : null;
};
let switcherPreview: { dispose(): void } | null = null;

// the map's own switcher, held open with example warehouses
onMounted(() => {
    const current = String(i18n.global.locale.value);
    const locale = isSupportedLocale(current) ? current : 'en';

    if (!switcherHost) {
        return;
    }

    switcherPreview = mountWarehouseSwitcherPreview(switcherHost, {
        locale,
        messages: messagesOf(locale),
        fallbackMessages: messagesOf('en'),
        options: [
            {
                id: 1,
                name: t('help.warehouseMap.warehouses.exampleMain'),
            },
            {
                id: 2,
                name: t('help.warehouseMap.warehouses.exampleAnnex'),
            },
            {
                id: 3,
                name: t('help.warehouseMap.warehouses.exampleCold'),
            },
        ],
        selected: [1],
    });
});

onBeforeUnmount(() => {
    switcherPreview?.dispose();
    switcherPreview = null;
});
</script>

<template>
    <HelpTopicPage
        :title="t('help.warehouseMap.title')"
        :feature-href="warehouseMapIndex()"
        :feature-label="t('nav.warehouseMap')"
    >
        <section v-for="section in sections" :key="section">
            <h2 :class="sectionHeadingClass">
                {{ t(`help.warehouseMap.${section}.heading`) }}
            </h2>
            <p class="text-sm text-ink">
                {{ t(`help.warehouseMap.${section}.body`) }}
            </p>
            <HelpUiPreview v-if="section === 'legend'" inert class="mt-2 block">
                <CellStateLegend />
            </HelpUiPreview>
            <HelpUiPreview
                v-if="section === 'warehouses'"
                inert
                class="mt-2 block"
            >
                <span
                    :ref="setSwitcherHost"
                    class="block"
                    data-testid="warehouse-switcher-preview"
                ></span>
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>

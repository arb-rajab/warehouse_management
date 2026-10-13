<script setup lang="ts">
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const sections = ['views', 'legend', 'search', 'walking', 'sharing'] as const;
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
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t(`help.warehouseMap.${section}.body`) }}
            </p>
            <HelpUiPreview v-if="section === 'legend'" inert class="mt-2 block">
                <CellStateLegend />
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>

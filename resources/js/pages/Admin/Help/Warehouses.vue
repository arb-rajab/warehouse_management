<script setup lang="ts">
import { Trash2, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import { index as warehousesIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import FilterWarehouseSelect from '@/components/FilterWarehouseSelect.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import WarehouseFormFields from '@/components/WarehouseFormFields.vue';
import {
    filterSectionHeadingClass as sectionHeadingClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

const plainSections = ['grid', 'letters'];

/** The preview filter's selection: local only, nothing is fetched or applied. */
const previewWarehouseIds = ref<string[]>([]);
</script>

<template>
    <HelpTopicPage
        :title="t('help.warehouses.title')"
        :feature-href="warehousesIndex()"
        :feature-label="t('warehouses.index.title')"
    >
        <section v-for="section in plainSections" :key="section">
            <h2 :class="sectionHeadingClass">
                {{ t(`help.warehouses.${section}.heading`) }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t(`help.warehouses.${section}.body`) }}
            </p>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.warehouses.creating.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.warehouses.creating.body') }}
            </p>
            <HelpUiPreview class="mt-2">
                <AddResourceLink
                    href="#"
                    tabindex="-1"
                    :label="t('warehouses.index.addWarehouse')"
                />
            </HelpUiPreview>
            <HelpUiPreview inert class="mt-2 block max-w-xs">
                <WarehouseFormFields
                    id-prefix="help-warehouse-create-"
                    :errors="{}"
                />
            </HelpUiPreview>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.warehouses.filtering.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.warehouses.filtering.body') }}
            </p>
            <HelpUiPreview inert class="mt-2 block max-w-xs">
                <FilterWarehouseSelect
                    id="help-warehouse-filter"
                    v-model="previewWarehouseIds"
                    :label="t('warehouses.filters.label')"
                    :all-label="t('cellLog.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="[]"
                />
            </HelpUiPreview>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.warehouses.deleting.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.warehouses.deleting.body') }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <HelpUiPreview>
                    <TableActionLink href="#" tabindex="-1" variant="danger">
                        <Trash2 class="h-3.5 w-3.5" />
                        {{ t('warehouses.index.delete') }}
                    </TableActionLink>
                </HelpUiPreview>
                <HelpUiPreview>
                    <span
                        tabindex="-1"
                        :title="t('warehouses.index.hasRowsTitle')"
                        class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
                    >
                        <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                        {{ t('warehouses.index.hasRows') }}
                    </span>
                </HelpUiPreview>
            </div>
        </section>
    </HelpTopicPage>
</template>

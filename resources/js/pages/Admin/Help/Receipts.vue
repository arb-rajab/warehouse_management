<script setup lang="ts">
import { ref } from 'vue';
import { index as receiptsIndex } from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import FilterSelect from '@/components/FilterSelect.vue';
import FilterWarehouseSelect from '@/components/FilterWarehouseSelect.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import ReceiptStatusBadge from '@/components/ReceiptStatusBadge.vue';
import {
    filterSectionHeadingClass as sectionHeadingClass,
    selectedCountLabel,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

const plainSections = ['what', 'list'];
const trailingSections = ['files', 'pallets'];

/** The preview filters' selection: local only, nothing is fetched or applied. */
const previewWarehouseIds = ref<string[]>([]);
const previewStatus = ref('');
</script>

<template>
    <HelpTopicPage
        :title="t('help.receipts.title')"
        :feature-href="receiptsIndex()"
        :feature-label="t('receipts.index.title')"
    >
        <section v-for="section in plainSections" :key="section">
            <h2 :class="sectionHeadingClass">
                {{ t(`help.receipts.${section}.heading`) }}
            </h2>
            <p class="text-sm text-ink">
                {{ t(`help.receipts.${section}.body`) }}
            </p>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.receipts.status.heading') }}
            </h2>
            <p class="text-sm text-ink">
                {{ t('help.receipts.status.body') }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <HelpUiPreview>
                    <ReceiptStatusBadge status="draft" />
                </HelpUiPreview>
                <HelpUiPreview>
                    <ReceiptStatusBadge status="finalized" />
                </HelpUiPreview>
            </div>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.receipts.filtering.heading') }}
            </h2>
            <p class="text-sm text-ink">
                {{ t('help.receipts.filtering.body') }}
            </p>
            <HelpUiPreview inert class="mt-2 block max-w-xs">
                <FilterWarehouseSelect
                    id="help-receipts-warehouse-filter"
                    v-model="previewWarehouseIds"
                    :label="t('warehouses.filters.label')"
                    :all-label="t('receipts.filters.all')"
                    :selected-count-label="selectedCountLabel"
                    :selected="[]"
                />
            </HelpUiPreview>
            <HelpUiPreview inert class="mt-2 block max-w-xs">
                <FilterSelect
                    id="help-receipts-status-filter"
                    v-model="previewStatus"
                    :label="t('receipts.filters.status')"
                    :all-label="t('receipts.filters.all')"
                    :options="[
                        { value: 'draft', label: t('receipts.status.draft') },
                        {
                            value: 'finalized',
                            label: t('receipts.status.finalized'),
                        },
                    ]"
                />
            </HelpUiPreview>
        </section>

        <section v-for="section in trailingSections" :key="section">
            <h2 :class="sectionHeadingClass">
                {{ t(`help.receipts.${section}.heading`) }}
            </h2>
            <p class="text-sm text-ink">
                {{ t(`help.receipts.${section}.body`) }}
            </p>
        </section>
    </HelpTopicPage>
</template>

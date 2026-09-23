<script setup lang="ts">
import { QrCode } from '@lucide/vue';
import { ref } from 'vue';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import DataTable from '@/components/DataTable.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import {
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

const sections = ['defaultBoxes', 'settingBoxes', 'exportingQr'];
const previewBoxCountDraft = ref('50');

/**
 * Matches ExpiringSoonDefaults::CUSTOM_WINDOW_DAYS, the app's real default
 * "expiring soon" window, so the previewed column label reads the same as
 * the live Products page.
 */
const previewExpiringSoonDays = 45;

/** Representative row for the products-table preview below — plain fixture data, never fetched. */
const previewProducts = [
    {
        id: 1,
        name: 'Example product',
        boxes_count: 50,
        full_cells_count: 12,
        opened_cells_count: 3,
        expired_cells_count: 1,
        expiring_soon_count: 2,
    },
];
</script>

<template>
    <HelpTopicPage
        :title="t('help.products.title')"
        :feature-href="productsIndex()"
        :feature-label="t('products.title')"
    >
        <section v-for="section in sections" :key="section">
            <h2 :class="sectionHeadingClass">
                {{ t(`help.products.${section}.heading`) }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t(`help.products.${section}.body`) }}
            </p>
            <HelpUiPreview
                v-if="section === 'settingBoxes'"
                inert
                class="mt-2 block"
            >
                <DataTable
                    :columns="[
                        { label: t('products.columns.product') },
                        { label: t('products.columns.boxesPerPallet') },
                        {
                            label: t('products.columns.full'),
                            sortKey: 'full_cells_count',
                        },
                        {
                            label: t('products.columns.opened'),
                            sortKey: 'opened_cells_count',
                        },
                        {
                            label: t('products.columns.expired'),
                            sortKey: 'expired_cells_count',
                        },
                        {
                            label: t('products.columns.expiringSoon', {
                                days: previewExpiringSoonDays,
                            }),
                            sortKey: 'expiring_soon_count',
                        },
                    ]"
                    :rows="previewProducts"
                    :empty-message="t('products.empty')"
                >
                    <template #row="{ row }">
                        <td
                            class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                        >
                            {{ row.name }}
                        </td>
                        <td class="px-4 py-2">{{ row.boxes_count }}</td>
                        <td class="px-4 py-2">
                            {{ row.full_cells_count }}
                        </td>
                        <td class="px-4 py-2">
                            {{ row.opened_cells_count }}
                        </td>
                        <td class="px-4 py-2">
                            {{ row.expired_cells_count }}
                        </td>
                        <td class="px-4 py-2">
                            {{ row.expiring_soon_count }}
                        </td>
                    </template>
                </DataTable>
            </HelpUiPreview>
            <HelpUiPreview v-if="section === 'settingBoxes'" class="mt-2">
                <button
                    type="button"
                    tabindex="-1"
                    :class="filterTriggerButtonClass"
                >
                    50
                </button>
            </HelpUiPreview>
            <HelpUiPreview
                v-if="section === 'settingBoxes'"
                inert
                class="mt-2 block max-w-xs"
            >
                <p class="mb-3 text-lg font-semibold">
                    {{ t('products.columns.boxesPerPallet') }}
                </p>
                <FilterNumberField
                    id="help-products-box-count"
                    v-model="previewBoxCountDraft"
                    :label="
                        t('products.boxesPerPalletLabel', {
                            product: t('products.columns.product'),
                        })
                    "
                />
            </HelpUiPreview>
            <HelpUiPreview v-if="section === 'exportingQr'" class="mt-2">
                <span
                    tabindex="-1"
                    :aria-label="
                        t('products.exportQrLabel', {
                            product: t('products.columns.product'),
                        })
                    "
                    :title="t('products.columns.qr')"
                    class="inline-flex text-gray-400 dark:text-neutral-500"
                >
                    <QrCode class="h-4 w-4" />
                </span>
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>

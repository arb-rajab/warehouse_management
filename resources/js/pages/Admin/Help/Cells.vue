<script setup lang="ts">
import { Ban, Box, LayoutGrid, PackageSearch, Search } from '@lucide/vue';
import { ref } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import {
    fieldLabelClass,
    filterSectionHeadingClass as sectionHeadingClass,
    mapToolbarButtonClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type { ProductFilterOption } from '@/types/admin';

const palletActionTabKeys = [
    'store',
    'open',
    'removeBoxes',
    'empty',
    'transfer',
    'edit',
] as const;

const previewProduct = ref<ProductFilterOption | null>(null);
</script>

<template>
    <HelpTopicPage
        :title="t('help.cells.title')"
        :feature-href="cellsIndex()"
        :feature-label="t('cells.title')"
    >
        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.map.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.cells.map.body') }}
            </p>
            <HelpUiPreview class="mt-2">
                <span
                    tabindex="-1"
                    class="w-40 truncate rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-400 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-500"
                >
                    {{ t('cells.search.placeholder') }}
                </span>
                <span :class="mapToolbarButtonClass" tabindex="-1">
                    <Search class="h-4 w-4" />
                </span>
            </HelpUiPreview>
            <HelpUiPreview class="mt-2">
                <span
                    tabindex="-1"
                    :title="t('cells.map.view2d')"
                    :class="[mapToolbarButtonClass, selectedToggleClass]"
                >
                    <LayoutGrid class="h-4 w-4" />
                </span>
                <span
                    tabindex="-1"
                    :title="t('cells.map.view3d')"
                    :class="mapToolbarButtonClass"
                >
                    <Box class="h-4 w-4" />
                </span>
            </HelpUiPreview>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.legend.heading') }}
            </h2>
            <p class="mb-3 text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.cells.legend.body') }}
            </p>
            <CellStateLegend />
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.managingPallets.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.cells.managingPallets.body') }}
            </p>
            <HelpUiPreview class="mt-2">
                <span
                    tabindex="-1"
                    :title="t('cells.palletActions.triggerLabel')"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-neutral-400"
                >
                    <PackageSearch class="h-4 w-4" />
                    {{ t('cells.palletActions.triggerLabel') }}
                </span>
            </HelpUiPreview>
            <HelpUiPreview class="mt-2">
                <span
                    v-for="(action, index) in palletActionTabKeys"
                    :key="action"
                    tabindex="-1"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        index === 0
                            ? selectedToggleClass
                            : 'border border-gray-300 text-gray-700 dark:border-neutral-700 dark:text-neutral-200'
                    "
                >
                    {{ t(`cells.palletActions.tabs.${action}`) }}
                </span>
            </HelpUiPreview>

            <div class="mt-4 space-y-4">
                <div v-for="action in palletActionTabKeys" :key="action">
                    <p
                        class="mb-1 text-sm font-medium text-gray-700 dark:text-neutral-300"
                    >
                        {{ t(`cells.palletActions.tabs.${action}`) }}
                    </p>
                    <HelpUiPreview inert class="block max-w-sm">
                        <div class="space-y-4">
                            <template v-if="action === 'store'">
                                <ProductSelect
                                    id="help-pallet-store-product"
                                    v-model="previewProduct"
                                    :label="
                                        t(
                                            'cells.palletActions.store.productLabel',
                                        )
                                    "
                                    :placeholder="
                                        t(
                                            'cells.palletActions.store.productPlaceholder',
                                        )
                                    "
                                />
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            'cells.palletActions.store.expirationLabel',
                                        )
                                    }}</label>
                                    <input
                                        type="date"
                                        :class="plainFieldInputClass"
                                    />
                                </div>
                            </template>

                            <template
                                v-else-if="
                                    action === 'open' ||
                                    action === 'removeBoxes'
                                "
                            >
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            `cells.palletActions.${action}.boxesCountLabel`,
                                        )
                                    }}</label>
                                    <input
                                        type="number"
                                        min="1"
                                        :class="plainFieldInputClass"
                                    />
                                </div>
                                <label
                                    class="flex items-start gap-2 text-sm text-gray-700 dark:text-neutral-300"
                                >
                                    <input type="checkbox" class="mt-0.5" />
                                    {{
                                        t(
                                            `cells.palletActions.${action}.confirmEmptyLabel`,
                                        )
                                    }}
                                </label>
                            </template>

                            <template v-else-if="action === 'empty'">
                                <p
                                    class="text-sm text-gray-600 dark:text-neutral-300"
                                >
                                    {{
                                        t(
                                            'cells.palletActions.empty.confirmText',
                                            {
                                                location: formatSlot('A', 3, 2),
                                            },
                                        )
                                    }}
                                </p>
                            </template>

                            <template v-else-if="action === 'transfer'">
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            'cells.palletActions.transfer.rowLabel',
                                        )
                                    }}</label>
                                    <select :class="plainFieldInputClass">
                                        <option value="" disabled>—</option>
                                    </select>
                                </div>
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            'cells.palletActions.transfer.cellNumberLabel',
                                        )
                                    }}</label>
                                    <input
                                        type="number"
                                        min="1"
                                        :class="plainFieldInputClass"
                                    />
                                </div>
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            'cells.palletActions.transfer.flatNumberLabel',
                                        )
                                    }}</label>
                                    <input
                                        type="number"
                                        min="1"
                                        :class="plainFieldInputClass"
                                    />
                                </div>
                            </template>

                            <template v-else-if="action === 'edit'">
                                <ProductSelect
                                    id="help-pallet-edit-product"
                                    v-model="previewProduct"
                                    :label="
                                        t(
                                            'cells.palletActions.edit.productLabel',
                                        )
                                    "
                                    :placeholder="
                                        t(
                                            'cells.palletActions.edit.productPlaceholder',
                                        )
                                    "
                                />
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            'cells.palletActions.edit.expirationLabel',
                                        )
                                    }}</label>
                                    <input
                                        type="date"
                                        :class="plainFieldInputClass"
                                    />
                                </div>
                                <div>
                                    <label :class="fieldLabelClass">{{
                                        t(
                                            'cells.palletActions.edit.boxesCountLabel',
                                        )
                                    }}</label>
                                    <input
                                        type="number"
                                        min="0"
                                        :class="plainFieldInputClass"
                                    />
                                </div>
                                <label
                                    class="flex items-start gap-2 text-sm text-gray-700 dark:text-neutral-300"
                                >
                                    <input type="checkbox" class="mt-0.5" />
                                    {{
                                        t(
                                            'cells.palletActions.edit.confirmEmptyLabel',
                                        )
                                    }}
                                </label>
                            </template>

                            <div v-if="action !== 'empty' && action !== 'edit'">
                                <label :class="fieldLabelClass">{{
                                    t('cells.palletActions.noteLabel')
                                }}</label>
                                <textarea
                                    rows="3"
                                    :placeholder="
                                        t('cells.palletActions.notePlaceholder')
                                    "
                                    :class="plainFieldInputClass"
                                ></textarea>
                            </div>
                        </div>
                    </HelpUiPreview>
                </div>
            </div>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.deactivating.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.cells.deactivating.body') }}
            </p>
            <HelpUiPreview class="mt-2">
                <span
                    tabindex="-1"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-neutral-400"
                >
                    <Ban class="h-4 w-4" />
                    {{ t('cells.toggleActive.deactivateLabel') }}
                </span>
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>

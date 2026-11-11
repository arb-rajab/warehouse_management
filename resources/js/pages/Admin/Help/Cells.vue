<script setup lang="ts">
import { ArrowLeftRight, Ban } from '@lucide/vue';
import { ref } from 'vue';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import CellSlot from '@/components/CellSlot.vue';
import CellStateLegend from '@/components/CellStateLegend.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PalletTransferFields from '@/components/PalletTransferFields.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import {
    fieldLabelClass,
    filterSectionHeadingClass as sectionHeadingClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    Cell,
    PalletTransferTargets,
    ProductFilterOption,
} from '@/types/admin';

const previewManageableCell: Cell = {
    id: 0,
    cell_number: 1,
    flat_number: 1,
    state: 'full',
    is_active: true,
    pallet: {
        id: 0,
        product_id: 0,
        product_name: 'Example product',
        product_ar_name: '',
        product_image_url: null,
        expiration_date: null,
        added_at: null,
        is_stale: null,
        remaining_boxes: 5,
    },
};

const palletActionTabKeys = [
    'store',
    'open',
    'removeBoxes',
    'empty',
    'transfer',
    'edit',
] as const;

const previewProduct = ref<ProductFilterOption | null>(null);

// Two sample warehouses, so the preview shows the destination warehouse select.
const previewTransferTargets: PalletTransferTargets = {
    warehouses: [
        { id: 1, name: t('help.cells.preview.mainWarehouse') },
        { id: 2, name: t('help.cells.preview.otherWarehouse') },
    ],
    rows: [
        { id: 1, letter: 'A', cells_count: 1, flats_count: 1, warehouse_id: 1 },
        { id: 2, letter: 'A', cells_count: 1, flats_count: 1, warehouse_id: 2 },
    ],
};
const previewTransferWarehouseId = ref<number | null>(1);
const previewTransferRow = ref('');
const previewTransferCell = ref('');
const previewTransferFlat = ref('');
</script>

<template>
    <HelpTopicPage
        :title="t('help.cells.title')"
        :feature-href="rowsIndex()"
        :feature-label="t('nav.rows')"
    >
        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.legend.heading') }}
            </h2>
            <p class="mb-3 text-sm text-ink">
                {{ t('help.cells.legend.body') }}
            </p>
            <CellStateLegend />
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.managingPallets.heading') }}
            </h2>
            <p class="text-sm text-ink">
                {{ t('help.cells.managingPallets.body') }}
            </p>
            <HelpUiPreview inert class="mt-2 flex justify-center">
                <CellSlot
                    :cell="previewManageableCell"
                    :label="formatSlot('A', 1, 1)"
                    :highlighted="false"
                    manageable
                />
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
                            : 'border border-line-strong text-ink'
                    "
                >
                    {{ t(`cells.palletActions.tabs.${action}`) }}
                </span>
            </HelpUiPreview>

            <div class="mt-4 space-y-4">
                <div v-for="action in palletActionTabKeys" :key="action">
                    <p class="mb-1 text-sm font-medium text-ink">
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
                                    class="flex items-start gap-2 text-sm text-ink"
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
                                <p class="text-sm text-muted">
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
                                <PalletTransferFields
                                    v-model:warehouse-id="
                                        previewTransferWarehouseId
                                    "
                                    v-model:row-letter="previewTransferRow"
                                    v-model:cell-number="previewTransferCell"
                                    v-model:flat-number="previewTransferFlat"
                                    id-prefix="help-pallet-transfer-"
                                    :warehouses="
                                        previewTransferTargets.warehouses
                                    "
                                    :rows="previewTransferTargets.rows"
                                />
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
                                    class="flex items-start gap-2 text-sm text-ink"
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

            <p class="mt-4 text-sm text-ink">
                {{ t('help.cells.managingPallets.transferBody') }}
            </p>
            <HelpUiPreview class="mt-2">
                <span
                    tabindex="-1"
                    data-testid="help-map-transfer-button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-line-strong bg-subtle px-3 py-1.5 text-xs font-bold text-ink"
                >
                    <ArrowLeftRight class="h-3.5 w-3.5 shrink-0" />
                    {{ t('warehouseMap.transferPallet') }}
                </span>
            </HelpUiPreview>
        </section>

        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.cells.deactivating.heading') }}
            </h2>
            <p class="text-sm text-ink">
                {{ t('help.cells.deactivating.body') }}
            </p>
            <HelpUiPreview class="mt-2">
                <span
                    tabindex="-1"
                    class="inline-flex items-center gap-1.5 text-sm text-muted"
                >
                    <Ban class="h-4 w-4" />
                    {{ t('cells.toggleActive.deactivateLabel') }}
                </span>
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>

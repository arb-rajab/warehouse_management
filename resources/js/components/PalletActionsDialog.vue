<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    empty as emptyPallet,
    open as openPallet,
    removeBoxes,
    store,
    transfer,
    update as updatePallet,
} from '@/actions/App/Http/Controllers/Admin/PalletController';
import FilterDialog from '@/components/FilterDialog.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { Cell, CellMapRow, ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    cell: Cell | null;
    label: string;
    rows: CellMapRow[];
    returnTo?: 'row';
}>();

const open = defineModel<boolean>('open', { required: true });

type PalletActionTab =
    'store' | 'open' | 'remove-boxes' | 'empty' | 'transfer' | 'edit';

const availableActions = computed<PalletActionTab[]>(() => {
    if (!props.cell) {
        return [];
    }

    if (props.cell.state === 'empty') {
        return ['store'];
    }

    if (props.cell.state === 'full') {
        return ['open', 'empty', 'transfer', 'edit'];
    }

    return ['remove-boxes', 'empty', 'transfer', 'edit'];
});

const selectedAction = ref<PalletActionTab>('store');

const product = ref<ProductFilterOption | null>(null);
const expirationDate = ref('');
const boxesCount = ref('');
const confirmEmpty = ref(false);
const destinationRowLetter = ref('');
const destinationCellNumber = ref('');
const destinationFlatNumber = ref('');
const note = ref('');
const processing = ref(false);

function resetFields(): void {
    product.value = null;
    expirationDate.value = '';
    boxesCount.value = '';
    confirmEmpty.value = false;
    destinationRowLetter.value = '';
    destinationCellNumber.value = '';
    destinationFlatNumber.value = '';
    note.value = '';
}

watch(open, (isOpen) => {
    if (isOpen) {
        selectedAction.value = availableActions.value[0] ?? 'store';
        resetFields();
    }
});

watch(selectedAction, (action) => {
    resetFields();

    if (action === 'edit' && props.cell?.pallet) {
        product.value = {
            id: props.cell.pallet.product_id,
            name: props.cell.pallet.product_name,
            ar_name: props.cell.pallet.product_ar_name,
        };
        expirationDate.value = props.cell.pallet.expiration_date ?? '';
        boxesCount.value = String(props.cell.pallet.remaining_boxes);
    }
});

const remainingBoxes = computed(() => props.cell?.pallet?.remaining_boxes ?? 0);

const boxesWouldEmptyPallet = computed(() => {
    const count = Number(boxesCount.value);

    return count > 0 && count >= remainingBoxes.value;
});

function noteOrNull(): string | null {
    return note.value === '' ? null : note.value;
}

function submit(): void {
    if (!props.cell) {
        return;
    }

    const pallet = props.cell.pallet;
    processing.value = true;

    const options = {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            open.value = false;
        },
    };

    if (selectedAction.value === 'store') {
        router.post(
            store({ cell: props.cell.id }, { mergeQuery: {} }).url,
            {
                product_id: product.value?.id ?? null,
                expiration_date: expirationDate.value,
                note: noteOrNull(),
                return_to: props.returnTo ?? null,
            },
            options,
        );

        return;
    }

    if (!pallet) {
        processing.value = false;

        return;
    }

    if (selectedAction.value === 'open') {
        router.post(
            openPallet({ pallet: pallet.id }, { mergeQuery: {} }).url,
            {
                boxes_count: boxesCount.value,
                confirm_empty: confirmEmpty.value,
                note: noteOrNull(),
                return_to: props.returnTo ?? null,
            },
            options,
        );
    } else if (selectedAction.value === 'remove-boxes') {
        router.post(
            removeBoxes({ pallet: pallet.id }, { mergeQuery: {} }).url,
            {
                boxes_count: boxesCount.value,
                confirm_empty: confirmEmpty.value,
                note: noteOrNull(),
                return_to: props.returnTo ?? null,
            },
            options,
        );
    } else if (selectedAction.value === 'empty') {
        router.post(
            emptyPallet({ pallet: pallet.id }, { mergeQuery: {} }).url,
            { note: noteOrNull(), return_to: props.returnTo ?? null },
            options,
        );
    } else if (selectedAction.value === 'transfer') {
        router.post(
            transfer({ pallet: pallet.id }, { mergeQuery: {} }).url,
            {
                row_letter: destinationRowLetter.value,
                cell_number: destinationCellNumber.value,
                flat_number: destinationFlatNumber.value,
                note: noteOrNull(),
                return_to: props.returnTo ?? null,
            },
            options,
        );
    } else if (selectedAction.value === 'edit') {
        router.put(
            updatePallet({ pallet: pallet.id }, { mergeQuery: {} }).url,
            {
                product_id: product.value?.id ?? null,
                expiration_date: expirationDate.value,
                remaining_boxes: boxesCount.value,
                return_to: props.returnTo ?? null,
            },
            options,
        );
    }
}

const submitLabel = computed(() => {
    switch (selectedAction.value) {
        case 'store':
            return t('cells.palletActions.store.submit');
        case 'open':
            return t('cells.palletActions.open.submit');
        case 'remove-boxes':
            return t('cells.palletActions.removeBoxes.submit');
        case 'empty':
            return t('cells.palletActions.empty.submit');
        case 'transfer':
            return t('cells.palletActions.transfer.submit');
        case 'edit':
            return t('cells.palletActions.edit.submit');
        default:
            return '';
    }
});
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t('cells.palletActions.title', { location: label })"
        :close-label="t('cellLog.filters.close')"
    >
        <div
            v-if="availableActions.length > 1"
            class="mb-4 flex flex-wrap gap-2"
        >
            <button
                v-for="action in availableActions"
                :key="action"
                type="button"
                data-testid="pallet-action-tab"
                :aria-pressed="selectedAction === action"
                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                :class="
                    selectedAction === action
                        ? selectedToggleClass
                        : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800'
                "
                @click="selectedAction = action"
            >
                {{
                    t(
                        `cells.palletActions.tabs.${action === 'remove-boxes' ? 'removeBoxes' : action}`,
                    )
                }}
            </button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <template
                v-if="selectedAction === 'store' || selectedAction === 'edit'"
            >
                <ProductSelect
                    :id="
                        selectedAction === 'edit'
                            ? 'pallet-action-edit-product'
                            : 'pallet-action-product'
                    "
                    v-model="product"
                    required
                    :label="
                        t(`cells.palletActions.${selectedAction}.productLabel`)
                    "
                    :placeholder="
                        t(
                            `cells.palletActions.${selectedAction}.productPlaceholder`,
                        )
                    "
                />
                <div>
                    <label
                        :for="
                            selectedAction === 'edit'
                                ? 'pallet-action-edit-expiration'
                                : 'pallet-action-expiration'
                        "
                        :class="fieldLabelClass"
                    >
                        {{
                            t(
                                `cells.palletActions.${selectedAction}.expirationLabel`,
                            )
                        }}
                    </label>
                    <input
                        :id="
                            selectedAction === 'edit'
                                ? 'pallet-action-edit-expiration'
                                : 'pallet-action-expiration'
                        "
                        v-model="expirationDate"
                        type="date"
                        :class="plainFieldInputClass"
                    />
                </div>
                <div v-if="selectedAction === 'edit'">
                    <label
                        for="pallet-action-edit-boxes"
                        :class="fieldLabelClass"
                    >
                        {{ t('cells.palletActions.edit.boxesCountLabel') }}
                    </label>
                    <input
                        id="pallet-action-edit-boxes"
                        v-model="boxesCount"
                        type="number"
                        min="0"
                        required
                        :class="plainFieldInputClass"
                    />
                </div>
            </template>

            <template
                v-else-if="
                    selectedAction === 'open' ||
                    selectedAction === 'remove-boxes'
                "
            >
                <p class="text-sm text-gray-600 dark:text-neutral-300">
                    {{
                        t(
                            `cells.palletActions.${selectedAction === 'open' ? 'open' : 'removeBoxes'}.remainingBoxesHint`,
                            { count: remainingBoxes },
                        )
                    }}
                </p>
                <div>
                    <label
                        for="pallet-action-boxes-count"
                        :class="fieldLabelClass"
                    >
                        {{
                            t(
                                `cells.palletActions.${selectedAction === 'open' ? 'open' : 'removeBoxes'}.boxesCountLabel`,
                            )
                        }}
                    </label>
                    <input
                        id="pallet-action-boxes-count"
                        v-model="boxesCount"
                        type="number"
                        min="1"
                        required
                        :class="plainFieldInputClass"
                    />
                </div>
                <label
                    v-if="boxesWouldEmptyPallet"
                    class="flex items-start gap-2 text-sm text-gray-700 dark:text-neutral-300"
                >
                    <input
                        v-model="confirmEmpty"
                        type="checkbox"
                        class="mt-0.5"
                    />
                    {{
                        t(
                            `cells.palletActions.${selectedAction === 'open' ? 'open' : 'removeBoxes'}.confirmEmptyLabel`,
                        )
                    }}
                </label>
            </template>

            <template v-else-if="selectedAction === 'empty'">
                <p class="text-sm text-gray-600 dark:text-neutral-300">
                    {{
                        t('cells.palletActions.empty.confirmText', {
                            location: label,
                        })
                    }}
                </p>
            </template>

            <template v-else-if="selectedAction === 'transfer'">
                <div>
                    <label for="pallet-action-to-row" :class="fieldLabelClass">
                        {{ t('cells.palletActions.transfer.rowLabel') }}
                    </label>
                    <select
                        id="pallet-action-to-row"
                        v-model="destinationRowLetter"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option value="" disabled>—</option>
                        <option
                            v-for="row in rows"
                            :key="row.id"
                            :value="row.letter"
                        >
                            {{ row.letter }}
                        </option>
                    </select>
                </div>
                <div>
                    <label for="pallet-action-to-cell" :class="fieldLabelClass">
                        {{ t('cells.palletActions.transfer.cellNumberLabel') }}
                    </label>
                    <input
                        id="pallet-action-to-cell"
                        v-model="destinationCellNumber"
                        type="number"
                        min="1"
                        required
                        :class="plainFieldInputClass"
                    />
                </div>
                <div>
                    <label for="pallet-action-to-flat" :class="fieldLabelClass">
                        {{ t('cells.palletActions.transfer.flatNumberLabel') }}
                    </label>
                    <input
                        id="pallet-action-to-flat"
                        v-model="destinationFlatNumber"
                        type="number"
                        min="1"
                        required
                        :class="plainFieldInputClass"
                    />
                </div>
            </template>

            <div v-if="selectedAction !== 'edit'">
                <label for="pallet-action-note" :class="fieldLabelClass">
                    {{ t('cells.palletActions.noteLabel') }}
                </label>
                <textarea
                    id="pallet-action-note"
                    v-model="note"
                    rows="3"
                    maxlength="1000"
                    :placeholder="t('cells.palletActions.notePlaceholder')"
                    :class="plainFieldInputClass"
                ></textarea>
            </div>

            <SubmitButton
                :label="submitLabel"
                :processing-label="t('cells.palletActions.submitting')"
                :processing="processing"
            />
        </form>
    </FilterDialog>
</template>

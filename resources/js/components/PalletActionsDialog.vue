<script setup lang="ts">
import type { FormDataConvertible, UrlMethodPair } from '@inertiajs/core';
import { useForm } from '@inertiajs/vue3';
import { CalendarPlus, CalendarX } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    empty as emptyPallet,
    open as openPallet,
    removeBoxes,
    store,
    transfer,
    update as updatePallet,
} from '@/actions/App/Http/Controllers/Admin/PalletController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import {
    fieldLabelClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { productName } from '@/lib/productName';
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

const form = useForm({
    product: null as ProductFilterOption | null,
    expiration_date: '',
    boxes_count: '',
    confirm_empty: false,
    row_letter: '',
    cell_number: '',
    flat_number: '',
    note: '',
});

function resetFields(): void {
    form.reset();
    form.clearErrors();
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
        form.product = {
            id: props.cell.pallet.product_id,
            name: props.cell.pallet.product_name,
            ar_name: props.cell.pallet.product_ar_name,
        };
        form.expiration_date = props.cell.pallet.expiration_date ?? '';
        form.boxes_count = String(props.cell.pallet.remaining_boxes);
    }
});

const remainingBoxes = computed(() => props.cell?.pallet?.remaining_boxes ?? 0);

const boxesWouldEmptyPallet = computed(() => {
    const count = Number(form.boxes_count);

    return count > 0 && count >= remainingBoxes.value;
});

const editWouldEmptyPallet = computed(() => Number(form.boxes_count) === 0);

function noteOrNull(): string | null {
    return form.note === '' ? null : form.note;
}

function boxesCountOrNull(): string | null {
    return form.boxes_count === '' ? null : form.boxes_count;
}

interface PalletActionRequest {
    action: UrlMethodPair;
    data: Record<string, FormDataConvertible>;
}

function buildRequest(): PalletActionRequest | null {
    const { cell } = props;

    if (!cell) {
        return null;
    }

    const returnTo = props.returnTo ?? null;
    const query = { mergeQuery: {} };

    if (selectedAction.value === 'store') {
        return {
            action: store(cell, query),
            data: {
                product_id: form.product?.id ?? null,
                expiration_date: form.expiration_date,
                note: noteOrNull(),
                return_to: returnTo,
            },
        };
    }

    const { pallet } = cell;

    if (!pallet) {
        return null;
    }

    switch (selectedAction.value) {
        case 'open':
            return {
                action: openPallet(pallet, query),
                data: {
                    boxes_count: boxesCountOrNull(),
                    confirm_empty: form.confirm_empty,
                    note: noteOrNull(),
                    return_to: returnTo,
                },
            };
        case 'remove-boxes':
            return {
                action: removeBoxes(pallet, query),
                data: {
                    boxes_count: form.boxes_count,
                    confirm_empty: form.confirm_empty,
                    note: noteOrNull(),
                    return_to: returnTo,
                },
            };
        case 'empty':
            return {
                action: emptyPallet(pallet, query),
                data: { note: noteOrNull(), return_to: returnTo },
            };
        case 'transfer':
            return {
                action: transfer(pallet, query),
                data: {
                    row_letter: form.row_letter,
                    cell_number: form.cell_number,
                    flat_number: form.flat_number,
                    note: noteOrNull(),
                    return_to: returnTo,
                },
            };
        case 'edit':
            return {
                action: updatePallet(pallet, query),
                data: {
                    product_id: form.product?.id ?? null,
                    expiration_date: form.expiration_date,
                    remaining_boxes: form.boxes_count,
                    confirm_empty: form.confirm_empty,
                    return_to: returnTo,
                },
            };
        default:
            return null;
    }
}

function submit(): void {
    const request = buildRequest();

    if (!request) {
        return;
    }

    form.transform(() => request.data).submit(request.action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
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
            v-if="cell?.pallet"
            data-testid="pallet-action-details"
            class="mb-4 flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-neutral-800 dark:bg-neutral-950"
        >
            <img
                v-if="cell.pallet.product_image_url"
                :src="cell.pallet.product_image_url"
                :alt="
                    productName(
                        cell.pallet.product_name,
                        cell.pallet.product_ar_name,
                    )
                "
                class="h-12 w-12 shrink-0 rounded object-cover"
            />
            <div class="min-w-0 space-y-0.5">
                <p
                    class="truncate font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{
                        productName(
                            cell.pallet.product_name,
                            cell.pallet.product_ar_name,
                        )
                    }}
                </p>
                <p class="text-sm text-gray-600 dark:text-neutral-300">
                    {{
                        t('cells.palletActions.details.remainingBoxes', {
                            count: cell.pallet.remaining_boxes,
                        })
                    }}
                </p>
                <div
                    v-if="cell.pallet.expiration_date"
                    class="flex items-center gap-1 text-xs text-gray-500 dark:text-neutral-400"
                >
                    <CalendarX class="h-3 w-3 shrink-0" />
                    {{ formatDate(cell.pallet.expiration_date) }}
                </div>
                <div
                    v-if="cell.pallet.added_at"
                    class="flex items-center gap-1 text-xs text-gray-400 dark:text-neutral-500"
                >
                    <CalendarPlus class="h-3 w-3 shrink-0" />
                    {{ formatDateTime(cell.pallet.added_at) }}
                </div>
            </div>
        </div>

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
                class="inline-flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
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

        <form
            id="pallet-action-form"
            class="space-y-4"
            @submit.prevent="submit"
        >
            <ActionErrorBanner :message="firstError(form.errors)" />

            <template
                v-if="selectedAction === 'store' || selectedAction === 'edit'"
            >
                <ProductSelect
                    :id="
                        selectedAction === 'edit'
                            ? 'pallet-action-edit-product'
                            : 'pallet-action-product'
                    "
                    v-model="form.product"
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
                        v-model="form.expiration_date"
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
                        v-model="form.boxes_count"
                        type="number"
                        min="0"
                        required
                        :class="plainFieldInputClass"
                    />
                </div>
                <label
                    v-if="selectedAction === 'edit' && editWouldEmptyPallet"
                    class="flex items-start gap-2 text-sm text-gray-700 dark:text-neutral-300"
                >
                    <input
                        v-model="form.confirm_empty"
                        type="checkbox"
                        class="mt-0.5"
                    />
                    {{ t('cells.palletActions.edit.confirmEmptyLabel') }}
                </label>
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
                        v-model="form.boxes_count"
                        type="number"
                        min="1"
                        :required="selectedAction === 'remove-boxes'"
                        :class="plainFieldInputClass"
                    />
                </div>
                <label
                    v-if="boxesWouldEmptyPallet"
                    class="flex items-start gap-2 text-sm text-gray-700 dark:text-neutral-300"
                >
                    <input
                        v-model="form.confirm_empty"
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
                        v-model="form.row_letter"
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
                        v-model="form.cell_number"
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
                        v-model="form.flat_number"
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
                    v-model="form.note"
                    rows="3"
                    maxlength="1000"
                    :placeholder="t('cells.palletActions.notePlaceholder')"
                    :class="plainFieldInputClass"
                ></textarea>
            </div>
        </form>

        <template #footer>
            <SubmitButton
                form="pallet-action-form"
                :label="submitLabel"
                :processing-label="t('cells.palletActions.submitting')"
                :processing="form.processing"
            />
        </template>
    </FilterDialog>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CircleAlert, LoaderCircle } from '@lucide/vue';
import { computed, useTemplateRef, watch } from 'vue';
import { transfer } from '@/actions/App/Http/Controllers/Admin/PalletController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FieldError from '@/components/FieldError.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import PalletTransferFields from '@/components/PalletTransferFields.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import {
    nonFieldErrors,
    palletTransferValidation,
} from '@/lib/palletTransferForm';
import { useFormValidation } from '@/lib/useFormValidation';
import type { PalletTransferTargets } from '@/types/admin';

/** The pallet the dialog moves, as the warehouse map hands it over. */
export interface PalletTransferDialogTarget {
    palletId: number;
    /** The pallet's slot, formatted (`A3·2`). */
    location: string;
    productName: string;
    /** The pallet's warehouse, the default destination warehouse. */
    warehouseId: number | null;
}

/**
 * The warehouse map's pallet transfer: the same destination fields as the
 * row page's pallet dialog (`PalletTransferFields`, validated in the browser
 * from `lib/palletTransferRules.json`), posted to the admin transfer route
 * with `return_to=map` and the map's warehouse filter, so the redirect lands
 * back on the map. `targets` is null while the page is still loading them.
 */
const props = defineProps<{
    target: PalletTransferDialogTarget | null;
    targets: PalletTransferTargets | null;
    /** The map's `warehouse_ids[]` filter, kept on the way back. */
    returnWarehouseIds: number[] | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    transferred: [];
}>();

const form = useForm({
    warehouse_id: null as number | null,
    row_letter: '',
    cell_number: '',
    flat_number: '',
    note: '',
});

const formElement = useTemplateRef<HTMLFormElement>('formElement');

const validator = useFormValidation(
    () => palletTransferValidation,
    () => formElement.value,
);

const fieldErrors = computed(() => validator.errorsFor(form.errors));
const bannerError = computed(() => firstError(nonFieldErrors(form.errors)));

/** The pallet's own warehouse, or the first one when the map did not name it. */
function defaultWarehouseId(): number | null {
    return (
        props.target?.warehouseId ?? props.targets?.warehouses[0]?.id ?? null
    );
}

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        validator.onStart();
        form.warehouse_id = defaultWarehouseId();
    }
});

// The destinations may arrive after the dialog opened.
watch(
    () => props.targets,
    () => {
        if (form.warehouse_id === null) {
            form.warehouse_id = defaultWarehouseId();
        }
    },
);

function submit(): void {
    const { target } = props;

    if (!target || !props.targets || !validator.onBefore()) {
        return;
    }

    form.transform((data) => ({
        ...data,
        note: data.note === '' ? null : data.note,
        return_to: 'map',
        ...(props.returnWarehouseIds
            ? { warehouse_ids: props.returnWarehouseIds }
            : {}),
    })).submit(transfer(target.palletId), {
        preserveScroll: true,
        // The map stays mounted; the page refreshes its rows after.
        preserveState: true,
        onStart: validator.onStart,
        onSuccess: () => {
            open.value = false;
            emit('transferred');
        },
    });
}
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="
            t('cells.palletActions.transfer.title', {
                location: target?.location ?? '',
            })
        "
        :close-label="t('common.close')"
    >
        <p
            v-if="target"
            data-testid="pallet-transfer-product"
            class="mb-4 truncate rounded-md border border-line bg-subtle p-3 font-medium text-ink"
        >
            {{ target.productName }}
        </p>

        <p
            v-if="!targets"
            data-testid="pallet-transfer-loading"
            class="flex items-center gap-2 text-sm text-muted"
        >
            <LoaderCircle class="h-4 w-4 shrink-0 animate-spin" />
            {{ t('cells.palletActions.transfer.loading') }}
        </p>

        <form
            v-else
            id="pallet-transfer-form"
            ref="formElement"
            class="space-y-4"
            novalidate
            @submit.prevent="submit"
            @focusout="validator.onFocusOut"
            @input="validator.onInput"
            @change="validator.onChange"
        >
            <RequiredLegend />
            <ActionErrorBanner :message="bannerError" />

            <PalletTransferFields
                v-model:warehouse-id="form.warehouse_id"
                v-model:row-letter="form.row_letter"
                v-model:cell-number="form.cell_number"
                v-model:flat-number="form.flat_number"
                id-prefix="pallet-transfer-to-"
                :warehouses="targets.warehouses"
                :rows="targets.rows"
                :errors="fieldErrors"
            />

            <div>
                <label for="pallet-transfer-note" :class="fieldLabelClass">
                    {{ t('cells.palletActions.noteLabel') }}
                </label>
                <textarea
                    id="pallet-transfer-note"
                    v-model="form.note"
                    name="note"
                    rows="3"
                    maxlength="1000"
                    :placeholder="t('cells.palletActions.notePlaceholder')"
                    :class="plainFieldInputClass"
                ></textarea>
                <FieldError :message="fieldErrors.note" />
            </div>

            <p
                v-if="validator.blocked.value"
                role="alert"
                class="flex items-center gap-1 text-sm text-danger"
            >
                <CircleAlert class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                {{ t('common.formValidation.blocked') }}
            </p>
        </form>

        <template #footer>
            <SubmitButton
                form="pallet-transfer-form"
                :label="t('cells.palletActions.transfer.submit')"
                :processing-label="t('cells.palletActions.submitting')"
                :processing="form.processing"
                :disabled="form.processing || !targets"
            />
        </template>
    </FilterDialog>
</template>

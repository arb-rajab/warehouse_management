<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import StoreCurrencyFormFields from '@/components/StoreCurrencyFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import type { StoreCurrency } from '@/types/store';
const props = defineProps<{ currency: StoreCurrency | null }>();
const open = defineModel<boolean>('open', { required: true });
const form = useForm({ name: '', symbol: '', code: '', exchange_rate: '' });
watch(
    [open, () => props.currency],
    () => {
        form.clearErrors();
        form.name = props.currency?.name ?? '';
        form.symbol = props.currency?.symbol ?? '';
        form.code = props.currency?.code ?? '';
        form.exchange_rate = props.currency?.exchange_rate ?? '';
    },
    { immediate: true },
);
function onInput(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (['name', 'symbol', 'code', 'exchange_rate'].includes(input.name)) {
        form[input.name as 'name' | 'symbol' | 'code' | 'exchange_rate'] =
            input.value;
    }
}
function submit(): void {
    form.submit(props.currency ? update(props.currency) : store(), {
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t(currency ? 'store.currencies.edit' : 'store.currencies.add')"
        :close-label="t('common.close')"
    >
        <form id="currency-form" @submit.prevent="submit" @input="onInput">
            <ActionErrorBanner :message="firstError(form.errors)" />
            <StoreCurrencyFormFields
                :key="currency?.id ?? 'new'"
                :currency="form"
                :errors="form.errors"
            />
        </form>
        <template #footer>
            <SubmitButton
                form="currency-form"
                :label="t('store.reference.save')"
                :processing-label="t('store.reference.saving')"
                :processing="form.processing"
            />
        </template>
    </FilterDialog>
</template>

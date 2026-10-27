<script setup lang="ts">
import { Form, useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import {
    modal,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import { paymentModal } from '@/actions/App/Http/Controllers/Store/Admin/SellerWithdrawRequestController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FieldError from '@/components/FieldError.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FormField from '@/components/FormField.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { confirmSellerPayment } from '@/lib/confirm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { provideRequiredFields } from '@/lib/requiredFields';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreSellerPayout, StoreSellerWithdrawal } from '@/types/store';

const props = defineProps<{
    shopId: number | null;
    withdrawal?: StoreSellerWithdrawal | null;
}>();
const open = defineModel<boolean>('open', { required: true });
const access = useStoreAccess();
const details = ref<StoreSellerPayout | null>(null);
const failed = ref(false);
const method = ref('');
const http = useHttp<
    { id: number; seller_withdraw_request_id: number | null },
    StoreSellerPayout
>({ id: 0, seller_withdraw_request_id: null });
let sequence = 0;
watch(
    [open, () => props.shopId, () => props.withdrawal?.id],
    async () => {
        const current = ++sequence;
        http.cancel();
        details.value = null;
        failed.value = false;
        method.value = '';

        if (!open.value || !access.value.can('pay_to_seller')) {
            return;
        }

        http.id = props.withdrawal?.seller.id ?? props.shopId ?? 0;
        http.seller_withdraw_request_id = props.withdrawal?.id ?? null;

        try {
            const response = await http.post(
                props.withdrawal ? paymentModal.url() : modal.url(),
            );

            if (current === sequence) {
                details.value = response ?? null;
                failed.value = !response;
            }
        } catch {
            if (current === sequence) {
                failed.value = true;
            }
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => http.cancel());
const bankFields = [
    'bank_name',
    'bank_acc_name',
    'bank_acc_no',
    'bank_routing_no',
] as const;
const hasRequiredFields = provideRequiredFields();
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t('store.sellers.payment')"
        :close-label="t('common.close')"
    >
        <p v-if="http.processing" role="status" class="flex items-center gap-2">
            <LoaderCircle class="h-4 w-4 animate-spin" />{{
                t('store.sellers.loading')
            }}
        </p>
        <ActionErrorBanner
            :message="failed ? t('store.sellers.paymentError') : undefined"
        />
        <Form
            v-if="details && access.can('pay_to_seller')"
            :action="store({ mergeQuery: {} })"
            :options="{ preserveScroll: true }"
            @before="() => confirmSellerPayment()"
            @success="open = false"
            #default="{ errors, processing }"
            class="space-y-4"
        >
            <RequiredLegend v-if="hasRequiredFields" />
            <ActionErrorBanner :message="firstError(errors)" />
            <dl class="grid grid-cols-2 gap-3">
                <dt>
                    {{
                        t(
                            details.shop.admin_to_pay < 0
                                ? 'store.sellers.dueToAdmin'
                                : 'store.sellers.dueToSeller',
                        )
                    }}
                </dt>
                <dd>{{ details.shop.due }}</dd>
                <template v-if="details.shop.bank_enabled"
                    ><template v-for="key in bankFields" :key="key"
                        ><dt>{{ t(`store.sellers.${key}`) }}</dt>
                        <dd class="break-words">
                            {{ details.shop[key] }}
                        </dd></template
                    ></template
                >
            </dl>
            <template
                v-if="!details.withdrawal || details.shop.admin_to_pay > 0"
            >
                <input type="hidden" name="shop_id" :value="details.shop.id" />
                <template v-if="details.withdrawal"
                    ><input
                        type="hidden"
                        name="payment_withdraw"
                        value="withdraw_request" /><input
                        type="hidden"
                        name="withdraw_request_id"
                        :value="details.withdrawal.id"
                /></template>
                <FormField
                    id="seller-payment-amount"
                    name="amount"
                    type="number"
                    :min="details.withdrawal ? details.withdrawal.amount : 0.01"
                    :max="
                        details.withdrawal
                            ? Math.min(
                                  details.withdrawal.amount,
                                  details.shop.admin_to_pay,
                              )
                            : Math.abs(details.shop.admin_to_pay)
                    "
                    step="0.01"
                    required
                    :value="
                        details.withdrawal
                            ? details.withdrawal.amount
                            : Math.abs(details.shop.admin_to_pay)
                    "
                    :label="t('store.sellers.amount')"
                    :error="errors.amount"
                />
                <label :class="fieldLabelClass"
                    >{{ t('store.sellers.paymentMethod')
                    }}<RequiredMark /><select
                        v-model="method"
                        name="payment_option"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option value="">
                            {{ t('store.sellers.selectPaymentMethod') }}
                        </option>
                        <option
                            v-if="
                                details.shop.admin_to_pay > 0 &&
                                details.shop.cash_enabled
                            "
                            value="cash"
                        >
                            {{ t('store.sellers.cash') }}
                        </option>
                        <option
                            v-if="
                                details.shop.admin_to_pay > 0 &&
                                details.shop.bank_enabled
                            "
                            value="bank_payment"
                        >
                            {{ t('store.sellers.bankPayment') }}
                        </option>
                        <option
                            v-if="details.shop.admin_to_pay <= 0"
                            value="seller_paid_to_admin"
                        >
                            {{ t('store.sellers.sellerPaidToAdmin') }}
                        </option></select
                    ><FieldError :message="errors.payment_option"
                /></label>
                <FormField
                    v-if="details.withdrawal || method === 'bank_payment'"
                    id="seller-payment-txn"
                    name="txn_code"
                    maxlength="191"
                    :label="t('store.sellers.txnCode')"
                    :error="errors.txn_code"
                />
                <SubmitButton
                    :processing="processing"
                    :label="
                        t(
                            details.shop.admin_to_pay > 0
                                ? 'store.sellers.pay'
                                : 'store.sellers.clearDue',
                        )
                    "
                    :processing-label="t('store.sellers.paying')"
                />
            </template>
        </Form>
    </FilterDialog>
</template>

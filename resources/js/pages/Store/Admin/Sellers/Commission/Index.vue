<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { updateSellerCommissionActivation } from '@/actions/App/Http/Controllers/BusinessSettingsController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/SellerCommissionController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreSellerCommissionSettings } from '@/types/store';

const props = defineProps<{ settings: StoreSellerCommissionSettings }>();
const access = useStoreAccess();
const canConfigure = computed(() =>
    access.value.can('seller_commission_configuration'),
);
const canActivate = computed(
    () =>
        canConfigure.value &&
        access.value.isRaizerAdmin &&
        access.value.can('features_activation'),
);
const switches = [
    'vendor_commission_activation',
    'category_wise_commission',
] as const;
type Switch = (typeof switches)[number];
const values = ref({
    vendor_commission_activation: props.settings.vendor_commission_activation,
    category_wise_commission: props.settings.category_wise_commission,
});
const http = useHttp<{ type: Switch; value: number }, string | number>({
    type: 'vendor_commission_activation',
    value: 0,
});
const saved = ref(false);
const failed = ref(false);
async function activate(key: Switch, event: Event) {
    const select = event.target as HTMLSelectElement;
    const previous = values.value[key];

    if (!canActivate.value || http.processing) {
        select.value = previous ? '1' : '0';

        return;
    }

    saved.value = false;
    failed.value = false;
    http.type = key;
    http.value = Number(select.value);

    try {
        const response = await http.post(
            updateSellerCommissionActivation.url(),
        );

        if (String(response) !== '1') {
            failed.value = true;
            select.value = previous ? '1' : '0';

            return;
        }

        values.value[key] = http.value === 1;
        saved.value = true;
    } catch {
        failed.value = true;
        select.value = previous ? '1' : '0';
    }
}
onBeforeUnmount(() => http.cancel());
</script>

<template>
    <div>
        <ResourceFormPage
            v-if="canConfigure"
            :title="t('store.sellers.commissionTitle')"
            :action="update()"
            :submit-label="t('store.sellers.save')"
            :submitting-label="t('store.sellers.saving')"
            :layout="StoreAdminLayout"
        >
            <template #default="{ errors }">
                <FieldError :message="errors.types" />
                <input type="hidden" name="types[]" value="vendor_commission" />
                <input
                    type="hidden"
                    name="types[]"
                    value="minimum_seller_amount_withdraw"
                />
                <FormField
                    id="vendor_commission"
                    :label="t('store.sellers.commissionPercent')"
                    type="number"
                    :value="settings.vendor_commission"
                    :error="errors.vendor_commission"
                    required
                    min="0"
                    max="100"
                    step="0.01"
                />
                <FormField
                    id="minimum_seller_amount_withdraw"
                    :label="t('store.sellers.minimumWithdrawal')"
                    type="number"
                    :value="settings.minimum_seller_amount_withdraw"
                    :error="errors.minimum_seller_amount_withdraw"
                    required
                    min="0"
                    step="any"
                />
                <p class="text-sm text-gray-700 dark:text-neutral-200">
                    {{
                        t('store.sellers.commissionNote', {
                            percentage: settings.vendor_commission,
                        })
                    }}
                </p>
                <p class="text-sm text-gray-700 dark:text-neutral-200">
                    {{ t('store.sellers.categoryCommissionNote') }}
                </p>
            </template>
            <template #after-form>
                <div class="mt-6 space-y-4">
                    <StoreBooleanField
                        v-for="key in switches"
                        :key="key"
                        :name="key"
                        :label="t(`store.sellers.${key}`)"
                        :value="values[key]"
                        :disabled="!canActivate || http.processing"
                        @change="activate(key, $event)"
                    />
                    <p
                        v-if="!canActivate"
                        class="text-sm text-gray-700 dark:text-neutral-200"
                    >
                        {{ t('store.sellers.activationRestricted') }}
                    </p>
                    <FieldError
                        :message="http.errors.type || http.errors.value"
                    />
                    <ActionErrorBanner
                        :message="
                            failed
                                ? t('store.sellers.activationError')
                                : undefined
                        "
                    />
                    <p v-if="saved" role="status">
                        {{ t('store.sellers.activationSaved') }}
                    </p>
                </div>
            </template>
        </ResourceFormPage>
        <StoreAdminLayout v-else
            ><PageHeader :title="t('store.sellers.commissionTitle')"
        /></StoreAdminLayout>
    </div>
</template>

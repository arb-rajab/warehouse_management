<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    representatives,
    update,
    updateRepresentative,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import { search } from '@/actions/App/Http/Controllers/Store/Admin/ProductSearchController';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { SearchOption } from '@/types/searchSelect';
const props = defineProps<{
    settings: {
        minimum_order_amount_check: boolean;
        minimum_order_amount: string | number;
        default_specific_product: string;
        default_representative_check: boolean;
        default_representative_id: string | number | null;
    };
    selectedProduct: SearchOption | null;
    selectedRepresentative: SearchOption | null;
}>();
const access = useStoreAccess();
const product = ref<SearchOption | null>(props.selectedProduct);
const representative = ref<SearchOption | null>(props.selectedRepresentative);
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.orderConfiguration.title')" />
        <PageHeader :title="t('store.orderConfiguration.title')" />
        <div
            v-if="access.can('order_configuration')"
            class="grid gap-8 md:grid-cols-2"
        >
            <Form
                :action="update()"
                class="space-y-4"
                #default="{ errors, processing }"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.orderConfiguration.minimum') }}
                </h2>
                <StoreBooleanField
                    name="minimum_order_amount_check"
                    :label="t('store.orderConfiguration.minimumCheck')"
                    :value="settings.minimum_order_amount_check"
                    :error="errors.minimum_order_amount_check"
                />
                <FormField
                    id="minimum_order_amount"
                    :label="t('store.orderConfiguration.amount')"
                    type="number"
                    :value="settings.minimum_order_amount"
                    :error="errors.minimum_order_amount"
                    required
                    min="0"
                    step="any"
                />
                <StoreSearchSelect
                    id="default_specific_product"
                    v-model="product"
                    name="default_specific_product"
                    :label="t('store.orderConfiguration.product')"
                    :endpoint="search"
                    :min-chars="4"
                    :placeholder="t('store.orderConfiguration.chooseProduct')"
                    :search-placeholder="
                        t('store.orderConfiguration.searchProducts')
                    "
                    :helper="t('store.orderConfiguration.searchHint')"
                    required
                    :error="errors.default_specific_product"
                />
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
            <Form
                :action="updateRepresentative()"
                class="space-y-4"
                #default="{ errors, processing }"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.orderConfiguration.representative') }}
                </h2>
                <StoreBooleanField
                    name="default_representative_check"
                    :label="t('store.orderConfiguration.representativeCheck')"
                    :value="settings.default_representative_check"
                    :error="errors.default_representative_check"
                />
                <StoreSearchSelect
                    id="default_representative_id"
                    v-model="representative"
                    name="default_representative_id"
                    :label="t('store.orderConfiguration.representative')"
                    :endpoint="representatives"
                    :placeholder="
                        t('store.orderConfiguration.chooseRepresentative')
                    "
                    :search-placeholder="
                        t('store.orderConfiguration.searchRepresentatives')
                    "
                    required
                    :error="errors.default_representative_id"
                />
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </div>
    </StoreAdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import type { StoreCustomerPackage, StoreUpload } from '@/types/store';
const props = defineProps<{
    package?: StoreCustomerPackage;
    name?: string;
    initialFiles?: StoreUpload[];
    errors: Record<string, string>;
}>();
const logo = ref(props.package?.logo ?? '');
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.customerPackages.name')"
            :value="name ?? package?.name ?? ''"
            :error="errors.name"
            required
            maxlength="50"
        />
        <FormField
            id="amount"
            :label="t('store.customerPackages.amount')"
            type="number"
            :value="package?.amount ?? ''"
            :error="errors.amount"
            required
            min="0"
            max="999999999999999999.99"
            step="0.01"
        />
        <FormField
            id="product_upload"
            :label="t('store.customerPackages.uploads')"
            type="number"
            :value="package?.product_upload ?? ''"
            :error="errors.product_upload"
            required
            min="0"
            max="2147483647"
            step="1"
        />
        <StoreUploadField
            v-model="logo"
            name="logo"
            :label="t('store.customerPackages.logo')"
            type="image"
            :initial-files="initialFiles"
            :error="errors.logo"
        />
    </div>
</template>

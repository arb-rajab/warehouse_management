<script setup lang="ts">
import {
    index as warehousesIndex,
    store,
} from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import WarehouseFormFields from '@/components/WarehouseFormFields.vue';
import { t } from '@/lib/i18n';
import { warehouseValidation } from '@/lib/warehouseForm';
</script>

<template>
    <ResourceFormPage
        :title="t('warehouses.create.title')"
        :action="store()"
        :submit-label="t('warehouses.create.submit')"
        :submitting-label="t('warehouses.create.submitting')"
        :cancel-href="warehousesIndex()"
        :validation="warehouseValidation"
        #default="{ errors }"
    >
        <WarehouseFormFields :errors="errors" />
    </ResourceFormPage>
</template>

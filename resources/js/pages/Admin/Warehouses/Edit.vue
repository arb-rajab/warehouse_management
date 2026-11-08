<script setup lang="ts">
import {
    index as warehousesIndex,
    update,
} from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import WarehouseFormFields from '@/components/WarehouseFormFields.vue';
import { t } from '@/lib/i18n';
import type { Warehouse } from '@/types/admin';

defineProps<{
    warehouse: Warehouse;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('warehouses.edit.title', { name: warehouse.name })"
        :action="update(warehouse)"
        :submit-label="t('warehouses.edit.submit')"
        :submitting-label="t('warehouses.edit.submitting')"
        :cancel-href="warehousesIndex()"
        #default="{ errors }"
    >
        <WarehouseFormFields
            :name="warehouse.name"
            :code="warehouse.code"
            :address="warehouse.address"
            :errors="errors"
        />
    </ResourceFormPage>
</template>

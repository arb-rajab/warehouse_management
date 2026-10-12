<script setup lang="ts">
import {
    index as valuesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAttributeValueFormFields from '@/components/StoreAttributeValueFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreAttributeValue } from '@/types/storeCatalogue';

defineProps<{ value: StoreAttributeValue }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.attributeValues.edit.title')"
        :action="update(value)"
        :submit-label="t('store.attributeValues.fields.save')"
        :submitting-label="t('store.attributeValues.fields.saving')"
        :cancel-href="valuesIndex(value.attribute_id)"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreAttributeValueFormFields
            :value="value.value"
            :attribute-id="value.attribute_id"
            :errors="errors"
        />
    </ResourceFormPage>
</template>

<script setup lang="ts">
import {
    index as colorsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/ColorController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreColorFormFields from '@/components/StoreColorFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import type { StoreColor } from '@/types/storeCatalogue';

defineProps<{ color: StoreColor }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.colors.edit.title')"
        :action="update(color)"
        :submit-label="t('store.colors.fields.save')"
        :submitting-label="t('store.colors.fields.saving')"
        :cancel-href="colorsIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <StoreColorFormFields
            :name="color.name"
            :code="color.code"
            :errors="errors"
        />
    </ResourceFormPage>
</template>

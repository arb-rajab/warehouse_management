<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/Admin/RowController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import RowFormFields from '@/components/RowFormFields.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('rows.create.title')"
        :action="store()"
        :submit-label="t('rows.create.submit')"
        :submitting-label="t('rows.create.submitting')"
        #default="{ errors }"
    >
        <RowFormFields :errors="errors" />
    </ResourceFormPage>
</template>

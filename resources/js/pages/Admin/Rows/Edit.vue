<script setup lang="ts">
import {
    index as rowsIndex,
    update,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import RowFormFields from '@/components/RowFormFields.vue';
import { t } from '@/lib/i18n';
import { rowLinkOptions } from '@/lib/location';
import type { Row } from '@/types/admin';

defineProps<{
    row: Row;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('rows.edit.title', { letter: row.letter })"
        :action="update(row, rowLinkOptions(row.warehouse))"
        :submit-label="t('rows.edit.submit')"
        :submitting-label="t('rows.edit.submitting')"
        :cancel-href="rowsIndex()"
        #default="{ errors }"
    >
        <RowFormFields
            :warehouse-name="row.warehouse.name"
            :letter="row.letter"
            :cells-count="row.cells_count"
            :flats-count="row.flats_count"
            :errors="errors"
        />
    </ResourceFormPage>
</template>

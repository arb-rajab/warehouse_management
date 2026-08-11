<script setup lang="ts">
import { update } from '@/actions/App/Http/Controllers/Admin/RowController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import RowFormFields from '@/components/RowFormFields.vue';
import { t } from '@/lib/i18n';
import type { Row } from '@/types/admin';

const props = defineProps<{
    row: Row;
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('rows.edit.title', { letter: props.row.letter })"
        :action="update(props.row)"
        :submit-label="t('rows.edit.submit')"
        :submitting-label="t('rows.edit.submitting')"
        #default="{ errors }"
    >
        <RowFormFields
            :letter="props.row.letter"
            :cells-count="props.row.cells_count"
            :flats-count="props.row.flats_count"
            :disable-dimensions="props.row.has_pallets"
            :errors="errors"
        />
    </ResourceFormPage>
</template>

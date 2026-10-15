<script setup lang="ts">
import { Check } from '@lucide/vue';
import FilterDialog from '@/components/FilterDialog.vue';
import StoreUploadManager from '@/components/StoreUploadManager.vue';
import { filterApplyButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreUpload } from '@/types/store';

defineProps<{ multiple?: boolean; type?: string }>();
const open = defineModel<boolean>('open', { required: true });
const selected = defineModel<StoreUpload[]>('selected', { default: () => [] });
const emit = defineEmits<{ choose: [files: StoreUpload[]] }>();
</script>
<template>
    <FilterDialog
        v-model:open="open"
        :title="t('store.uploads.choose')"
        :close-label="t('common.close')"
    >
        <StoreUploadManager
            v-if="open"
            v-model:selected="selected"
            :multiple="multiple"
            :type="type"
        />
        <template #footer
            ><button
                type="button"
                :class="filterApplyButtonClass"
                @click="
                    emit('choose', selected);
                    open = false;
                "
            >
                <Check class="h-4 w-4" />{{ t('store.uploads.select') }}
            </button></template
        >
    </FilterDialog>
</template>

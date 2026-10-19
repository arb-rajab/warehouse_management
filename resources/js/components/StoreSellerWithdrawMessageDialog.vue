<script setup lang="ts">
import FilterDialog from '@/components/FilterDialog.vue';
import { t } from '@/lib/i18n';
import type { StoreSellerWithdrawal } from '@/types/store';
defineProps<{ withdrawal: StoreSellerWithdrawal | null }>();
const open = defineModel<boolean>('open', { required: true });
</script>
<template>
    <FilterDialog
        v-model:open="open"
        :title="t('store.sellers.sellerMessage')"
        :close-label="t('common.close')"
    >
        <p class="font-semibold">{{ t('store.sellers.message') }}</p>
        <p class="break-words whitespace-pre-wrap">{{ withdrawal?.message }}</p>
    </FilterDialog>
</template>

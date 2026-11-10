<script setup lang="ts">
import { CircleCheck, PencilLine } from '@lucide/vue';
import { t } from '@/lib/i18n';
import type { ReceiptStatus } from '@/types/admin';

/**
 * A truck receipt's state as a pill: soft success once finalized (its pallets
 * exist), soft warning while it is still a draft. Shared by the receipts list,
 * a receipt's page and the receipts help topic.
 */
defineProps<{
    status: ReceiptStatus;
}>();
</script>

<template>
    <span
        v-if="status === 'finalized'"
        class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-xs font-medium text-success-strong"
    >
        <CircleCheck class="h-3 w-3 shrink-0" />
        {{ t('receipts.status.finalized') }}
    </span>
    <span
        v-else
        class="inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-xs font-medium text-warning-strong"
    >
        <PencilLine class="h-3 w-3 shrink-0" />
        {{ t('receipts.status.draft') }}
    </span>
</template>

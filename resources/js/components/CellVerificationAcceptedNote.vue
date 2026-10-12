<script setup lang="ts">
import { Check } from '@lucide/vue';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import TableLink from '@/components/TableLink.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { UserSummary } from '@/types/admin';

defineProps<{
    acceptedAt: string;
    acceptedBy: UserSummary | null;
}>();
</script>

<template>
    <div
        class="mt-1 flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-neutral-400"
    >
        <Check class="h-3 w-3 shrink-0" />
        {{
            t('cellVerificationReport.acceptedOn', {
                date: formatDateTime(acceptedAt),
            })
        }}
        <template v-if="acceptedBy">
            {{ t('cellVerificationReport.acceptedBy') }}
            <TableLink :href="showUser(acceptedBy)">
                {{ acceptedBy.name }}
            </TableLink>
        </template>
    </div>
</template>

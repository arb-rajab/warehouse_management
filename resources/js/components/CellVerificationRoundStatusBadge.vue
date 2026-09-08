<script setup lang="ts">
import { t } from '@/lib/i18n';

/**
 * A verification round's completion state as a pill — green once the round is
 * finished, amber while it's still open. Shared by the rounds listing and a
 * single round's page, which differ only in what they label the finished
 * state: the listing shows a fixed "completed" label, the round page shows the
 * completion timestamp, passed through the default slot.
 */
defineProps<{
    completedAt: string | null;
}>();
</script>

<template>
    <span
        v-if="completedAt"
        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
    >
        <slot>{{ t('cellVerificationRound.status.completed') }}</slot>
    </span>
    <span
        v-else
        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
    >
        {{ t('cellVerificationRound.status.inProgress') }}
    </span>
</template>

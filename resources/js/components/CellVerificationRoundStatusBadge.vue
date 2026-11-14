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
        class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-xs font-medium text-success-strong"
    >
        <slot>{{ t('cellVerificationRound.status.completed') }}</slot>
    </span>
    <span
        v-else
        class="inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-xs font-medium text-warning-strong"
    >
        {{ t('cellVerificationRound.status.inProgress') }}
    </span>
</template>

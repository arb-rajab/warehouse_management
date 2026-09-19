<script setup lang="ts">
import { ShieldCheck } from '@lucide/vue';
import { t } from '@/lib/i18n';

defineProps<{
    isAdmin: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="
            isAdmin
                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                : 'bg-gray-100 text-gray-600 dark:bg-neutral-800 dark:text-neutral-400'
        "
    >
        <ShieldCheck v-if="isAdmin" class="h-3 w-3 shrink-0" />
        {{ isAdmin ? t('users.index.roleAdmin') : t('users.index.roleMobile') }}
    </span>
</template>

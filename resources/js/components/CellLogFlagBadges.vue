<script setup lang="ts">
import { flagReasonLabel } from '@/lib/cellStatusLogDisplay';
import { t } from '@/lib/i18n';
import type { CellStatusLog } from '@/types/admin';

defineProps<{
    flags: CellStatusLog['flags'];
}>();
</script>

<template>
    <div v-if="flags.length > 0" class="mt-1 flex flex-col gap-0.5">
        <div class="flex flex-wrap gap-1">
            <span
                v-for="flag in flags"
                :key="flag.id"
                class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                :class="
                    flag.acknowledged
                        ? 'bg-gray-100 text-gray-500 dark:bg-neutral-800 dark:text-neutral-400'
                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300'
                "
            >
                {{ flagReasonLabel(flag.reason) }}
            </span>
        </div>
        <span
            v-for="flag in flags.filter((flag) => flag.acknowledged_by)"
            :key="`acknowledged-by-${flag.id}`"
            class="text-[10px] text-gray-400 dark:text-neutral-500"
        >
            {{
                t('cellLog.flags.acknowledgedBy', {
                    name: flag.acknowledged_by!.name,
                })
            }}
        </span>
    </div>
</template>

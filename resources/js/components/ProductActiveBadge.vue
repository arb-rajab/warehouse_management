<script setup lang="ts">
import { CircleCheck, CircleSlash } from '@lucide/vue';
import { t } from '@/lib/i18n';

defineProps<{
    active: boolean;
}>();
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1 self-start rounded-full px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap"
        :class="
            active
                ? 'bg-success-soft text-success-strong'
                : 'bg-warning-soft text-warning-strong'
        "
        data-testid="product-active-badge"
    >
        <CircleCheck v-if="active" class="h-3.5 w-3.5" aria-hidden="true" />
        <CircleSlash v-else class="h-3.5 w-3.5" aria-hidden="true" />
        {{
            active ? t('products.status.active') : t('products.status.inactive')
        }}
    </span>
</template>

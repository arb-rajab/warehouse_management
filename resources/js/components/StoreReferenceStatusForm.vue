<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { CircleCheck, CircleX, LoaderCircle } from '@lucide/vue';
import { t } from '@/lib/i18n';

defineProps<{
    action: string | UrlMethodPair;
    active: boolean;
}>();
</script>

<template>
    <Form
        :action="action"
        :options="{ preserveScroll: true }"
        #default="{ processing }"
    >
        <input type="hidden" name="status" :value="active ? 0 : 1" />
        <button
            type="submit"
            :disabled="processing"
            class="inline-flex cursor-pointer items-center gap-1 rounded-full px-2 py-1 text-xs font-medium disabled:cursor-not-allowed disabled:opacity-50"
            :class="
                active
                    ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                    : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
            "
        >
            <LoaderCircle v-if="processing" class="h-3.5 w-3.5 animate-spin" />
            <CircleCheck v-else-if="active" class="h-3.5 w-3.5" />
            <CircleX v-else class="h-3.5 w-3.5" />
            {{
                t(
                    active
                        ? 'store.reference.active'
                        : 'store.reference.inactive',
                )
            }}
        </button>
    </Form>
</template>

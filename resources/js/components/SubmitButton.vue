<script setup lang="ts">
import { LoaderCircle, Trash2 } from '@lucide/vue';
import { dangerButtonClass } from '@/lib/filters';

/**
 * The form submit button. `variant="danger"` is the soft danger style with
 * the Trash2 icon for a destructive submit (Delete selection), so it never
 * reads as the brand-red primary button.
 */
withDefaults(
    defineProps<{
        label: string;
        processingLabel: string;
        processing: boolean;
        form?: string;
        variant?: 'primary' | 'danger';
    }>(),
    { variant: 'primary' },
);
</script>

<template>
    <button
        type="submit"
        :form="form"
        :disabled="processing"
        :class="
            variant === 'danger'
                ? [dangerButtonClass, 'w-full']
                : 'flex w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-brand px-3.5 py-2 text-sm font-semibold text-on-brand shadow-btn hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-50'
        "
    >
        <LoaderCircle v-if="processing" class="h-4 w-4 shrink-0 animate-spin" />
        <Trash2
            v-else-if="variant === 'danger'"
            class="h-4 w-4 shrink-0"
            aria-hidden="true"
        />
        {{ processing ? processingLabel : label }}
    </button>
</template>

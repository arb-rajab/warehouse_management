<script setup lang="ts">
import { X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineProps<{
    title: string;
    closeLabel: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const dialogRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLElement | null>(null);
const contentRef = ref<HTMLElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

function focusableElements(): HTMLElement[] {
    if (!dialogRef.value) {
        return [];
    }

    return Array.from(
        dialogRef.value.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
    );
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;

        return;
    }

    if (event.key !== 'Tab' || !dialogRef.value) {
        return;
    }

    const focusable = focusableElements();

    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(open, async (isOpen) => {
    if (isOpen) {
        previouslyFocused = document.activeElement as HTMLElement | null;
        await nextTick();
        const firstInContent = contentRef.value?.querySelector<HTMLElement>(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        );
        (firstInContent ?? closeButtonRef.value)?.focus();
    } else {
        // preventScroll: closing the dialog shouldn't snap the page back to
        // wherever the trigger button sits — e.g. the cell-highlight filter
        // dialog can scroll the page to a matching cell while it's open
        // (see Rows/Show.vue), and restoring focus must not undo that.
        previouslyFocused?.focus({ preventScroll: true });
        previouslyFocused = null;
    }
});

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div
            ref="dialogRef"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            class="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl dark:bg-neutral-900"
        >
            <div class="flex shrink-0 items-center justify-between p-6 pb-4">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button
                    ref="closeButtonRef"
                    type="button"
                    :aria-label="closeLabel"
                    class="cursor-pointer rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                    @click="open = false"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <div
                ref="contentRef"
                class="min-h-0 flex-1 overflow-y-auto px-6"
                :class="$slots.footer ? '' : 'pb-6'"
            >
                <slot />
            </div>
            <div v-if="$slots.footer" class="shrink-0 px-6 pb-6">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

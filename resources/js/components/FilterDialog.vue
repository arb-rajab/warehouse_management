<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, onMounted } from 'vue';

defineProps<{
    title: string;
    closeLabel: string;
}>();

const open = defineModel<boolean>('open', { required: true });

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

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
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            class="relative z-10 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl dark:bg-neutral-900"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button
                    type="button"
                    :aria-label="closeLabel"
                    class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                    @click="open = false"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <slot />
        </div>
    </div>
</template>

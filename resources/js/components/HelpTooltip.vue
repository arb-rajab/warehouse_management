<script setup lang="ts">
import { Info } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';

/**
 * A small (i) button with a help bubble. The bubble opens on hover and focus,
 * and toggles on click or tap for touch screens; Escape and a click outside
 * close it. Focus stays on the button throughout, and the bubble is tied to
 * it with `aria-describedby`. Placement uses logical properties, so it
 * follows the reading direction.
 */
defineProps<{
    /** Accessible name of the (i) button. */
    label: string;
    /** The help text. */
    text: string;
}>();

const id = useId();
const root = ref<HTMLElement | null>(null);
const hovered = ref(false);
const focused = ref(false);
const pinned = ref(false);
const dismissed = ref(false);

function visible(): boolean {
    return !dismissed.value && (hovered.value || focused.value || pinned.value);
}

function onEnter(): void {
    hovered.value = true;
    dismissed.value = false;
}

function onFocus(): void {
    focused.value = true;
    dismissed.value = false;
}

function onClick(): void {
    pinned.value = !pinned.value;
    dismissed.value = false;
}

function close(): void {
    pinned.value = false;
    dismissed.value = true;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && visible()) {
        close();
    }
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        pinned.value = false;
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <span
        ref="root"
        class="relative inline-flex"
        @mouseenter="onEnter"
        @mouseleave="hovered = false"
    >
        <button
            type="button"
            :aria-label="label"
            :aria-describedby="id"
            class="inline-flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-muted hover:text-ink"
            @focus="onFocus"
            @blur="focused = false"
            @click="onClick"
        >
            <Info class="h-4 w-4" />
        </button>
        <span
            v-show="visible()"
            :id="id"
            role="tooltip"
            class="absolute start-0 bottom-full z-30 mb-2 w-60 rounded-md bg-ink p-3 text-xs font-normal text-page shadow-pop"
            >{{ text }}</span
        >
    </span>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * A compact icon + value pair for a table cell, with a real tooltip that
 * explains the value ("Number of sales: 12").
 *
 * The bubble opens on hover and on keyboard focus, and toggles on tap or click
 * for touch screens; Escape, a click outside, scrolling and resizing close it.
 * It is teleported to `body` and positioned with `position: fixed`, because a
 * table's scroller (DataTable.vue) would clip an absolutely positioned bubble.
 * Its offset comes from `getBoundingClientRect()`, so it is assigned to the
 * physical `left` property (js-components.md); it is centred on the trigger,
 * which reads the same in both directions.
 *
 * The visible value is `aria-hidden`; the same text as the bubble is
 * available to assistive technology as the button's `sr-only` content.
 */
const props = defineProps<{
    /** The icon component, decorative: the label carries the meaning. */
    icon: Component;
    /** The full explanation, shown in the bubble and read by screen readers. */
    label: string;
    /** The value shown next to the icon. */
    value: string | number;
    /** Extra classes for the whole pair, such as a colour. */
    tone?: string;
}>();

const trigger = ref<HTMLElement | null>(null);
const bubble = ref<HTMLElement | null>(null);
const hovered = ref(false);
const focused = ref(false);
const pinned = ref(false);
const dismissed = ref(false);
const position = ref<{ top: string; left: string; below: boolean }>({
    top: '0px',
    left: '0px',
    below: false,
});

/** Half the bubble's `max-w-60`, so a clamped centre never leaves the screen. */
const HALF_WIDTH = 120;
const MARGIN = 8;

const visible = computed(
    () =>
        !dismissed.value &&
        (hovered.value || focused.value || pinned.value) &&
        props.label !== '',
);

function place(): void {
    const el = trigger.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const centre = rect.left + rect.width / 2;
    const below = rect.top < 48;

    position.value = {
        top: `${below ? rect.bottom + 6 : rect.top - 6}px`,
        left: `${Math.min(
            Math.max(centre, HALF_WIDTH + MARGIN),
            window.innerWidth - HALF_WIDTH - MARGIN,
        )}px`,
        below,
    };
}

function open(): void {
    dismissed.value = false;
    place();
}

function onEnter(): void {
    hovered.value = true;
    open();
}

function onFocus(): void {
    focused.value = true;
    open();
}

function onClick(): void {
    pinned.value = !pinned.value;
    open();
}

function close(): void {
    hovered.value = false;
    focused.value = false;
    pinned.value = false;
    dismissed.value = true;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && visible.value) {
        close();
    }
}

function onDocumentClick(event: MouseEvent): void {
    const target = event.target as Node;

    if (!trigger.value?.contains(target) && !bubble.value?.contains(target)) {
        pinned.value = false;
    }
}

function onViewportChange(): void {
    if (visible.value) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
});
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="inline-flex cursor-help items-center gap-1.5 rounded text-inherit tabular-nums focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        :class="tone"
        @mouseenter="onEnter"
        @mouseleave="hovered = false"
        @focus="onFocus"
        @blur="focused = false"
        @click="onClick"
    >
        <component
            :is="icon"
            class="h-3.5 w-3.5 shrink-0 opacity-70"
            aria-hidden="true"
        />
        <span aria-hidden="true">{{ value }}</span>
        <span class="sr-only">{{ label }}</span>
    </button>
    <Teleport to="body">
        <span
            v-if="visible"
            ref="bubble"
            data-icon-stat-tooltip
            aria-hidden="true"
            class="pointer-events-none fixed z-50 w-max max-w-60 -translate-x-1/2 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-normal whitespace-normal text-white shadow-lg dark:bg-neutral-100 dark:text-neutral-900"
            :class="position.below ? '' : '-translate-y-full'"
            :style="{ top: position.top, left: position.left }"
            >{{ label }}</span
        >
    </Teleport>
</template>

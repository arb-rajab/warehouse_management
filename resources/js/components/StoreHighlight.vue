<script setup lang="ts">
import { nextTick, onMounted, useTemplateRef } from 'vue';

/**
 * Marks the option or section a deep link sent the admin here to change
 * (.ai/rules/deep-link-highlight.md, ?highlight=<key>): while `active` it
 * carries `data-highlighted` and an amber ring, and on mount it scrolls its
 * first control into view (centred) and focuses it, or the block itself when
 * it holds no control (a read-only status list). Inactive, it is a plain
 * wrapper, so the content stays an ordinary form control either way.
 */
const props = withDefaults(defineProps<{ active?: boolean; as?: string }>(), {
    active: false,
    as: 'div',
});
const root = useTemplateRef<HTMLElement>('root');
const CONTROL =
    'input:not([type="hidden"]):not([type="file"]), select, textarea, button, a[href]';

onMounted(async () => {
    if (!props.active) {
        return;
    }

    await nextTick();
    const block = root.value;
    const target = block?.querySelector<HTMLElement>(CONTROL) ?? block;
    target?.scrollIntoView?.({ block: 'center' });
    target?.focus({ preventScroll: true });
});
</script>

<template>
    <component
        :is="as"
        ref="root"
        :data-highlighted="active ? '' : undefined"
        :tabindex="active ? -1 : undefined"
        :class="
            active
                ? 'rounded-lg p-3 ring-2 ring-warning ring-offset-2 ring-offset-surface focus:outline-none'
                : ''
        "
    >
        <slot />
    </component>
</template>

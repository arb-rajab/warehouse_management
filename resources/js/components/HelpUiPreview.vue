<script setup lang="ts">
// Wraps a real UI element/component (e.g. AddResourceLink, TableActionLink)
// so a help page can show what a button/control actually looks like next to
// the text describing it, instead of only naming it in prose. `pointer-events-none`
// + `aria-hidden` because the wrapped element is a decorative visual copy —
// interactive elements inside it are also given `tabindex="-1"` at each call
// site so they don't intercept keyboard focus either.
</script>

<template>
    <span
        class="pointer-events-none mt-1 inline-flex items-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 px-2 py-1.5 align-middle dark:border-neutral-700 dark:bg-neutral-900/50"
        aria-hidden="true"
    >
        <slot />
    </span>
</template>

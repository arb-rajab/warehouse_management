<script setup lang="ts">
/**
 * A group label in the back-office sidebar ("Inventory", "Activity", ...).
 * On the `lg` icon rail it stays for screen readers and a thin rule takes
 * its place.
 */
defineProps<{ label: string; collapsed?: boolean }>();
</script>

<template>
    <p
        class="px-2.5 pt-4 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-sidebar-muted uppercase rtl:text-xs rtl:tracking-normal"
        :class="{ 'lg:sr-only': collapsed }"
    >
        {{ label }}
    </p>
    <hr
        v-if="collapsed"
        aria-hidden="true"
        class="mx-2 my-2 hidden border-sidebar-line lg:block"
    />
</template>

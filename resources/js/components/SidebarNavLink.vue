<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

/**
 * One back-office sidebar link (WMS and store control panel): icon, label,
 * optional count, and when active `aria-current="page"` with the brand-soft
 * fill and the inline-start indicator bar. A Blade target (`inertia: false`)
 * loads in full. `collapsed` is the `lg` icon rail: the label stays for
 * screen readers and as the tooltip, the count sits over the icon.
 */
withDefaults(
    defineProps<{
        href: string | UrlMethodPair;
        label: string;
        active: boolean;
        icon?: Component;
        count?: number;
        inertia?: boolean;
        collapsed?: boolean;
    }>(),
    { inertia: true, collapsed: false },
);
</script>

<template>
    <component
        :is="inertia ? Link : 'a'"
        :href="inertia || typeof href === 'string' ? href : href.url"
        :aria-current="active ? 'page' : undefined"
        :title="collapsed ? label : undefined"
        class="relative flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors"
        :class="[
            active
                ? 'bg-sidebar-active font-semibold text-sidebar-active-ink before:absolute before:inset-y-2 before:-start-2.5 before:w-[3px] before:rounded-e-[3px] before:bg-sidebar-indicator'
                : 'font-medium text-sidebar-ink hover:bg-sidebar-hover hover:text-sidebar-strong',
            { 'lg:justify-center lg:before:-start-2': collapsed },
        ]"
    >
        <component
            :is="icon"
            v-if="icon"
            class="h-[18px] w-[18px] shrink-0"
            aria-hidden="true"
        />
        <span
            class="min-w-0 flex-1 truncate"
            :class="{ 'lg:sr-only': collapsed }"
            >{{ label }}</span
        >
        <span
            v-if="count"
            data-testid="sidebar-count"
            class="ms-auto rounded-full px-2 text-[11px] font-bold"
            :class="[
                active
                    ? 'bg-brand text-on-brand'
                    : 'bg-sidebar-well text-sidebar-muted',
                {
                    'lg:absolute lg:end-0 lg:-top-0.5 lg:ms-0 lg:bg-brand lg:px-1 lg:text-[10px] lg:text-on-brand':
                        collapsed,
                },
            ]"
            >{{ count }}</span
        >
    </component>
</template>

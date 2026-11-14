<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        label: string;
        value: number;
        href: string | UrlMethodPair;
        tone?: 'default' | 'warning' | 'danger';
        icon?: Component;
    }>(),
    {
        tone: 'default',
    },
);

const toneClasses: Record<'default' | 'warning' | 'danger', string> = {
    default: 'text-ink',
    warning: 'text-warning',
    danger: 'text-danger',
};

/** The icon well beside the label (Direction A stat tile). */
const wellClasses: Record<'default' | 'warning' | 'danger', string> = {
    default: 'bg-brand-soft text-brand-strong',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
};
</script>

<template>
    <div
        class="rounded-lg border border-line bg-surface p-4 shadow-card transition-colors hover:border-line-strong hover:bg-row-hover"
    >
        <Link :href="href" class="block">
            <div class="flex items-start justify-between gap-3">
                <div class="text-sm text-muted">
                    {{ label }}
                </div>
                <span
                    v-if="icon"
                    data-testid="stat-icon-well"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-md"
                    :class="wellClasses[tone]"
                >
                    <component
                        :is="icon"
                        class="h-[18px] w-[18px]"
                        aria-hidden="true"
                    />
                </span>
            </div>
            <div
                class="mt-1 text-2xl font-semibold tabular-nums"
                :class="toneClasses[tone]"
            >
                {{ value }}
            </div>
        </Link>
        <slot name="footer" />
    </div>
</template>

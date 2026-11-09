<script setup lang="ts">
import { computed } from 'vue';

/**
 * The signed-in user block at the foot of the back-office sidebar: the
 * initials avatar (the one saffron accent of the back office), the name and
 * a second line (the e-mail address). The default slot holds the actions
 * (logout). `collapsed` is the `lg` icon rail: only the avatar and the
 * actions' icons stay visible.
 */
const props = defineProps<{
    name?: string;
    detail?: string;
    collapsed?: boolean;
}>();

const initials = computed(() =>
    (props.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toLocaleUpperCase())
        .join(''),
);
</script>

<template>
    <div
        class="flex items-center gap-2.5 px-4 py-3"
        :class="{ 'lg:flex-col lg:gap-1.5 lg:px-2': collapsed }"
    >
        <span
            aria-hidden="true"
            data-testid="sidebar-avatar"
            class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-soft text-[13px] font-bold text-accent-strong"
            :title="collapsed ? name : undefined"
            >{{ initials }}</span
        >
        <div
            class="min-w-0 flex-1 leading-tight"
            :class="{ 'lg:sr-only': collapsed }"
        >
            <p class="truncate text-sm font-semibold text-sidebar-strong">
                {{ name }}
            </p>
            <p
                v-if="detail"
                class="truncate text-xs text-sidebar-muted"
                :title="detail"
            >
                {{ detail }}
            </p>
        </div>
        <slot />
    </div>
</template>

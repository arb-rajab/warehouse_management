<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

/**
 * The back-office sign-in frame (login and forced password change): the
 * Direction A paper page, the logo with the brand name (the shared Inertia
 * prop `brandName`, the same source as the sidebar, never a literal) and the
 * area title under it, the language switcher, and a card for the form in the
 * default slot.
 */
defineProps<{
    area: string;
}>();

const page = usePage();
const brandName = computed(() => page.props.brandName ?? '');
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-page px-4 py-10 text-ink"
    >
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        src="/assets/img/33logoqr.png"
                        alt=""
                        class="h-11 w-11 shrink-0 rounded-full"
                        data-testid="auth-logo"
                    />
                    <div class="min-w-0 leading-tight">
                        <h1
                            class="truncate text-lg font-bold text-ink"
                            data-testid="auth-brand"
                        >
                            {{ brandName }}
                        </h1>
                        <p
                            class="truncate text-sm text-muted"
                            data-testid="auth-area"
                        >
                            {{ area }}
                        </p>
                    </div>
                </div>
                <LanguageSwitcher />
            </div>

            <div
                class="rounded-lg border border-line bg-surface p-8 shadow-card"
            >
                <slot />
            </div>
        </div>
    </div>
</template>

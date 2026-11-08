<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, X } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import { t } from '@/lib/i18n';

/**
 * The green twin of ActionErrorBanner: shows the `success` message a
 * controller sent with Inertia::flash() above the page content. The X hides
 * it for this page only. Every response brings a new flash object, so the
 * next save shows its message again, even with the same wording. Dialog
 * actions submit with preserveScroll, so the banner may sit above the
 * viewport: a new message that is not fully visible is scrolled into view
 * (instantly when the user prefers reduced motion).
 */
const page = usePage();
const message = computed(() => page.flash?.success);
const dismissed = ref(false);

const banner = useTemplateRef<HTMLElement>('banner');

function scrollIntoViewIfHidden(): void {
    const el = banner.value;

    if (!el || typeof el.scrollIntoView !== 'function') {
        return;
    }

    const { top, bottom } = el.getBoundingClientRect();
    const viewportHeight =
        window.innerHeight || document.documentElement.clientHeight;

    if (top >= 0 && bottom <= viewportHeight) {
        return;
    }

    const reduceMotion =
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
        false;

    el.scrollIntoView({
        block: 'nearest',
        behavior: reduceMotion ? 'instant' : 'smooth',
    });
}

// A layout that remounts with the redirect brings the flash in at mount.
onMounted(scrollIntoViewIfHidden);

watch(
    () => page.flash,
    async () => {
        dismissed.value = false;
        await nextTick();
        scrollIntoViewIfHidden();
    },
);
</script>

<template>
    <p
        v-if="message && !dismissed"
        ref="banner"
        role="status"
        class="mb-4 flex items-center gap-1.5 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-400"
    >
        <CircleCheck class="h-4 w-4 shrink-0" aria-hidden="true" />
        <span class="min-w-0 flex-1">{{ message }}</span>
        <button
            type="button"
            class="-me-1 shrink-0 cursor-pointer rounded p-0.5 hover:bg-green-100 dark:hover:bg-green-900"
            :aria-label="t('common.close')"
            @click="dismissed = true"
        >
            <X class="h-4 w-4" aria-hidden="true" />
        </button>
    </p>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { update as updateLocale } from '@/actions/App/Http/Controllers/LocaleController';
import { t } from '@/lib/i18n';
import type { SupportedLocale } from '@/lib/i18n';

const page = usePage();

const locales: { code: SupportedLocale; labelKey: string }[] = [
    { code: 'en', labelKey: 'language.en' },
    { code: 'ar', labelKey: 'language.ar' },
];

/**
 * A full reload (not an Inertia visit) so the freshly booted app re-reads the
 * new session locale server-side — the `dir`/`lang` attributes on `<html>`
 * and the i18n messages are only ever set up once, at boot.
 */
function switchTo(locale: SupportedLocale): void {
    router.post(
        updateLocale(locale).url,
        {},
        { onSuccess: () => window.location.reload() },
    );
}
</script>

<template>
    <div
        class="flex items-center gap-1 text-sm"
        :aria-label="t('language.label')"
    >
        <Languages class="h-4 w-4 text-gray-400 dark:text-neutral-500" />
        <button
            v-for="locale in locales"
            :key="locale.code"
            type="button"
            class="cursor-pointer rounded px-1.5 py-0.5"
            :class="
                page.props.locale === locale.code
                    ? 'font-semibold text-gray-900 dark:text-white'
                    : 'text-gray-500 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white'
            "
            @click="switchTo(locale.code)"
        >
            {{ t(locale.labelKey) }}
        </button>
    </div>
</template>

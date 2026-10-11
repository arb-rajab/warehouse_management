<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { selectedToggleClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreLanguage } from '@/types/store';
defineProps<{
    languages: StoreLanguage[];
    lang: string;
    href: (code: string) => UrlMethodPair;
}>();
</script>

<template>
    <div>
        <nav
            v-if="languages.length > 1"
            :aria-label="t('store.reference.languages')"
            class="flex flex-wrap items-center gap-1 text-sm"
        >
            <Languages class="h-4 w-4" />
            <Link
                v-for="language in languages"
                :key="language.code"
                :href="href(language.code)"
                :aria-current="language.code === lang ? 'page' : undefined"
                class="rounded-md px-3 py-1"
                :class="
                    language.code === lang
                        ? selectedToggleClass
                        : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                "
                >{{ language.name }}</Link
            >
        </nav>
        <input type="hidden" name="lang" :value="lang" />
    </div>
</template>

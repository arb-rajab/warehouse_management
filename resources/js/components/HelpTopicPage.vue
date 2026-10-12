<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Head, Link } from '@inertiajs/vue3';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

/**
 * The page shell every `Admin/Help/<Topic>.vue` sub-page shares: the `Head`
 * title, `AdminLayout`, `PageHeader`, a link back to the help landing page,
 * and the `max-w-2xl` content column that ends in a link to the admin screen
 * the topic documents.
 *
 * The back-to-help link is identical on all seven topic pages, so it is built
 * in rather than taking props; only the trailing feature link varies.
 */
defineProps<{
    title: string;
    /** The admin screen this topic documents, linked at the end of the page. */
    featureHref: string | UrlMethodPair;
    featureLabel: string;
}>();

/** Shared by both links; only the top one needs the trailing margin. */
const topicLinkClass =
    'inline-block text-sm text-blue-600 hover:underline dark:text-blue-400';
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader :title="title" />

        <Link :href="helpIndex()" :class="[topicLinkClass, 'mb-6']">
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <slot />

            <Link :href="featureHref" :class="topicLinkClass">
                {{ featureLabel }}
            </Link>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as cellVerificationRoundsIndex } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const sections = ['what', 'reports', 'exporting'];
</script>

<template>
    <Head :title="t('help.cellVerificationRounds.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.cellVerificationRounds.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section v-for="section in sections" :key="section">
                <h2 :class="sectionHeadingClass">
                    {{ t(`help.cellVerificationRounds.${section}.heading`) }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t(`help.cellVerificationRounds.${section}.body`) }}
                </p>
            </section>

            <Link
                :href="cellVerificationRoundsIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('cellVerificationRound.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>

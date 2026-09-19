<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const otherSections = ['managingPallets', 'deactivating'];
</script>

<template>
    <Head :title="t('help.cells.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.cells.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cells.map.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.cells.map.body') }}
                </p>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cells.legend.heading') }}
                </h2>
                <p class="mb-3 text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.cells.legend.body') }}
                </p>
                <CellStateLegend />
            </section>

            <section v-for="section in otherSections" :key="section">
                <h2 :class="sectionHeadingClass">
                    {{ t(`help.cells.${section}.heading`) }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t(`help.cells.${section}.body`) }}
                </p>
            </section>

            <Link
                :href="cellsIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('cells.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>

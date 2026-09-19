<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Ban, PackageSearch, Search } from '@lucide/vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import CellStateLegend from '@/components/CellStateLegend.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    filterSectionHeadingClass as sectionHeadingClass,
    mapToolbarButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
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
                <HelpUiPreview class="mt-2">
                    <span
                        tabindex="-1"
                        class="w-40 truncate rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-400 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-500"
                    >
                        {{ t('cells.search.placeholder') }}
                    </span>
                    <span :class="mapToolbarButtonClass" tabindex="-1">
                        <Search class="h-4 w-4" />
                    </span>
                </HelpUiPreview>
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

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cells.managingPallets.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.cells.managingPallets.body') }}
                </p>
                <HelpUiPreview class="mt-2">
                    <span
                        tabindex="-1"
                        :title="t('cells.palletActions.triggerLabel')"
                        class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-neutral-400"
                    >
                        <PackageSearch class="h-4 w-4" />
                        {{ t('cells.palletActions.triggerLabel') }}
                    </span>
                </HelpUiPreview>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.cells.deactivating.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.cells.deactivating.body') }}
                </p>
                <HelpUiPreview class="mt-2">
                    <span
                        tabindex="-1"
                        class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-neutral-400"
                    >
                        <Ban class="h-4 w-4" />
                        {{ t('cells.toggleActive.deactivateLabel') }}
                    </span>
                </HelpUiPreview>
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

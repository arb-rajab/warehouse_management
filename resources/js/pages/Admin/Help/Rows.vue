<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const plainSections = ['grid', 'dimensions'];
</script>

<template>
    <Head :title="t('help.rows.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.rows.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section v-for="section in plainSections" :key="section">
                <h2 :class="sectionHeadingClass">
                    {{ t(`help.rows.${section}.heading`) }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t(`help.rows.${section}.body`) }}
                </p>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.rows.creating.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.rows.creating.body') }}
                </p>
                <HelpUiPreview class="mt-2">
                    <AddResourceLink
                        href="#"
                        tabindex="-1"
                        :label="t('rows.index.addRow')"
                    />
                </HelpUiPreview>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.rows.editing.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.rows.editing.body') }}
                </p>
                <HelpUiPreview class="mt-2">
                    <span
                        tabindex="-1"
                        class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-neutral-400"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        {{ t('rows.show.editRow') }}
                    </span>
                </HelpUiPreview>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.rows.deleting.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.rows.deleting.body') }}
                </p>
                <HelpUiPreview class="mt-2">
                    <TableActionLink href="#" tabindex="-1" variant="danger">
                        <Trash2 class="h-3.5 w-3.5" />
                        {{ t('rows.index.delete') }}
                    </TableActionLink>
                </HelpUiPreview>
            </section>

            <Link
                :href="rowsIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('rows.index.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>

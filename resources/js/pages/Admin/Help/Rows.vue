<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Trash2, TriangleAlert } from '@lucide/vue';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowFormFields from '@/components/RowFormFields.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const plainSections = ['grid', 'dimensions'];

/** Representative rows for the Rows-list table preview below — plain fixture data, never fetched. */
const previewRows = [
    { id: 1, letter: 'A', cells_count: 10, flats_count: 3 },
    { id: 2, letter: 'B', cells_count: 8, flats_count: 2 },
];
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
                <HelpUiPreview inert class="mt-2 block max-w-xs">
                    <RowFormFields id-prefix="help-row-create-" :errors="{}" />
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
                <HelpUiPreview inert class="mt-2 block max-w-xs">
                    <RowFormFields
                        id-prefix="help-row-edit-"
                        letter="A"
                        :cells-count="10"
                        :flats-count="3"
                        :disable-dimensions="true"
                        :errors="{}"
                    />
                </HelpUiPreview>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.rows.deleting.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.rows.deleting.body') }}
                </p>
                <HelpUiPreview inert class="mt-2 block">
                    <DataTable
                        :columns="[
                            t('rows.index.columnLetter'),
                            t('rows.index.columnCells'),
                            t('rows.index.columnFlats'),
                            t('rows.index.columnAction'),
                        ]"
                        :rows="previewRows"
                        :empty-message="t('rows.index.empty')"
                    >
                        <template #row="{ row }">
                            <td class="px-4 py-2">
                                <span
                                    class="font-medium text-gray-900 dark:text-neutral-100"
                                    >{{ row.letter }}</span
                                >
                            </td>
                            <td class="px-4 py-2">{{ row.cells_count }}</td>
                            <td class="px-4 py-2">{{ row.flats_count }}</td>
                            <td class="px-4 py-2">—</td>
                        </template>
                    </DataTable>
                </HelpUiPreview>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <HelpUiPreview>
                        <TableActionLink
                            href="#"
                            tabindex="-1"
                            variant="danger"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('rows.index.delete') }}
                        </TableActionLink>
                    </HelpUiPreview>
                    <HelpUiPreview>
                        <span
                            tabindex="-1"
                            :title="t('rows.index.hasPalletsTitle')"
                            class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
                        >
                            <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                            {{ t('rows.index.hasPallets') }}
                        </span>
                    </HelpUiPreview>
                </div>
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

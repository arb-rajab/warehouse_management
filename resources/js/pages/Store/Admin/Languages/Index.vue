<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    destroy,
    edit,
    show,
    showAppTranslations,
    index as languagesIndex,
    updateStatus,
    updateRtlStatus,
    importAppTranslations,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { provideRequiredFields } from '@/lib/requiredFields';
import { useStoreAccess } from '@/lib/storeAccess';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { AdminLanguage } from '@/types/store';
defineProps<{
    languages: Paginated<AdminLanguage>;
    filters: PerPageFilters;
    defaultLanguage: string;
    /** `default_language` when a setup checklist link sent the admin here. */
    highlight?: string | null;
}>();
const access = useStoreAccess();
function onPerPageChange(per_page: number): void {
    router.get(
        languagesIndex().url,
        { per_page },
        { preserveState: true, replace: true },
    );
}
const hasRequiredFields = provideRequiredFields();
const columns = useTableColumns({
    listId: 'languages.languages',
    columns: [
        { key: 'name', label: 'store.languages.name', required: true },
        { key: 'code', label: 'store.languages.code' },
        { key: 'rtl', label: 'store.languages.rtl' },
        { key: 'status', label: 'store.languages.status' },
    ],
});
const { shown, visibleColumns } = columns;
const tableColumns = computed(() => [
    ...visibleColumns.value.map((column) => t(column.label)),
    t('store.languages.actions'),
]);
</script>
<template>
    <div>
        <Head :title="t('store.languages.title')" />
        <StoreAdminLayout>
            <PageHeader :title="t('store.languages.title')">
                <AddResourceLink
                    v-if="access.can('language_setup')"
                    :href="create()"
                    :label="t('store.languages.create')"
                />
            </PageHeader>
            <StoreHighlight
                as="p"
                :active="highlight === 'default_language'"
                data-section="default_language"
                class="mb-4 flex items-center gap-2"
            >
                <TriangleAlert class="h-4 w-4" />{{
                    t('store.languages.defaultReadOnly', {
                        code: defaultLanguage,
                    })
                }}
            </StoreHighlight>
            <p class="mb-4">{{ t('store.languages.translationScope') }}</p>
            <StoreColumnPicker :state="columns" />
            <DataTable
                :columns="tableColumns"
                :rows="languages.data"
                :empty-message="t('store.languages.empty')"
            >
                <template #row="{ row: language }">
                    <td class="px-4 py-2">{{ language.name }}</td>
                    <td v-if="shown.code" class="px-4 py-2 font-mono">
                        {{ language.code }}
                    </td>
                    <td v-if="shown.rtl" class="px-4 py-2">
                        <StoreStatusSwitch
                            v-if="access.can('language_setup')"
                            :action="updateRtlStatus({ mergeQuery: {} })"
                            :checked="language.rtl"
                            :label="
                                t('store.languages.rtlFor', {
                                    name: language.name,
                                })
                            "
                            ><input
                                type="hidden"
                                name="id"
                                :value="language.id"
                        /></StoreStatusSwitch>
                    </td>
                    <td v-if="shown.status" class="px-4 py-2">
                        <StoreStatusSwitch
                            v-if="access.can('language_setup')"
                            :action="updateStatus({ mergeQuery: {} })"
                            :checked="language.status"
                            :label="
                                t('store.languages.statusFor', {
                                    name: language.name,
                                })
                            "
                            :locked="
                                language.is_default && language.status
                                    ? {
                                          reason: t(
                                              'store.languages.defaultLocked',
                                          ),
                                          hint: t(
                                              'store.languages.defaultReadOnly',
                                              { code: defaultLanguage },
                                          ),
                                      }
                                    : undefined
                            "
                            ><input
                                type="hidden"
                                name="id"
                                :value="language.id"
                        /></StoreStatusSwitch>
                    </td>
                    <td class="px-4 py-2">
                        <div
                            v-if="access.can('language_setup')"
                            class="flex flex-nowrap gap-2"
                        >
                            <TableActionLink :href="edit(language)"
                                ><Pencil class="h-3.5 w-3.5" />{{
                                    t('store.languages.edit')
                                }}</TableActionLink
                            >
                            <TableActionLink :href="show(language)"
                                ><Eye class="h-3.5 w-3.5" />{{
                                    t('store.languages.translations')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                :href="showAppTranslations(language)"
                                ><Eye class="h-3.5 w-3.5" />{{
                                    t('store.languages.appTranslations')
                                }}</TableActionLink
                            >
                            <TableActionLink
                                v-if="
                                    !language.is_default && !language.is_english
                                "
                                :href="destroy(language, { mergeQuery: {} })"
                                method="delete"
                                as="button"
                                variant="danger"
                                :on-before="() => confirmDelete(language.name)"
                                ><Trash2 class="h-3.5 w-3.5" />{{
                                    t('store.languages.delete')
                                }}</TableActionLink
                            >
                        </div>
                    </td>
                </template>
            </DataTable>
            <Pagination
                :links="languages.meta.links"
                :per-page="filters.per_page ?? 20"
                @update:per-page="onPerPageChange"
            />
            <Form
                v-if="access.can('language_setup')"
                :action="importAppTranslations()"
                #default="{ errors, processing }"
                class="mt-6 max-w-sm space-y-4"
            >
                <p>{{ t('store.languages.mobileImportScope') }}</p>
                <RequiredLegend v-if="hasRequiredFields" />
                <FormField
                    id="lang_file"
                    type="file"
                    accept=".json,application/json"
                    :label="t('store.languages.importFile')"
                    :error="errors.lang_file"
                    required
                />
                <SubmitButton
                    :label="t('store.languages.import')"
                    :processing-label="t('store.settings.saving')"
                    :processing="processing"
                />
            </Form>
        </StoreAdminLayout>
    </div>
</template>

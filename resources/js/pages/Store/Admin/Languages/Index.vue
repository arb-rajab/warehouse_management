<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2, TriangleAlert } from '@lucide/vue';
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
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { AdminLanguage } from '@/types/store';
defineProps<{
    languages: Paginated<AdminLanguage>;
    filters: PerPageFilters;
    defaultLanguage: string;
}>();
const access = useStoreAccess();
function onPerPageChange(per_page: number): void {
    router.get(
        languagesIndex().url,
        { per_page },
        { preserveState: true, replace: true },
    );
}
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
            <p class="mb-4 flex items-center gap-2">
                <TriangleAlert class="h-4 w-4" />{{
                    t('store.languages.defaultReadOnly', {
                        code: defaultLanguage,
                    })
                }}
            </p>
            <DataTable
                :columns="[
                    t('store.languages.name'),
                    t('store.languages.code'),
                    t('store.languages.rtl'),
                    t('store.languages.status'),
                    t('store.languages.actions'),
                ]"
                :rows="languages.data"
                :empty-message="t('store.languages.empty')"
            >
                <template #row="{ row: language }">
                    <td class="px-4 py-2">{{ language.name }}</td>
                    <td class="px-4 py-2">{{ language.code }}</td>
                    <td class="px-4 py-2">
                        <Form
                            v-if="access.can('language_setup')"
                            :action="updateRtlStatus({ mergeQuery: {} })"
                            :transform="
                                (data) => ({
                                    id: data.id,
                                    status: data[`rtl-${language.id}`],
                                })
                            "
                            #default="{ errors, processing }"
                        >
                            <input
                                type="hidden"
                                name="id"
                                :value="language.id"
                            />
                            <StoreBooleanField
                                :name="`rtl-${language.id}`"
                                :label="t('store.languages.rtl')"
                                :value="language.rtl"
                                :error="errors.status"
                            />
                            <SubmitButton
                                :label="t('store.settings.save')"
                                :processing-label="t('store.settings.saving')"
                                :processing="processing"
                            />
                        </Form>
                    </td>
                    <td class="px-4 py-2">
                        <Form
                            v-if="access.can('language_setup')"
                            :action="updateStatus({ mergeQuery: {} })"
                            :transform="
                                (data) => ({
                                    id: data.id,
                                    status: language.is_default
                                        ? 1
                                        : data[`status-${language.id}`],
                                })
                            "
                            #default="{ errors, processing }"
                        >
                            <input
                                type="hidden"
                                name="id"
                                :value="language.id"
                            />
                            <StoreBooleanField
                                :name="`status-${language.id}`"
                                :label="t('store.languages.status')"
                                :value="language.status"
                                :disabled="language.is_default"
                                :error="errors.status"
                            />
                            <input
                                v-if="language.is_default"
                                type="hidden"
                                name="status"
                                value="1"
                            />
                            <SubmitButton
                                v-if="!language.is_default"
                                :label="t('store.settings.save')"
                                :processing-label="t('store.settings.saving')"
                                :processing="processing"
                            />
                        </Form>
                    </td>
                    <td class="px-4 py-2">
                        <div
                            v-if="access.can('language_setup')"
                            class="flex flex-wrap gap-2"
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

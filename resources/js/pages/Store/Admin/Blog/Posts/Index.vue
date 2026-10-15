<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Pencil, Trash2, X } from '@lucide/vue';
import { computed } from 'vue';
import {
    changeStatus,
    create,
    destroy,
    edit,
    index as blogsIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreBlogSummary } from '@/types/store';
const props = defineProps<{
    blogs: Paginated<StoreBlogSummary>;
    filters: PerPageFilters & { search?: string | null };
}>();
const access = useStoreAccess();
const currentPerPage = computed(() => props.filters.per_page ?? 20);
function onPerPageChange(perPage: number): void {
    router.get(
        blogsIndex().url,
        { per_page: perPage, search: props.filters.search },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="t('store.blogPosts.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.blogPosts.index.title')">
            <AddResourceLink
                v-if="access.can('add_blog')"
                :href="create()"
                :label="t('store.blogPosts.index.add')"
            />
        </PageHeader>
        <Form
            :action="blogsIndex()"
            #default="{ errors, processing }"
            class="mb-4 flex items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="currentPerPage" />
            <FormField
                id="search"
                :label="t('store.blogPosts.index.search')"
                :value="filters.search ?? ''"
                :error="errors.search"
                maxlength="255"
            />
            <SubmitButton
                :label="t('store.blogPosts.index.search')"
                :processing-label="t('store.blogPosts.index.searching')"
                :processing="processing"
            />
        </Form>
        <DataTable
            :columns="[
                t('store.blogPosts.index.columnTitle'),
                t('store.blogPosts.index.columnCategory'),
                t('store.blogPosts.index.columnStatus'),
                t('store.blogPosts.index.columnActions'),
            ]"
            :rows="blogs.data"
            :empty-message="t('store.blogPosts.index.empty')"
        >
            <template #row="{ row: blog }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ blog.title }}
                </td>
                <td class="px-4 py-2">
                    {{ blog.category?.category_name ?? '—' }}
                </td>
                <td class="px-4 py-2">
                    <Form
                        v-if="access.can('publish_blog')"
                        :action="changeStatus(blog, { mergeQuery: {} })"
                        :options="{ preserveScroll: true }"
                        #default="{ errors, processing }"
                    >
                        <input
                            type="hidden"
                            name="status"
                            :value="blog.status ? '0' : '1'"
                        />
                        <button
                            type="submit"
                            :class="filterTriggerButtonClass"
                            :disabled="processing"
                            :aria-pressed="blog.status"
                        >
                            <Check v-if="blog.status" class="size-4" /><X
                                v-else
                                class="size-4"
                            />
                            {{
                                t(
                                    processing
                                        ? 'store.blogPosts.index.updating'
                                        : blog.status
                                          ? 'store.blogPosts.index.unpublish'
                                          : 'store.blogPosts.index.publish',
                                )
                            }}
                        </button>
                        <FieldError :message="errors.status" />
                    </Form>
                    <Check
                        v-else-if="blog.status"
                        :aria-label="t('store.blogPosts.index.columnStatus')"
                        class="size-4"
                    />
                    <X v-else class="size-4" />
                </td>
                <td class="px-4 py-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            :href="blog.public_url"
                            :class="filterTriggerButtonClass"
                            ><ArrowUpRight class="size-4" />{{
                                t('store.blogPosts.index.view')
                            }}</a
                        >
                        <TableActionLink
                            v-if="access.can('edit_blog')"
                            :href="edit(blog)"
                            ><Pencil class="size-3.5" />{{
                                t('store.blogPosts.index.edit')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_blog')"
                            :href="destroy(blog, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(blog.title)"
                            ><Trash2 class="size-3.5" />{{
                                t('store.blogPosts.index.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="blogs.meta.links"
            :per-page="currentPerPage"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>

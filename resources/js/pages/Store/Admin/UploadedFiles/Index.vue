<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ClipboardCopy, Download, Info, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    bulkDestroy,
    create,
    destroy,
    index as uploadedFilesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreFileFilterBar from '@/components/StoreFileFilterBar.vue';
import StoreFileGrid from '@/components/StoreFileGrid.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { pillLinkClass, primaryPillVariantClass } from '@/lib/actionLink';
import { confirmDelete } from '@/lib/confirm';
import { formatDateTime } from '@/lib/date';
import { filterClearButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatFileSize, uploadDisplayName } from '@/lib/storeUploads';
import type { Paginated } from '@/types/admin';
import type { StoreUpload, StoreUploadFilters } from '@/types/store';

const props = defineProps<{
    uploads: Paginated<StoreUpload>;
    filters: StoreUploadFilters;
}>();

const search = ref(props.filters.search ?? '');
const sort = ref(props.filters.sort);
const selectedIds = ref<number[]>([]);
const details = ref<StoreUpload | null>(null);
const copyMessage = ref('');

const allSelected = computed(
    () =>
        props.uploads.data.length > 0 &&
        props.uploads.data.every((file) => selectedIds.value.includes(file.id)),
);

function reload(perPage: number = props.filters.per_page): void {
    router.get(
        uploadedFilesIndex().url,
        {
            search: search.value || undefined,
            sort: sort.value,
            per_page: perPage,
        },
        { preserveState: true, replace: true },
    );
}

function toggle(file: StoreUpload): void {
    selectedIds.value = selectedIds.value.includes(file.id)
        ? selectedIds.value.filter((id) => id !== file.id)
        : [...selectedIds.value, file.id];
}

function toggleAll(): void {
    selectedIds.value = allSelected.value
        ? []
        : props.uploads.data.map((file) => file.id);
}

async function copyLink(file: StoreUpload): Promise<void> {
    try {
        await navigator.clipboard.writeText(file.url ?? '');
        copyMessage.value = t('store.uploads.index.linkCopied');
    } catch {
        copyMessage.value = t('store.uploads.index.linkNotCopied');
    }
}

const actionButtonClass = [
    pillLinkClass,
    primaryPillVariantClass,
    'cursor-pointer',
];
</script>

<template>
    <Head :title="t('store.uploads.index.title')" />

    <StoreAdminLayout>
        <PageHeader :title="t('store.uploads.index.title')">
            <AddResourceLink
                :href="create()"
                :label="t('store.uploads.index.uploadNew')"
            />
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <StoreFileFilterBar
                v-model:search="search"
                v-model:sort="sort"
                id-prefix="uploaded-files"
                class="flex-1"
                @submit="reload()"
            />
            <Form
                :action="bulkDestroy({ mergeQuery: {} })"
                :on-before="
                    () =>
                        confirmDelete(
                            t('store.uploads.index.selection', {
                                count: selectedIds.length,
                            }),
                        )
                "
                :options="{ preserveScroll: true }"
                class="flex items-center gap-2"
                #default="{ processing }"
                @success="selectedIds = []"
            >
                <input
                    v-for="id in selectedIds"
                    :key="id"
                    type="hidden"
                    name="ids[]"
                    :value="id"
                />
                <label
                    class="inline-flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer"
                        :checked="allSelected"
                        :disabled="uploads.data.length === 0"
                        @change="toggleAll"
                    />
                    {{ t('store.uploads.index.selectAll') }}
                </label>
                <button
                    type="submit"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-700 dark:hover:bg-red-600"
                    :disabled="selectedIds.length === 0 || processing"
                >
                    <Trash2 class="h-4 w-4 shrink-0" />
                    {{ t('store.uploads.index.deleteSelection') }}
                </button>
            </Form>
        </div>

        <p
            aria-live="polite"
            class="text-sm text-gray-600 dark:text-neutral-400"
            :class="copyMessage ? 'mb-4' : ''"
        >
            {{ copyMessage }}
        </p>

        <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
            <StoreFileGrid
                :files="uploads.data"
                :selected-ids="selectedIds"
                :empty-message="t('store.uploads.empty')"
                :class="details ? '' : 'lg:col-span-2'"
                @toggle="toggle"
            >
                <template #actions="{ file }">
                    <div class="mt-1 flex flex-wrap gap-1">
                        <button
                            type="button"
                            :class="actionButtonClass"
                            :aria-label="
                                t('store.uploads.index.detailsFor', {
                                    name: uploadDisplayName(file),
                                })
                            "
                            @click="details = file"
                        >
                            <Info class="h-3.5 w-3.5" />
                            {{ t('store.uploads.index.details') }}
                        </button>
                        <a
                            v-if="file.url"
                            :href="file.url"
                            :download="uploadDisplayName(file)"
                            target="_blank"
                            rel="noopener"
                            :class="actionButtonClass"
                        >
                            <Download class="h-3.5 w-3.5" />
                            {{ t('store.uploads.index.download') }}
                        </a>
                        <button
                            v-if="file.url"
                            type="button"
                            :class="actionButtonClass"
                            @click="copyLink(file)"
                        >
                            <ClipboardCopy class="h-3.5 w-3.5" />
                            {{ t('store.uploads.index.copyLink') }}
                        </button>
                        <TableActionLink
                            :href="destroy(file, { mergeQuery: {} })"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="
                                () => confirmDelete(uploadDisplayName(file))
                            "
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('store.uploads.index.delete') }}
                        </TableActionLink>
                    </div>
                </template>
            </StoreFileGrid>

            <aside
                v-if="details"
                class="h-fit rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-neutral-800 dark:bg-neutral-900"
                :aria-label="t('store.uploads.info.title')"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">
                        {{ t('store.uploads.info.title') }}
                    </h2>
                    <button
                        type="button"
                        :class="filterClearButtonClass"
                        :aria-label="t('common.close')"
                        @click="details = null"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <dl class="space-y-2">
                    <div>
                        <dt class="text-gray-500 dark:text-neutral-400">
                            {{ t('store.uploads.info.name') }}
                        </dt>
                        <dd class="break-all">
                            {{ uploadDisplayName(details) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-neutral-400">
                            {{ t('store.uploads.info.type') }}
                        </dt>
                        <dd>
                            {{
                                details.type
                                    ? t(`store.uploads.kind.${details.type}`)
                                    : t('store.uploads.unknownName')
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-neutral-400">
                            {{ t('store.uploads.info.size') }}
                        </dt>
                        <dd>{{ formatFileSize(details.size) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-neutral-400">
                            {{ t('store.uploads.info.uploadedBy') }}
                        </dt>
                        <dd>
                            {{
                                details.uploaded_by ??
                                t('store.uploads.unknownName')
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-neutral-400">
                            {{ t('store.uploads.info.uploadedAt') }}
                        </dt>
                        <dd>
                            {{
                                details.created_at
                                    ? formatDateTime(details.created_at)
                                    : t('store.uploads.unknownName')
                            }}
                        </dd>
                    </div>
                </dl>
                <a
                    v-if="details.url"
                    :href="details.url"
                    :download="uploadDisplayName(details)"
                    target="_blank"
                    rel="noopener"
                    :class="[pillLinkClass, primaryPillVariantClass, 'mt-4']"
                >
                    <Download class="h-3.5 w-3.5" />
                    {{ t('store.uploads.info.download') }}
                </a>
            </aside>
        </div>

        <Pagination
            :links="uploads.meta.links"
            :per-page="filters.per_page"
            @update:per-page="reload"
        />
    </StoreAdminLayout>
</template>

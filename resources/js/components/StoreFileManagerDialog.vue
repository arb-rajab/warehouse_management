<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, LoaderCircle, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { browse as browseUploads } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import FilterDialog from '@/components/FilterDialog.vue';
import StoreFileFilterBar from '@/components/StoreFileFilterBar.vue';
import StoreFileGrid from '@/components/StoreFileGrid.vue';
import StoreFileUploader from '@/components/StoreFileUploader.vue';
import {
    filterApplyButtonClass,
    filterClearButtonClass,
    filterFooterClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type {
    StoreUpload,
    StoreUploadKind,
    StoreUploadSort,
} from '@/types/store';

/**
 * The store's file manager, replacing the Blade `aizuploader` modal: pick
 * from the signed-in account's own files (searched, sorted and paged on the
 * server, 60 at a time), or upload new ones, then confirm. `type` limits the
 * files offered to one kind, as an image-only field asks for images.
 */
const props = defineProps<{
    multiple?: boolean;
    type?: StoreUploadKind;
    /** The files the field holds now, ticked when the dialog opens. */
    selected: StoreUpload[];
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    select: [files: StoreUpload[]];
}>();

const tab = ref<'browse' | 'upload'>('browse');
const search = ref('');
const sort = ref<StoreUploadSort>('newest');
const files = ref<StoreUpload[]>([]);
const page = ref<Paginated<StoreUpload> | null>(null);
const chosen = ref<StoreUpload[]>([]);

const http = useHttp<Record<string, never>, Paginated<StoreUpload>>({});

function fetchPage(pageNumber: number): void {
    http.get(
        browseUploads.url({
            query: {
                search: search.value || undefined,
                sort: sort.value,
                type: props.type,
                page: pageNumber,
            },
        }),
        {
            onSuccess: (response) => {
                files.value =
                    pageNumber === 1
                        ? response.data
                        : [...files.value, ...response.data];
                page.value = response;
            },
        },
    ).catch(() => undefined);
}

watch(open, (isOpen) => {
    if (isOpen) {
        tab.value = 'browse';
        chosen.value = [...props.selected];
        fetchPage(1);
    }
});

function isChosen(file: StoreUpload): boolean {
    return chosen.value.some((picked) => picked.id === file.id);
}

function toggle(file: StoreUpload): void {
    if (isChosen(file)) {
        chosen.value = chosen.value.filter((picked) => picked.id !== file.id);
    } else {
        chosen.value = props.multiple ? [...chosen.value, file] : [file];
    }
}

function onUploaded(upload: StoreUpload): void {
    if (props.type === undefined || upload.type === props.type) {
        files.value = [upload, ...files.value];
        chosen.value = props.multiple ? [...chosen.value, upload] : [upload];
    }
}

function confirm(): void {
    emit('select', chosen.value);
    open.value = false;
}

function tabClass(name: 'browse' | 'upload'): string {
    return tab.value === name
        ? selectedToggleClass
        : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800';
}
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t('store.uploads.dialog.title')"
        :close-label="t('common.close')"
    >
        <div class="mb-4 flex gap-2">
            <button
                type="button"
                class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium"
                :class="tabClass('browse')"
                :aria-pressed="tab === 'browse'"
                @click="tab = 'browse'"
            >
                {{ t('store.uploads.dialog.browseTab') }}
            </button>
            <button
                type="button"
                class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium"
                :class="tabClass('upload')"
                :aria-pressed="tab === 'upload'"
                @click="tab = 'upload'"
            >
                {{ t('store.uploads.dialog.uploadTab') }}
            </button>
        </div>

        <div v-show="tab === 'browse'" class="space-y-4 pb-2">
            <StoreFileFilterBar
                v-model:search="search"
                v-model:sort="sort"
                id-prefix="file-manager"
                @submit="fetchPage(1)"
            />
            <StoreFileGrid
                :files="files"
                :selected-ids="chosen.map((file) => file.id)"
                :empty-message="t('store.uploads.empty')"
                @toggle="toggle"
            />
            <p
                v-if="http.processing"
                class="flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-neutral-400"
            >
                <LoaderCircle class="h-4 w-4 animate-spin" />
                {{ t('store.uploads.dialog.loading') }}
            </p>
            <div
                v-else-if="page && page.meta.current_page < page.meta.last_page"
                class="flex justify-center"
            >
                <button
                    type="button"
                    :class="filterClearButtonClass"
                    @click="fetchPage(page.meta.current_page + 1)"
                >
                    {{ t('store.uploads.dialog.loadMore') }}
                </button>
            </div>
        </div>

        <div v-show="tab === 'upload'" class="pb-2">
            <StoreFileUploader
                :accept="type === 'image' ? 'image/*' : undefined"
                @uploaded="onUploaded"
            />
        </div>

        <template #footer>
            <div :class="filterFooterClass">
                <button
                    type="button"
                    :class="filterApplyButtonClass"
                    @click="confirm"
                >
                    <Check class="h-4 w-4 shrink-0" />
                    {{ t('store.uploads.dialog.confirm') }}
                </button>
                <button
                    type="button"
                    :class="filterClearButtonClass"
                    @click="chosen = []"
                >
                    <X class="h-4 w-4 shrink-0" />
                    {{ t('store.uploads.dialog.clear') }}
                </button>
                <span
                    class="ms-auto text-sm text-gray-500 dark:text-neutral-400"
                >
                    {{
                        t('store.uploads.dialog.selectedCount', {
                            count: chosen.length,
                        })
                    }}
                </span>
            </div>
        </template>
    </FilterDialog>
</template>

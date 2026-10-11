<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { File, FolderOpen, X } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { previews as previewUploads } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import FieldError from '@/components/FieldError.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import { fieldLabelClass, filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { uploadDisplayName } from '@/lib/storeUploads';
import type { StoreUpload, StoreUploadKind } from '@/types/store';

/**
 * A form field holding uploaded files, replacing the Blade
 * `data-toggle="aizuploader"` group. It keeps that group's contract: a hidden
 * input `name` carrying the chosen upload ids, comma-separated, so a store
 * form posts what it posted before. It shows the chosen files with a remove
 * button, and "Browse" opens the file manager dialog.
 */
defineProps<{
    id: string;
    name: string;
    label: string;
    multiple?: boolean;
    type?: StoreUploadKind;
    error?: string;
}>();

/** The comma-separated ids, e.g. a record's current `photos`. */
const ids = defineModel<string>({ default: '' });

const files = ref<StoreUpload[]>([]);
const dialogOpen = ref(false);

const http = useHttp<Record<string, never>, StoreUpload[]>({});

function parsedIds(): number[] {
    return ids.value
        .split(',')
        .map((id) => Number(id.trim()))
        .filter((id) => Number.isInteger(id) && id > 0);
}

function setFiles(chosen: StoreUpload[]): void {
    files.value = chosen;
    ids.value = chosen.map((file) => file.id).join(',');
}

function remove(file: StoreUpload): void {
    setFiles(files.value.filter((kept) => kept.id !== file.id));
}

onMounted(() => {
    const current = parsedIds();

    if (current.length === 0) {
        return;
    }

    http.get(previewUploads.url({ query: { ids: current } }), {
        onSuccess: (response) => {
            files.value = response;
        },
    }).catch(() => undefined);
});
</script>

<template>
    <div>
        <span :id="`${id}-label`" :class="fieldLabelClass">
            {{ label }}
        </span>
        <input type="hidden" :name="name" :value="ids" />

        <ul
            v-if="files.length > 0"
            class="mb-2 flex flex-wrap gap-2"
            :aria-labelledby="`${id}-label`"
        >
            <li
                v-for="file in files"
                :key="file.id"
                class="flex w-28 flex-col gap-1 rounded-md border border-gray-200 p-1 text-xs dark:border-neutral-800"
            >
                <span
                    class="flex aspect-square items-center justify-center overflow-hidden rounded bg-gray-50 text-gray-400 dark:bg-neutral-800 dark:text-neutral-500"
                >
                    <img
                        v-if="file.type === 'image' && file.url"
                        :src="file.url"
                        alt=""
                        class="h-full w-full object-cover"
                    />
                    <File v-else class="h-8 w-8" />
                </span>
                <span class="truncate" :title="uploadDisplayName(file)">
                    {{ uploadDisplayName(file) }}
                </span>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-1 rounded px-1 py-0.5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                    :aria-label="
                        t('store.uploads.field.removeFile', {
                            name: uploadDisplayName(file),
                        })
                    "
                    @click="remove(file)"
                >
                    <X class="h-3.5 w-3.5" />
                    {{ t('store.uploads.field.remove') }}
                </button>
            </li>
        </ul>

        <button
            :id="id"
            type="button"
            :class="filterTriggerButtonClass"
            @click="dialogOpen = true"
        >
            <FolderOpen class="h-4 w-4" />
            {{
                multiple
                    ? t('store.uploads.field.browseMany')
                    : t('store.uploads.field.browse')
            }}
        </button>
        <FieldError :message="error" />

        <StoreFileManagerDialog
            v-model:open="dialogOpen"
            :multiple="multiple"
            :type="type"
            :selected="files"
            @select="setFiles"
        />
    </div>
</template>

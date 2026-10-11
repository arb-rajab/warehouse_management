<script setup lang="ts">
import { File, FileVideo, PackageSearch } from '@lucide/vue';
import { t } from '@/lib/i18n';
import { formatFileSize, uploadDisplayName } from '@/lib/storeUploads';
import type { StoreUpload } from '@/types/store';

/**
 * The tile grid of uploaded files that the uploads library page and the file
 * manager dialog share: a thumbnail (images) or an icon, the name and the
 * size, with a checkbox to pick the file. The `actions` slot adds a
 * file's buttons below it (the library's info, download, copy and delete).
 */
defineProps<{
    files: StoreUpload[];
    selectedIds: number[];
    emptyMessage: string;
}>();

const emit = defineEmits<{
    toggle: [file: StoreUpload];
}>();
</script>

<template>
    <ul
        v-if="files.length > 0"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6"
    >
        <li
            v-for="file in files"
            :key="file.id"
            class="flex flex-col overflow-hidden rounded-lg border bg-white dark:bg-neutral-900"
            :class="
                selectedIds.includes(file.id)
                    ? 'border-blue-600 ring-2 ring-blue-600 dark:border-blue-500 dark:ring-blue-500'
                    : 'border-gray-200 dark:border-neutral-800'
            "
        >
            <label class="relative block cursor-pointer">
                <input
                    type="checkbox"
                    class="absolute start-2 top-2 h-4 w-4 cursor-pointer"
                    :checked="selectedIds.includes(file.id)"
                    :aria-label="
                        t('store.uploads.selectFile', {
                            name: uploadDisplayName(file),
                        })
                    "
                    @change="emit('toggle', file)"
                />
                <span
                    class="flex aspect-square items-center justify-center bg-gray-50 text-gray-400 dark:bg-neutral-800 dark:text-neutral-500"
                >
                    <img
                        v-if="file.type === 'image' && file.url"
                        :src="file.url"
                        alt=""
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                    <FileVideo
                        v-else-if="file.type === 'video'"
                        class="h-10 w-10"
                    />
                    <File v-else class="h-10 w-10" />
                </span>
            </label>
            <div class="flex flex-1 flex-col gap-1 p-2 text-sm">
                <p
                    class="truncate font-medium text-gray-900 dark:text-neutral-100"
                    :title="uploadDisplayName(file)"
                >
                    {{ uploadDisplayName(file) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-neutral-400">
                    {{ formatFileSize(file.size) }}
                </p>
                <slot name="actions" :file="file" />
            </div>
        </li>
    </ul>
    <p
        v-else
        class="flex items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 p-8 text-sm text-gray-500 dark:border-neutral-700 dark:text-neutral-400"
    >
        <PackageSearch class="h-4 w-4 shrink-0" />
        {{ emptyMessage }}
    </p>
</template>
